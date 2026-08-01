<?php

declare(strict_types=1);

use App\Domain\Catalog\Actions\SetDefaultProductVariant;
use App\Domain\Catalog\Actions\ToggleProductVariantActive;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Exceptions\DomainException;

it('E1: chặn tắt biến thể đang bán cuối cùng của một món', function () {
    $product = Product::factory()->create();
    $bienThe = ProductVariant::factory()->for($product)->create(['is_active' => true]);

    expect(fn () => app(ToggleProductVariantActive::class)->handle($bienThe))
        ->toThrow(DomainException::class, 'Đây là biến thể cuối cùng đang bán của món này. Phải có ít nhất một biến thể đang bán.');

    expect($bienThe->refresh()->is_active)->toBeTrue();
});

it('E1: còn từ hai biến thể đang bán trở lên thì tắt một cái vẫn được', function () {
    $product = Product::factory()->create();
    $a = ProductVariant::factory()->for($product)->create(['name' => 'Lon', 'is_active' => true]);
    $b = ProductVariant::factory()->for($product)->create(['name' => 'Chai', 'is_active' => true]);

    app(ToggleProductVariantActive::class)->handle($a);

    expect($a->refresh()->is_active)->toBeFalse()
        ->and($b->refresh()->is_active)->toBeTrue();
});

it('E1: bật lại một biến thể đang tắt không cần kiểm tra gì', function () {
    $product = Product::factory()->create();
    $bienThe = ProductVariant::factory()->for($product)->inactive()->create(['name' => 'Chai']);
    // Cần ít nhất một biến thể khác đang bán để món hợp lệ, nhưng việc BẬT không đụng bất biến E1.
    ProductVariant::factory()->for($product)->create(['name' => 'Lon']);

    app(ToggleProductVariantActive::class)->handle($bienThe);

    expect($bienThe->refresh()->is_active)->toBeTrue();
});

it('E2: đặt biến thể mới làm mặc định thì biến thể cũ tự bỏ cờ', function () {
    $product = Product::factory()->create();
    $cu = ProductVariant::factory()->for($product)->create(['name' => 'Lon', 'is_default' => true]);
    $moi = ProductVariant::factory()->for($product)->create(['name' => 'Chai', 'is_default' => false]);

    app(SetDefaultProductVariant::class)->handle($moi);

    expect($cu->refresh()->is_default)->toBeFalse()
        ->and($moi->refresh()->is_default)->toBeTrue();
});

it('E2: luôn đúng một biến thể mặc định trong số nhiều biến thể của một món', function () {
    $product = Product::factory()->create();
    $a = ProductVariant::factory()->for($product)->create(['name' => 'Lon', 'is_default' => true]);
    $b = ProductVariant::factory()->for($product)->create(['name' => 'Chai', 'is_default' => false]);
    $c = ProductVariant::factory()->for($product)->create(['name' => 'Thùng', 'is_default' => false]);

    app(SetDefaultProductVariant::class)->handle($c);

    $soMacDinh = ProductVariant::query()->where('product_id', $product->id)->where('is_default', true)->count();

    expect($soMacDinh)->toBe(1)
        ->and($c->refresh()->is_default)->toBeTrue()
        ->and($a->refresh()->is_default)->toBeFalse()
        ->and($b->refresh()->is_default)->toBeFalse();
});

it('E2: đặt mặc định không đụng biến thể mặc định của MÓN KHÁC', function () {
    $productA = Product::factory()->create();
    $productB = Product::factory()->create();
    $macDinhA = ProductVariant::factory()->for($productA)->create(['is_default' => true]);
    $moiB = ProductVariant::factory()->for($productB)->create(['is_default' => false]);

    app(SetDefaultProductVariant::class)->handle($moiB);

    expect($macDinhA->refresh()->is_default)->toBeTrue()
        ->and($moiB->refresh()->is_default)->toBeTrue();
});
