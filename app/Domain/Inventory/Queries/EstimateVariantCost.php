<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Queries;

use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Inventory\Models\StockBalance;
use App\Support\Money;

/**
 * Giá vốn ước tính của một biến thể = tổng (số lượng định lượng × giá vốn
 * trung bình hiện tại của nguyên liệu). Giá vốn trung bình đọc từ
 * stock_balances (total_cost ÷ qty) — KHÔNG lưu ở đâu cả, luôn tính lại tại
 * thời điểm gọi, nên đổi giá vốn nguyên liệu là mọi món dùng nó đổi theo ngay.
 *
 * Nguyên liệu chưa từng nhập hàng (chưa có dòng stock_balances, hoặc qty <= 0)
 * thì coi giá vốn trung bình là 0 — đây là ƯỚC TÍNH cho Bước 3, chưa phải giá
 * vốn thật (giá vốn thật chỉ có từ Bước 4 khi có phiếu nhập).
 */
final class EstimateVariantCost
{
    public function handle(ProductVariant $variant): Money
    {
        $recipes = $variant->recipes()->with('ingredient')->get();

        if ($recipes->isEmpty()) {
            return Money::zero();
        }

        $ingredientIds = $recipes->pluck('ingredient_id')->all();
        $balances = StockBalance::query()
            ->whereIn('ingredient_id', $ingredientIds)
            ->get()
            ->keyBy('ingredient_id');

        $total = Money::zero();

        foreach ($recipes as $recipe) {
            $balance = $balances->get($recipe->ingredient_id);
            $donGia = $this->giaVonTrungBinh($balance);

            $total = $total->plus($donGia->times($recipe->qty_base));
        }

        return $total;
    }

    private function giaVonTrungBinh(?StockBalance $balance): Money
    {
        if ($balance === null || $balance->qty <= 0) {
            return Money::zero();
        }

        return Money::fromInt((int) round($balance->total_cost / $balance->qty));
    }
}
