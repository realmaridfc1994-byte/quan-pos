<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Actions;

use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Reporting\Models\IngredientWasteMonthly;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Tổng hợp MỘT THÁNG vào `ingredient_waste_monthly` — Phase 3 Bước 8.
 *
 * Nguồn chân lý là `stock_movements` (type=waste) — bảng tổng hợp chỉ là
 * bản chốt lại để màn hình chủ quán đọc, không được đọc thẳng sổ cái (mục 4
 * đề bài). Luôn TÍNH LẠI TỪ ĐẦU rồi ghi đè đúng tháng đang chạy, giống
 * SummarizeDailyReport/SummarizeProductProfit.
 */
final class SummarizeIngredientWasteMonthly
{
    public function handle(string $anyDateInMonth): Collection
    {
        $dauThang = Carbon::parse($anyDateInMonth)->startOfMonth();
        $cuoiThang = $dauThang->clone()->endOfMonth();

        return DB::transaction(function () use ($dauThang, $cuoiThang): Collection {
            IngredientWasteMonthly::query()->where('month', $dauThang->toDateString())->delete();

            $tongTheoNguyenLieu = StockMovement::query()
                ->where('type', StockMovementType::Waste)
                ->whereBetween('occurred_at', [$dauThang, $cuoiThang])
                ->selectRaw('ingredient_id, SUM(-qty_delta) as tong_qty, SUM(-cost_delta) as tong_cost')
                ->groupBy('ingredient_id')
                ->get();

            return $tongTheoNguyenLieu->map(fn ($dong) => IngredientWasteMonthly::query()->create([
                'month' => $dauThang->toDateString(),
                'ingredient_id' => $dong->ingredient_id,
                'waste_qty' => (int) $dong->tong_qty,
                'waste_cost' => (int) $dong->tong_cost,
            ]));
        });
    }
}
