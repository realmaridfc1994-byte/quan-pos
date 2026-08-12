<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Actions;

use App\Domain\Inventory\Enums\StockMovementRefType;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Ordering\Enums\OrderItemStatus;
use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Enums\TableSessionStatus;
use App\Domain\Ordering\Models\OrderItem;
use App\Domain\Ordering\Models\TableSession;
use App\Domain\Reporting\Models\ProductProfitDaily;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Tổng hợp MỘT NGÀY vào `product_profit_daily` — Phase 3 Bước 8.
 *
 * Nguồn chân lý cho Action này (KHÔNG áp dụng cho màn hình đọc) là
 * `order_items`/`orders`/`table_sessions`/`stock_movements` — bảng
 * `product_profit_daily` chỉ là bản chốt lại để màn hình chủ quán đọc,
 * KHÔNG BAO GIỜ đọc ngược từ bảng đó (giống SummarizeDailyReport ở Phase 2).
 *
 * Luôn TÍNH LẠI TỪ ĐẦU rồi ghi đè (xoá-rồi-chèn lại) cho đúng ngày — gọi lại
 * nhiều lần cho CÙNG một ngày luôn ra đúng một kết quả.
 *
 * ── Doanh thu đã phân bổ giảm giá (docs/kiem-toan-kho.md mục 5) ──────────
 * table_sessions.discount_amount chỉ tồn tại ở CẤP TỔNG BILL, không có cột
 * nào lưu phần giảm giá riêng cho từng order_items. Action này tự chia lại
 * theo tỉ lệ line_amount/subtotal_amount của TOÀN BỘ dòng món (mọi ngày,
 * không chỉ ngày đang tổng hợp) thuộc lượt khách đó, để một dòng món không
 * đổi doanh thu khi báo cáo chạy lại cho ngày khác. Chia bằng intdiv (làm
 * tròn xuống) cho mọi dòng TRỪ dòng cuối cùng — dòng cuối nhận đúng phần dư
 * còn lại, đảm bảo tổng các dòng LUÔN khớp tuyệt đối với discount_amount,
 * không lệch một đồng nào vì làm tròn.
 *
 * ── Giá vốn tại thời điểm bán ─────────────────────────────────────────────
 * cost_amount cộng dồn |cost_delta| của các dòng stock_movements
 * (ref_type=order_item, ref_id=order_items.id) do DeductStockForServedItem
 * ghi lúc phục vụ — đây là giá vốn bình quân gia quyền TẠI THỜI ĐIỂM BÁN,
 * không tính lại theo giá vốn hiện tại (cùng tinh thần CLAUDE.md mục 10: số
 * trên hoá đơn được chốt, không tính lại về sau). Món không trừ kho
 * (deducts_stock=false) không có dòng sổ cái nào → cost_amount = 0.
 *
 * ── Hai con số "độ tin cậy" (Bước 10) ────────────────────────────────────
 * cost_amount = 0 có thể nghĩa là "món này thật sự không tốn nguyên liệu",
 * nhưng cũng có thể nghĩa là "không ai biết nó tốn bao nhiêu". Hai trường hợp
 * sau làm lãi gộp trông CAO HƠN thật, và trước đây không cột nào ghi lại:
 *
 *   - qty_no_cost: bán lúc kho đang âm. Sổ cái vẫn ghi dòng nhưng
 *     has_cost = false và cost_delta = 0 — giá vốn KHÔNG XÁC ĐỊNH ĐƯỢC, chứ
 *     không phải bằng 0. Một phần lẩu ăn 5 nguyên liệu mà chỉ cần MỘT nguyên
 *     liệu rơi vào cảnh này là cả phần đó không tin được, nên đếm cả phần.
 *   - qty_not_served: bếp quên bấm "xong". Không có served_at thì
 *     DeductStockForServedItem chưa chạy, không có dòng sổ cái nào, nhưng
 *     doanh thu vẫn tính đủ vì line_amount không phụ thuộc served_at. Chỉ
 *     đếm khi lượt khách ĐÃ ĐÓNG — bàn còn đang ăn dở thì món chưa bưng ra
 *     là chuyện bình thường, chưa phải sai sót.
 *
 * Hai con số này KHÔNG sửa cost_amount và KHÔNG bịa giá vốn cho dòng nào.
 *
 * ── Lượt khách bị huỷ cả lượt (sửa 12/08, review Phase 3 Bước 10) ────────
 * VoidTableSession chỉ đổi trạng thái LƯỢT KHÁCH sang "void", cố tình KHÔNG
 * đụng tới các dòng món bên trong (H1: huỷ từng dòng phải ghi ai/lúc nào/vì
 * sao, đó là quyết định của người). Trước đây Action này chỉ lọc theo trạng
 * thái dòng món và phiếu bếp, nên món của một bill khách bỏ về không trả tiền
 * vẫn được cộng vào doanh thu — trong khi báo cáo doanh thu ngày lấy từ các
 * phiếu thu nên đếm 0 đồng. Hai màn hình cãi nhau, không ai giải thích được.
 *
 * Kho thì KHÔNG hoàn lại (K6 — món đã bưng ra là đã ăn mất thật), nên dòng sổ
 * cái vẫn còn nguyên; chỉ doanh thu là tiền chưa từng thu, phải bỏ ra.
 */
final class SummarizeProductProfit
{
    public function handle(string $date): Collection
    {
        $ngay = Carbon::parse($date)->startOfDay();

        return DB::transaction(function () use ($ngay): Collection {
            $dongMonHomNay = OrderItem::query()
                ->whereHas('order', fn ($q) => $q
                    ->whereDate('sent_at', $ngay)
                    ->where('status', '!=', OrderStatus::Cancelled)
                    // Lượt khách bị huỷ cả lượt (khách bỏ về không trả tiền)
                    // KHÔNG mang doanh thu — xem ghi chú ở đầu file.
                    ->whereHas('tableSession', fn ($qq) => $qq->where('status', '!=', TableSessionStatus::Void)))
                ->where('status', '!=', OrderItemStatus::Cancelled)
                ->with('order:id,table_session_id')
                ->get();

            ProductProfitDaily::query()->where('date', $ngay->toDateString())->delete();

            if ($dongMonHomNay->isEmpty()) {
                return collect();
            }

            $idPhienLienQuan = $dongMonHomNay->pluck('order.table_session_id')->unique()->values();
            $doanhThuTheoDongMon = $this->tinhDoanhThuDaPhanBoGiamGia($idPhienLienQuan);
            $giaVonTheoDongMon = $this->tinhGiaVonTheoDongMon($dongMonHomNay->pluck('id'));
            $idThieuGiaVon = $this->idDongMonThieuGiaVon($dongMonHomNay->pluck('id'));
            $idPhienDaDong = $this->idPhienDaDong($idPhienLienQuan);
            $idChuaBungRa = $this->idDongMonChuaBungRa($dongMonHomNay, $idPhienDaDong);

            // K19 (docs/schema.md K.7) — hai tập id KHÔNG BAO GIỜ giao nhau:
            // một dòng món hoặc có sổ cái kho (thuộc idThieuGiaVon nếu
            // has_cost=false), hoặc không có sổ cái nào vì chưa phục vụ
            // (thuộc idChuaBungRa) — union thẳng, không lo đếm trùng doanh thu.
            $idKhongDangTin = array_values(array_unique([...$idThieuGiaVon, ...$idChuaBungRa]));

            return $dongMonHomNay
                ->groupBy(fn (OrderItem $item): string => "{$item->product_id}:{$item->product_variant_id}")
                ->map(function (Collection $nhom) use ($ngay, $doanhThuTheoDongMon, $giaVonTheoDongMon, $idThieuGiaVon, $idChuaBungRa, $idKhongDangTin): ProductProfitDaily {
                    $mauDau = $nhom->first();
                    $layDoanhThu = fn (OrderItem $i) => $doanhThuTheoDongMon[$i->id] ?? $i->line_amount;

                    return ProductProfitDaily::query()->create([
                        'date' => $ngay->toDateString(),
                        'product_id' => $mauDau->product_id,
                        'product_variant_id' => $mauDau->product_variant_id,
                        'quantity_sold' => $nhom->sum('quantity'),
                        'revenue_amount' => $nhom->sum($layDoanhThu),
                        'cost_amount' => $nhom->sum(fn (OrderItem $i) => $giaVonTheoDongMon[$i->id] ?? 0),
                        'qty_no_cost' => $nhom
                            ->filter(fn (OrderItem $i): bool => in_array($i->id, $idThieuGiaVon, true))
                            ->sum('quantity'),
                        'qty_not_served' => $nhom
                            ->filter(fn (OrderItem $i): bool => in_array($i->id, $idChuaBungRa, true))
                            ->sum('quantity'),
                        'revenue_uncosted_amount' => $nhom
                            ->filter(fn (OrderItem $i): bool => in_array($i->id, $idKhongDangTin, true))
                            ->sum($layDoanhThu),
                    ]);
                })
                ->values();
        });
    }

    /**
     * @param  Collection<int, int>  $idPhien
     * @return array<int, int> [order_item_id => doanh_thu_da_phan_bo]
     */
    private function tinhDoanhThuDaPhanBoGiamGia(Collection $idPhien): array
    {
        $ketQua = [];

        $phiens = TableSession::query()->whereIn('id', $idPhien)->get(['id', 'subtotal_amount', 'discount_amount']);

        foreach ($phiens as $phien) {
            $dongCuaPhien = OrderItem::query()
                ->whereHas('order', fn ($q) => $q->where('table_session_id', $phien->id)->where('status', '!=', OrderStatus::Cancelled))
                ->where('status', '!=', OrderItemStatus::Cancelled)
                ->orderBy('id')
                ->get(['id', 'line_amount']);

            if ($phien->discount_amount === 0 || $phien->subtotal_amount === 0 || $dongCuaPhien->isEmpty()) {
                foreach ($dongCuaPhien as $dong) {
                    $ketQua[$dong->id] = $dong->line_amount;
                }

                continue;
            }

            $daPhanBo = 0;
            $soDong = $dongCuaPhien->count();

            foreach ($dongCuaPhien as $idx => $dong) {
                if ($idx === $soDong - 1) {
                    $phanBo = $phien->discount_amount - $daPhanBo;
                } else {
                    $phanBo = intdiv($dong->line_amount * $phien->discount_amount, $phien->subtotal_amount);
                    $daPhanBo += $phanBo;
                }

                $ketQua[$dong->id] = $dong->line_amount - $phanBo;
            }
        }

        return $ketQua;
    }

    /**
     * Dòng món có ÍT NHẤT MỘT dòng sổ cái không xác định được giá vốn
     * (has_cost = false, tức là lúc trừ kho thì tồn đang âm).
     *
     * @param  Collection<int, int>  $idDongMon
     * @return list<int> danh sách order_item_id
     */
    private function idDongMonThieuGiaVon(Collection $idDongMon): array
    {
        return StockMovement::query()
            ->where('ref_type', StockMovementRefType::OrderItem)
            ->whereIn('ref_id', $idDongMon)
            ->where('has_cost', false)
            ->distinct()
            ->pluck('ref_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * Dòng món đã tính tiền nhưng bếp chưa bấm xong, CHỈ tính khi lượt khách
     * đã đóng (bàn còn đang ăn dở thì món chưa bưng ra là chuyện bình thường).
     *
     * @param  Collection<int, OrderItem>  $dongMonHomNay
     * @param  list<int>  $idPhienDaDong
     * @return list<int> danh sách order_item_id
     */
    private function idDongMonChuaBungRa(Collection $dongMonHomNay, array $idPhienDaDong): array
    {
        return $dongMonHomNay
            ->filter(fn (OrderItem $i): bool => $i->served_at === null
                && in_array((int) $i->order->table_session_id, $idPhienDaDong, true))
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, int>  $idPhien
     * @return list<int> danh sách table_session_id đã đóng
     */
    private function idPhienDaDong(Collection $idPhien): array
    {
        return TableSession::query()
            ->whereIn('id', $idPhien)
            ->where('status', TableSessionStatus::Closed)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * @param  Collection<int, int>  $idDongMon
     * @return array<int, int> [order_item_id => gia_von]
     */
    private function tinhGiaVonTheoDongMon(Collection $idDongMon): array
    {
        return StockMovement::query()
            ->where('ref_type', StockMovementRefType::OrderItem)
            ->whereIn('ref_id', $idDongMon)
            ->selectRaw('ref_id, SUM(-cost_delta) as tong_gia_von')
            ->groupBy('ref_id')
            ->pluck('tong_gia_von', 'ref_id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }
}
