<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Inventory\Models\Ingredient;
use App\Domain\Inventory\Models\Recipe;
use Illuminate\Database\QueryException;

it('món không đánh dấu trừ kho mà thêm định lượng thì bị chặn ở tầng dữ liệu', function () {
    $khanLanh = ProductVariant::factory()->create(['deducts_stock' => false]);
    $ga = Ingredient::factory()->create();

    expect(fn () => Recipe::query()->create([
        'product_variant_id' => $khanLanh->id,
        'ingredient_id' => $ga->id,
        'qty_base' => 1,
    ]))->toThrow(QueryException::class);

    expect(Recipe::query()->count())->toBe(0);
});

it('bật lại đánh dấu trừ kho rồi thêm định lượng thì thành công', function () {
    $mon = ProductVariant::factory()->create(['deducts_stock' => true]);
    $ga = Ingredient::factory()->create();

    Recipe::query()->create([
        'product_variant_id' => $mon->id,
        'ingredient_id' => $ga->id,
        'qty_base' => 500,
    ]);

    expect(Recipe::query()->count())->toBe(1);
});
