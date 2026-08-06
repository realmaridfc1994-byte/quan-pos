<?php

declare(strict_types=1);

use App\Domain\Inventory\Models\Ingredient;
use App\Domain\Inventory\Models\Supplier;
use Database\Seeders\InventorySeeder;

it('nạp đúng 60 nguyên liệu kèm ít nhất một đơn vị quy đổi mỗi nguyên liệu', function () {
    $this->seed(InventorySeeder::class);

    expect(Ingredient::query()->count())->toBe(60)
        ->and(Supplier::query()->count())->toBeGreaterThan(0);

    Ingredient::query()->get()->each(
        fn (Ingredient $nl) => expect($nl->units()->count())->toBeGreaterThan(0)
    );
});

it('chạy seeder hai lần không tạo trùng, không lỗi ràng buộc UNIQUE', function () {
    $this->seed(InventorySeeder::class);
    $this->seed(InventorySeeder::class);

    expect(Ingredient::query()->count())->toBe(60);
});

it('mỗi nguyên liệu chỉ có đúng một đơn vị nhập mặc định', function () {
    $this->seed(InventorySeeder::class);

    Ingredient::query()->get()->each(function (Ingredient $nl) {
        expect($nl->units()->where('is_purchase_default', true)->count())->toBe(1);
    });
});
