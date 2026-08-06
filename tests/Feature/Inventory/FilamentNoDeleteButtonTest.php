<?php

declare(strict_types=1);

use App\Domain\Inventory\Models\Ingredient;
use App\Domain\Inventory\Models\Supplier;
use App\Domain\Staffing\Models\User;
use App\Filament\Resources\IngredientResource\Pages\ManageIngredients;
use App\Filament\Resources\SupplierResource\Pages\ManageSuppliers;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->owner()->create());
});

it('trang Nhà cung cấp không có nút Xoá', function () {
    $supplier = Supplier::factory()->create();

    Livewire::test(ManageSuppliers::class)
        ->assertTableActionDoesNotExist('delete', record: $supplier)
        ->assertTableBulkActionDoesNotExist('delete');
});

it('trang Nguyên liệu không có nút Xoá', function () {
    $ingredient = Ingredient::factory()->create();

    Livewire::test(ManageIngredients::class)
        ->assertTableActionDoesNotExist('delete', record: $ingredient)
        ->assertTableBulkActionDoesNotExist('delete');
});
