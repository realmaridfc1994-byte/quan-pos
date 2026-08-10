<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Inventory\Models\Ingredient;
use App\Domain\Inventory\Models\Recipe;
use App\Domain\Inventory\Models\StockBalance;
use App\Domain\Inventory\Queries\EstimateVariantCost;

it('tính giá vốn ước tính bằng tổng số lượng nhân giá vốn nguyên liệu', function () {
    $lauGa = ProductVariant::factory()->create(['deducts_stock' => true]);

    $ga = Ingredient::factory()->create();
    StockBalance::choPhepGhi(fn () => StockBalance::query()->create(['ingredient_id' => $ga->id, 'qty' => 1000, 'total_cost' => 60_000])); // 60đ/gam

    $nam = Ingredient::factory()->create();
    StockBalance::choPhepGhi(fn () => StockBalance::query()->create(['ingredient_id' => $nam->id, 'qty' => 500, 'total_cost' => 25_000])); // 50đ/gam

    Recipe::query()->create(['product_variant_id' => $lauGa->id, 'ingredient_id' => $ga->id, 'qty_base' => 800]);
    Recipe::query()->create(['product_variant_id' => $lauGa->id, 'ingredient_id' => $nam->id, 'qty_base' => 150]);

    $giaVon = app(EstimateVariantCost::class)->handle($lauGa->fresh());

    // 800 * 60 + 150 * 50 = 48.000 + 7.500 = 55.500
    expect($giaVon->amount)->toBe(55_500);
});

it('đổi giá vốn một nguyên liệu thì giá vốn ước tính của món dùng nó đổi theo ngay', function () {
    $mon = ProductVariant::factory()->create(['deducts_stock' => true]);
    $ga = Ingredient::factory()->create();
    $balance = StockBalance::choPhepGhi(fn () => StockBalance::query()->create(['ingredient_id' => $ga->id, 'qty' => 1000, 'total_cost' => 60_000]));

    Recipe::query()->create(['product_variant_id' => $mon->id, 'ingredient_id' => $ga->id, 'qty_base' => 800]);

    $truoc = app(EstimateVariantCost::class)->handle($mon->fresh());
    expect($truoc->amount)->toBe(48_000);

    StockBalance::choPhepGhi(fn () => $balance->update(['total_cost' => 120_000]));

    $sau = app(EstimateVariantCost::class)->handle($mon->fresh());
    expect($sau->amount)->toBe(96_000);
});

it('món chưa có định lượng thì giá vốn ước tính là 0', function () {
    $mon = ProductVariant::factory()->create(['deducts_stock' => true]);

    $giaVon = app(EstimateVariantCost::class)->handle($mon);

    expect($giaVon->isZero())->toBeTrue();
});

it('nguyên liệu chưa từng nhập hàng thì tính giá vốn là 0, không lỗi', function () {
    $mon = ProductVariant::factory()->create(['deducts_stock' => true]);
    $ga = Ingredient::factory()->create();

    Recipe::query()->create(['product_variant_id' => $mon->id, 'ingredient_id' => $ga->id, 'qty_base' => 500]);

    $giaVon = app(EstimateVariantCost::class)->handle($mon->fresh());

    expect($giaVon->isZero())->toBeTrue();
});
