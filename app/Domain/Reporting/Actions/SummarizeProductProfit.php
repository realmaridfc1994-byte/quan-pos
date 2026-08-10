<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Actions;

use App\Domain\Inventory\Enums\StockMovementRefType;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Ordering\Enums\OrderItemStatus;
use App\Domain\Ordering\Enums\OrderStatus;
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
                    ->where('status', '!=', OrderStatus::Cancelled))
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

            return $dongMonHomNay
                ->groupBy(fn (OrderItem $item): string => "{$item->product_id}:{$item->product_variant_id}")
                ->map(function (Collection $nhom) use ($ngay, $doanhThuTheoDongMon, $giaVonTheoDongMon): ProductProfitDaily {
                    $mauDau = $nhom->first();

                    return ProductProfitDaily::query()->create([
                        'date' => $ngay->toDateString(),
                        'product_id' => $mauDau->product_id,
                        'product_variant_id' => $mauDau->product_variant_id,
                        'quantity_sold' => $nhom->sum('quantity'),
                        'revenue_amount' => $nhom->sum(fn (OrderItem $i) => $doanhThuTheoDongMon[$i->id] ?? $i->line_amount),
                        'cost_amount' => $nhom->sum(fn (OrderItem $i) => $giaVonTheoDongMon[$i->id] ?? 0),
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
