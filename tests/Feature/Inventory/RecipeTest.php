<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Inventory\Models\Ingredient;
use App\Domain\Inventory\Models\Recipe;

it('món có định lượng đọc ra đúng danh sách nguyên liệu và số lượng', function () {
    $lauGa = ProductVariant::factory()->create(['deducts_stock' => true]);
    $ga = Ingredient::factory()->create(['name' => 'Gà ta']);
    $nam = Ingredient::factory()->create(['name' => 'Nấm kim châm']);

    Recipe::query()->create([
        'product_variant_id' => $lauGa->id,
        'ingredient_id' => $ga->id,
        'qty_base' => 800,
    ]);
    Recipe::query()->create([
        'product_variant_id' => $lauGa->id,
        'ingredient_id' => $nam->id,
        'qty_base' => 150,
    ]);

    $dinhLuong = $lauGa->recipes()->with('ingredient')->get();

    expect($dinhLuong)->toHaveCount(2)
        ->and($dinhLuong->firstWhere('ingredient_id', $ga->id)->qty_base)->toBe(800)
        ->and($dinhLuong->firstWhere('ingredient_id', $nam->id)->qty_base)->toBe(150)
        ->and($dinhLuong->firstWhere('ingredient_id', $ga->id)->ingredient->name)->toBe('Gà ta');
});
