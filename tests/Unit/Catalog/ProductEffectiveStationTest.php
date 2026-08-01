<?php

declare(strict_types=1);

use App\Domain\Catalog\Enums\Station;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;

it('E6: món không ghi đè trạm thì lấy theo nhóm món', function () {
    $category = Category::factory()->barCategory()->make();
    $product = Product::factory()->make(['station_override' => null]);
    $product->setRelation('category', $category);

    expect($product->effectiveStation())->toBe(Station::Bar);
});

it('E6: món ghi đè trạm thì lấy theo trạm riêng, bỏ qua nhóm món', function () {
    $category = Category::factory()->kitchenCategory()->make();
    $product = Product::factory()->make(['station_override' => Station::Bar]);
    $product->setRelation('category', $category);

    expect($product->effectiveStation())->toBe(Station::Bar);
});
