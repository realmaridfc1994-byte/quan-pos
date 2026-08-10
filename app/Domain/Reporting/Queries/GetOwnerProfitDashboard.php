<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Queries;

use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Inventory\Models\Ingredient;
use App\Domain\Inventory\Models\StockBalance;
use App\Domain\Reporting\Models\IngredientWasteMonthly;
use App\Domain\Reporting\Models\ProductProfitDaily;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Số liệu lãi gộp/hao hụt/tồn thấp cho màn hình chủ quán — Phase 3 Bước 8.
 *
 * CHỈ đọc `product_profit_daily`/`ingredient_waste_monthly`/`stock_balances`
 * — TUYỆT ĐỐI KHÔNG đọc `order_items`/`orders`/`stock_movements` (mục 4 đề
 * bài). `stock_balances` là bảng TỒN HIỆN TẠI (không phải sổ cái), nên đọc
 * thẳng để cảnh báo tồn thấp là hợp lệ — không phạm luật này.
 */
final class GetOwnerProfitDashboard
{
    /** Tỉ lệ lãi (lãi/doanh thu) dưới mức này coi là "lãi thấp". */
    private const NGUONG_TI_LE_LAI_THAP = 0.15;

    /** Lấy top bao nhiêu dòng cho mỗi bảng xếp hạng. */
    private const TOP_N = 20;

    /** @return array<string, mixed> */
    public function handle(): array
    {
        $dauThangNay = Carbon::today()->startOfMonth();
        $tongHopThangNay = $this->tongHopThangNay($dauThangNay, Carbon::today());

        return [
            'thang' => $dauThangNay->translatedFormat('m/Y'),
            'lai_gop_theo_tong' => $tongHopThangNay->sortByDesc('profit_amount')->take(self::TOP_N)->values()->all(),
            'lai_gop_theo_ti_le' => $tongHopThangNay
                ->filter(fn (array $d) => $d['revenue_amount'] > 0)
                ->sortByDesc('margin')
                ->take(self::TOP_N)
                ->values()
                ->all(),
            'ban_chay_lai_thap' => $this->banChayLaiThap($tongHopThangNay),
            'hao_hut_thang_nay' => $this->haoHutThangNay($dauThangNay),
            'ton_thap' => $this->tonThap(),
        ];
    }

    /**
     * @return Collection<int, array{product_id: int, product_variant_id: int, product_name: string, variant_name: string, quantity_sold: int, revenue_amount: int, cost_amount: int, profit_amount: int, margin: float}>
     */
    private function tongHopThangNay(Carbon $tu, Carbon $den): Collection
    {
        $tongHop = ProductProfitDaily::query()
            ->whereBetween('date', [$tu->toDateString(), $den->toDateString()])
            ->selectRaw('product_id, product_variant_id, SUM(quantity_sold) as so_luong, SUM(revenue_amount) as doanh_thu, SUM(cost_amount) as gia_von')
            ->groupBy('product_id', 'product_variant_id')
            ->get();

        $bienThe = ProductVariant::query()
            ->whereIn('id', $tongHop->pluck('product_variant_id'))
            ->with('product:id,name')
            ->get()
            ->keyBy('id');

        return $tongHop->map(function ($dong) use ($bienThe): array {
            $v = $bienThe->get((int) $dong->product_variant_id);
            $doanhThu = (int) $dong->doanh_thu;
            $giaVon = (int) $dong->gia_von;
            $laiGop = $doanhThu - $giaVon;

            return [
                'product_id' => (int) $dong->product_id,
                'product_variant_id' => (int) $dong->product_variant_id,
                'product_name' => $v?->product?->name ?? '(món đã xoá)',
                'variant_name' => $v?->name ?? '',
                'quantity_sold' => (int) $dong->so_luong,
                'revenue_amount' => $doanhThu,
                'cost_amount' => $giaVon,
                'profit_amount' => $laiGop,
                'margin' => $doanhThu > 0 ? $laiGop / $doanhThu : 0.0,
            ];
        });
    }

    /**
     * "Bán chạy" = số lượng bán >= trung bình số lượng bán mọi món tháng này.
     * "Lãi thấp" = tỉ lệ lãi dưới NGUONG_TI_LE_LAI_THAP.
     *
     * @param  Collection<int, array<string, mixed>>  $tongHopThangNay
     * @return list<array<string, mixed>>
     */
    private function banChayLaiThap(Collection $tongHopThangNay): array
    {
        if ($tongHopThangNay->isEmpty()) {
            return [];
        }

        $trungBinhSoLuong = $tongHopThangNay->avg('quantity_sold');

        return $tongHopThangNay
            ->filter(fn (array $d) => $d['revenue_amount'] > 0
                && $d['quantity_sold'] >= $trungBinhSoLuong
                && $d['margin'] < self::NGUONG_TI_LE_LAI_THAP)
            ->sortBy('margin')
            ->take(self::TOP_N)
            ->values()
            ->all();
    }

    /** @return list<array{ingredient_id: int, ingredient_name: string, waste_qty: int, waste_cost: int, waste_cost_thang_truoc: int}> */
    private function haoHutThangNay(Carbon $dauThangNay): array
    {
        $dauThangTruoc = $dauThangNay->clone()->subMonthNoOverflow();

        $thangNay = IngredientWasteMonthly::query()->where('month', $dauThangNay->toDateString())->get()->keyBy('ingredient_id');
        $thangTruoc = IngredientWasteMonthly::query()->where('month', $dauThangTruoc->toDateString())->get()->keyBy('ingredient_id');

        $idNguyenLieu = $thangNay->keys()->merge($thangTruoc->keys())->unique();
        if ($idNguyenLieu->isEmpty()) {
            return [];
        }

        $nguyenLieu = Ingredient::query()->whereIn('id', $idNguyenLieu)->get(['id', 'name'])->keyBy('id');

        return $idNguyenLieu
            ->map(fn (int $id): array => [
                'ingredient_id' => $id,
                'ingredient_name' => $nguyenLieu->get($id)?->name ?? '(nguyên liệu đã xoá)',
                'waste_qty' => $thangNay->get($id)?->waste_qty ?? 0,
                'waste_cost' => $thangNay->get($id)?->waste_cost ?? 0,
                'waste_cost_thang_truoc' => $thangTruoc->get($id)?->waste_cost ?? 0,
            ])
            ->sortByDesc('waste_cost')
            ->values()
            ->all();
    }

    /** @return list<array{ingredient_id: int, ingredient_name: string, qty: int, min_qty: int}> */
    private function tonThap(): array
    {
        return StockBalance::query()
            ->join('ingredients', 'ingredients.id', '=', 'stock_balances.ingredient_id')
            ->where('ingredients.min_qty', '>', 0)
            ->whereColumn('stock_balances.qty', '<=', 'ingredients.min_qty')
            ->orderBy('stock_balances.qty')
            ->get(['ingredients.id as ingredient_id', 'ingredients.name as ingredient_name', 'ingredients.min_qty', 'stock_balances.qty'])
            ->map(fn ($dong): array => [
                'ingredient_id' => (int) $dong->ingredient_id,
                'ingredient_name' => $dong->ingredient_name,
                'qty' => (int) $dong->qty,
                'min_qty' => (int) $dong->min_qty,
            ])
            ->all();
    }
}
