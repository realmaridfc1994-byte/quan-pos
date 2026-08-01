<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Option;
use App\Domain\Catalog\Models\OptionGroup;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Staffing\Models\User;
use App\Filament\Resources\CategoryResource\Pages\ManageCategories;
use App\Filament\Resources\OptionGroupResource\Pages\ManageOptionGroups;
use App\Filament\Resources\OptionResource\Pages\ManageOptions;
use App\Filament\Resources\ProductResource\Pages\ManageProducts;
use App\Filament\Resources\ProductVariantResource\Pages\ManageProductVariants;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->owner()->create());
});

it('E4: trang Nhóm món không có nút Xoá', function () {
    $category = Category::factory()->create();

    Livewire::test(ManageCategories::class)
        ->assertTableActionDoesNotExist('delete', record: $category)
        ->assertTableBulkActionDoesNotExist('delete');
});

it('E4: trang Món không có nút Xoá', function () {
    $product = Product::factory()->create();

    Livewire::test(ManageProducts::class)
        ->assertTableActionDoesNotExist('delete', record: $product)
        ->assertTableBulkActionDoesNotExist('delete');
});

it('E4: trang Biến thể món không có nút Xoá', function () {
    $variant = ProductVariant::factory()->create();

    Livewire::test(ManageProductVariants::class)
        ->assertTableActionDoesNotExist('delete', record: $variant)
        ->assertTableBulkActionDoesNotExist('delete');
});

it('E4: trang Nhóm tùy chọn không có nút Xoá', function () {
    $group = OptionGroup::factory()->create();

    Livewire::test(ManageOptionGroups::class)
        ->assertTableActionDoesNotExist('delete', record: $group)
        ->assertTableBulkActionDoesNotExist('delete');
});

it('E4: trang Tùy chọn không có nút Xoá', function () {
    $option = Option::factory()->create();

    Livewire::test(ManageOptions::class)
        ->assertTableActionDoesNotExist('delete', record: $option)
        ->assertTableBulkActionDoesNotExist('delete');
});
