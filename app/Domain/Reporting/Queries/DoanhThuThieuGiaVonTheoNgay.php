<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Queries;

use App\Domain\Inventory\Enums\StockMovementRefType;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Ordering\Enums\OrderItemStatus;
use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Enums\TableSessionStatus;
use App\Domain\Ordering\Models\OrderItem;
use App\Domain\Ordering\Models\TableSession;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * CHỖ TÍNH DUY NHẤT cho câu hỏi "ngày hôm đó, phần doanh thu nào chưa biết giá
 * vốn" — Phase 4 Bước 4B.0.
 *
 * Vì sao tách ra khỏi SummarizeProductProfit: từ bước này, CẢ HAI bảng báo cáo
 * đều cần con số đó — bảng lãi gộp theo món (`product_profit_daily`) và bảng
 * tóm tắt ngày (`daily_summaries`). Nếu mỗi bên tự tính một bản thì sớm muộn
 * hai màn hình sẽ nói hai con số khác nhau về cùng một buổi tối, và không ai
 * biết bên nào đúng.
 *
 * Cách còn lại — cho bảng tóm tắt ngày đọc thẳng bảng lãi gộp — bị loại: hai
 * bảng tổng hợp luôn tính lại TỪ NGUỒN GỐC (orders/order_items/stock_movements),
 * không bảng tổng hợp nào đọc bảng tổng hợp khác. Đọc ngược sẽ bắt hai việc
 * tổng hợp phải chạy đúng thứ tự mãi mãi, và ai đảo thứ tự thì số sai một cách
 * lặng lẽ, không có lỗi nào nổ ra.
 *
 * Nội dung các quy tắc bên dưới GIỮ NGUYÊN từ SummarizeProductProfit (Phase 3
 * Bước 8/10), chỉ dời chỗ ở, không đổi một phép tính nào.
 *
 * ── Doanh thu đã phân bổ giảm giá (docs/kiem-toan-kho.md mục 5) ──────────
 * table_sessions.discount_amount chỉ tồn tại ở CẤP TỔNG BILL, không có cột
 * nào lưu phần giảm giá riêng cho từng order_items. Lớp này tự chia lại theo
 * tỉ lệ line_amount/subtotal_amount của TOÀN BỘ dòng món (mọi ngày, không chỉ
 * ngày đang tổng hợp) thuộc lượt khách đó, để một dòng món không đổi doanh thu
 * khi báo cáo chạy lại cho ngày khác. Chia bằng intdiv (làm tròn xuống) cho
 * mọi dòng TRỪ dòng cuối cùng — dòng cuối nhận đúng phần dư còn lại, đảm bảo
 * tổng các dòng LUÔN khớp tuyệt đối với discount_amount, không lệch một đồng.
 *
 * ── Giá vốn tại thời điểm bán ─────────────────────────────────────────────
 * Cộng dồn |cost_delta| của các dòng stock_movements (ref_type=order_item) do
 * DeductStockForServedItem ghi lúc phục vụ — giá vốn bình quân gia quyền TẠI
 * THỜI ĐIỂM BÁN, không tính lại theo giá vốn hiện tại. Món không trừ kho
 * (deducts_stock=false) không có dòng sổ cái nào → giá vốn 0.
 *
 * ── Hai đường làm lãi gộp trông cao hơn thật ──────────────────────────────
 *   - idThieuGiaVon: bán lúc kho đang âm. Sổ cái vẫn ghi dòng nhưng
 *     has_cost = false và cost_delta = 0 — giá vốn KHÔNG XÁC ĐỊNH ĐƯỢC, chứ
 *     không phải bằng 0. Một phần lẩu ăn 5 nguyên liệu mà chỉ cần MỘT nguyên
 *     liệu rơi vào cảnh này là cả phần đó không tin được, nên tính cả phần.
 *   - idChuaBungRa: bếp quên bấm "xong". Không có served_at thì
 *     DeductStockForServedItem chưa chạy, không có dòng sổ cái nào, nhưng
 *     doanh thu vẫn tính đủ vì line_amount không phụ thuộc served_at. Chỉ
 *     tính khi lượt khách ĐÃ ĐÓNG — bàn còn đang ăn dở thì món chưa bưng ra
 *     là chuyện bình thường, chưa phải sai sót.
 *
 * Lớp này KHÔNG bịa giá vốn cho dòng nào và KHÔNG ghi vào bảng nào.
 *
 * ── Lượt khách bị huỷ cả lượt ─────────────────────────────────────────────
 * VoidTableSession chỉ đổi trạng thái LƯỢT KHÁCH sang "void", cố tình KHÔNG
 * đụng tới các dòng món bên trong (H1). Món của một bill khách bỏ về không trả
 * tiền KHÔNG mang doanh thu — kho thì không hoàn lại (K6, món đã bưng ra là đã
 * ăn mất thật), chỉ doanh thu là tiền chưa từng thu nên phải bỏ ra.
 */
final class DoanhThuThieuGiaVonTheoNgay
{
    public function handle(string $date): KetQuaThieuGiaVonNgay
    {
        $ngay = Carbon::parse($date)->startOfDay();

        $dongMonHomNay = OrderItem::query()
            ->whereHas('order', fn ($q) => $q
                ->whereDate('sent_at', $ngay)
                ->where('status', '!=', OrderStatus::Cancelled)
                ->whereHas('tableSession', fn ($qq) => $qq->where('status', '!=', TableSessionStatus::Void)))
            ->where('status', '!=', OrderItemStatus::Cancelled)
            ->with('order:id,table_session_id')
            ->get();

        if ($dongMonHomNay->isEmpty()) {
            return new KetQuaThieuGiaVonNgay(collect(), [], [], [], []);
        }

        $idPhienLienQuan = $dongMonHomNay->pluck('order.table_session_id')->unique()->values();
        $idDongMon = $dongMonHomNay->pluck('id');

        return new KetQuaThieuGiaVonNgay(
            dongMon: $dongMonHomNay,
            doanhThuTheoDongMon: $this->tinhDoanhThuDaPhanBoGiamGia($idPhienLienQuan),
            giaVonTheoDongMon: $this->tinhGiaVonTheoDongMon($idDongMon),
            idThieuGiaVon: $this->idDongMonThieuGiaVon($idDongMon),
            idChuaBungRa: $this->idDongMonChuaBungRa($dongMonHomNay, $this->idPhienDaDong($idPhienLienQuan)),
        );
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
