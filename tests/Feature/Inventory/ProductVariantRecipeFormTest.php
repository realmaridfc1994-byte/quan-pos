<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Inventory\Models\Ingredient;
use App\Domain\Staffing\Models\User;
use App\Filament\Resources\ProductVariantResource\Pages\ManageProductVariants;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->owner()->create());
});

it('trang Biến thể món tải được, kèm cột trừ kho và giá vốn ước tính', function () {
    $bienThe = ProductVariant::factory()->create(['deducts_stock' => true]);
    Ingredient::factory()->create();

    Livewire::test(ManageProductVariants::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$bienThe])
        ->assertTableActionDoesNotExist('delete', record: $bienThe);
});

it('mở form sửa biến thể có định lượng thì hiện được form Repeater nguyên liệu, không lỗi', function () {
    $bienThe = ProductVariant::factory()->create(['deducts_stock' => true]);
    Ingredient::factory()->create();

    Livewire::test(ManageProductVariants::class)
        ->mountTableAction('edit', $bienThe)
        ->assertTableActionMounted('edit');
});
