<?php

declare(strict_types=1);

use App\Domain\Inventory\Actions\ToggleSupplierActive;
use App\Domain\Inventory\Models\Supplier;

it('bật lại một nhà cung cấp đã ngừng dùng', function () {
    $supplier = Supplier::factory()->inactive()->create();

    app(ToggleSupplierActive::class)->handle($supplier);

    expect($supplier->refresh()->is_active)->toBeTrue();
});

it('ngừng dùng một nhà cung cấp đang hoạt động', function () {
    $supplier = Supplier::factory()->create();

    app(ToggleSupplierActive::class)->handle($supplier);

    expect($supplier->refresh()->is_active)->toBeFalse();
});
