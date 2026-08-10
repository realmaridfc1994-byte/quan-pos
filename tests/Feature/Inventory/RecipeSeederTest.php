<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Inventory\Models\Recipe;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RecipeSeeder;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

it('lẩu gà có định lượng đúng như ví dụ trong docs/schema.md: 800g gà, 150g nấm kim châm', function () {
    $lauGa = ProductVariant::query()
        ->whereHas('product', fn ($q) => $q->where('code', 'LAUGA'))
        ->where('name', 'Mặc định')
        ->first();

    $dinhLuong = $lauGa->recipes()->with('ingredient')->get()->keyBy(fn ($r) => $r->ingredient->code);

    expect($dinhLuong->get('GA-TA')->qty_base)->toBe(800)
        ->and($dinhLuong->get('NAM-KIMCHAM')->qty_base)->toBe(150);
});

it('bia Tiger có định lượng đúng cho từng biến thể — lon/chai 1, thùng 24', function () {
    $tiger = Product::query()->where('code', 'TIGER')->first();

    $lon = ProductVariant::query()->where('product_id', $tiger->id)->where('name', 'Lon')->first();
    $thung = ProductVariant::query()->where('product_id', $tiger->id)->where('name', 'Thùng')->first();

    expect($lon->recipes()->sole()->qty_base)->toBe(1)
        ->and($thung->recipes()->sole()->qty_base)->toBe(24)
        ->and($lon->deducts_stock)->toBeTrue()
        ->and($thung->deducts_stock)->toBeTrue();
});

it('chạy seeder định lượng hai lần không tạo trùng, không lỗi ràng buộc UNIQUE', function () {
    $lanDau = Recipe::query()->count();

    $this->seed(RecipeSeeder::class);

    expect(Recipe::query()->count())->toBe($lanDau);
});

it('món không tìm được nguyên liệu khớp thì không có định lượng và không đánh dấu trừ kho', function () {
    $traiCay = ProductVariant::query()
        ->whereHas('product', fn ($q) => $q->where('code', 'TRAICAY'))
        ->first();

    expect($traiCay->deducts_stock)->toBeFalse()
        ->and($traiCay->recipes()->count())->toBe(0);
});
