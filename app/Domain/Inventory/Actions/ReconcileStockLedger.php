<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\DTO\StockReconciliationResult;
use App\Domain\Inventory\Enums\StockMovementRefType;
use App\Domain\Inventory\Models\Ingredient;
use App\Domain\Inventory\Models\StockBalance;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Ordering\Models\OrderItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Đối soát sổ cái kho — Phase 3 Bước 9. Đúng ba bất biến theo docs/schema.md:
 *
 *  a. K2 — với MỌI nguyên liệu: cộng hết qty_delta trong sổ cái = qty trong
 *     bảng tồn. Luôn kiểm TOÀN BỘ lịch sử, không có khái niệm khoảng ngày.
 *  b. K3 — cộng hết cost_delta trong sổ cái = total_cost trong bảng tồn.
 *     Cũng luôn kiểm toàn bộ lịch sử.
 *  c. Mọi order_items có served_at đều có dòng sổ cái tương ứng, và ngược
 *     lại. Đây là chỗ DUY NHẤT nhận khoảng ngày (lọc theo served_at) — hai
 *     mục trên không nhận vì là số cộng dồn không thể cắt theo ngày.
 *     Lưu ý dòng 1894 docs/schema.md: dòng tách ra khi huỷ một phần
 *     (split_from_item_id khác rỗng) CŨNG có served_at kế thừa nhưng KHÔNG
 *     có dòng sổ cái riêng (K7) — phải loại trừ, không thì báo lệch giả.
 *
 * Luôn tự ghi kết quả vào activity_log (log_name = 'doi-soat-kho') — CHỖ
 * DUY NHẤT màn hình chủ quán đọc để hiện cảnh báo, không tạo bảng DB mới
 * (spatie/laravel-activitylog đã có sẵn, dùng lại đúng cách VerifyApproverPin
 * đã làm).
 */
final class ReconcileStockLedger
{
    public function handle(?Carbon $tuNgay = null, ?Carbon $denNgay = null): StockReconciliationResult
    {
        $lechQty = $this->doiSoatSoLuong();
        $lechCost = $this->doiSoatGiaTri();
        [$thieuSoCai, $soCaiMoCoi] = $this->doiSoatServedAt($tuNgay, $denNgay);

        $ketQua = new StockReconciliationResult($lechQty, $lechCost, $thieuSoCai, $soCaiMoCoi);

        activity('doi-soat-kho')
            ->withProperties([
                ...$ketQua->toArray(),
                'tu_ngay' => $tuNgay?->toDateString(),
                'den_ngay' => $denNgay?->toDateString(),
            ])
            ->log($ketQua->sach() ? 'Đối soát kho sạch — không lệch.' : 'Đối soát kho phát hiện lệch.');

        return $ketQua;
    }

    /** @return list<array{ingredient_id: int, ingredient_name: string, so_cai: int, ton_kho: int, lech: int}> */
    private function doiSoatSoLuong(): array
    {
        return $this->soSanh(
            soCai: StockMovement::query()->selectRaw('ingredient_id, SUM(qty_delta) as tong')->groupBy('ingredient_id')->pluck('tong', 'ingredient_id'),
            tonKho: StockBalance::query()->pluck('qty', 'ingredient_id'),
        );
    }

    /** @return list<array{ingredient_id: int, ingredient_name: string, so_cai: int, ton_kho: int, lech: int}> */
    private function doiSoatGiaTri(): array
    {
        return $this->soSanh(
            soCai: StockMovement::query()->selectRaw('ingredient_id, SUM(cost_delta) as tong')->groupBy('ingredient_id')->pluck('tong', 'ingredient_id'),
            tonKho: StockBalance::query()->pluck('total_cost', 'ingredient_id'),
        );
    }

    /**
     * @param  Collection<int, int>  $soCai
     * @param  Collection<int, int>  $tonKho
     * @return list<array{ingredient_id: int, ingredient_name: string, so_cai: int, ton_kho: int, lech: int}>
     */
    private function soSanh(Collection $soCai, Collection $tonKho): array
    {
        $idTatCa = $soCai->keys()->merge($tonKho->keys())->unique();
        if ($idTatCa->isEmpty()) {
            return [];
        }

        $ten = Ingredient::query()->whereIn('id', $idTatCa)->pluck('name', 'id');

        $ketQua = [];
        foreach ($idTatCa as $id) {
            $giaTriSoCai = (int) ($soCai[$id] ?? 0);
            $giaTriTonKho = (int) ($tonKho[$id] ?? 0);

            if ($giaTriSoCai !== $giaTriTonKho) {
                $ketQua[] = [
                    'ingredient_id' => (int) $id,
                    'ingredient_name' => $ten[$id] ?? "#{$id}",
                    'so_cai' => $giaTriSoCai,
                    'ton_kho' => $giaTriTonKho,
                    'lech' => $giaTriTonKho - $giaTriSoCai,
                ];
            }
        }

        return $ketQua;
    }

    /**
     * @return array{0: list<array{order_item_id: int, product_name: string, variant_name: string, served_at: string}>, 1: list<array{stock_movement_id: int, ref_id: ?int}>}
     */
    private function doiSoatServedAt(?Carbon $tuNgay, ?Carbon $denNgay): array
    {
        $donMonServed = OrderItem::query()
            ->whereNotNull('served_at')
            ->whereNull('split_from_item_id')
            ->when($tuNgay !== null, fn ($q) => $q->whereDate('served_at', '>=', $tuNgay))
            ->when($denNgay !== null, fn ($q) => $q->whereDate('served_at', '<=', $denNgay))
            ->with('productVariant.recipes')
            ->get();

        $idDaTru = StockMovement::query()
            ->where('ref_type', StockMovementRefType::OrderItem)
            ->pluck('ref_id')
            ->unique();

        $thieuSoCai = [];
        foreach ($donMonServed as $item) {
            if (! $item->productVariant->deducts_stock || $item->productVariant->recipes->isEmpty()) {
                continue;
            }

            if (! $idDaTru->contains($item->id)) {
                $thieuSoCai[] = [
                    'order_item_id' => $item->id,
                    'product_name' => $item->product_name,
                    'variant_name' => $item->variant_name,
                    'served_at' => $item->served_at->toDateTimeString(),
                ];
            }
        }

        // Ngược lại: mọi dòng sổ cái ref_type=order_item phải trỏ về một
        // order_item CÓ served_at — không lọc theo khoảng ngày (bất biến dữ
        // liệu, không phải số theo kỳ).
        $soCaiOrderItem = StockMovement::query()
            ->where('ref_type', StockMovementRefType::OrderItem)
            ->get(['id', 'ref_id']);

        $idDaServed = OrderItem::query()
            ->whereIn('id', $soCaiOrderItem->pluck('ref_id')->unique())
            ->whereNotNull('served_at')
            ->pluck('id');

        $soCaiMoCoi = $soCaiOrderItem
            ->reject(fn ($dong) => $idDaServed->contains($dong->ref_id))
            ->map(fn ($dong) => ['stock_movement_id' => $dong->id, 'ref_id' => $dong->ref_id])
            ->values()
            ->all();

        return [$thieuSoCai, $soCaiMoCoi];
    }
}
