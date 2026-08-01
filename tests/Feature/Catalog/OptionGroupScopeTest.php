<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\OptionGroup;
use App\Domain\Catalog\Models\Product;
use App\Domain\Staffing\Models\User;
use App\Filament\Resources\OptionGroupResource\Pages\ManageOptionGroups;
use Illuminate\Database\QueryException;
use Livewire\Livewire;

beforeEach(function () {
    $this->owner = User::factory()->owner()->create();
    $this->actingAs($this->owner);
});

it('E5: tạo nhóm tùy chọn qua form Filament với phạm vi "một món" chỉ lưu product_id', function () {
    $product = Product::factory()->create();

    Livewire::test(ManageOptionGroups::class)
        ->callAction('create', data: [
            'name' => 'Độ cay',
            'scope' => 'product',
            'product_id' => $product->id,
            'min_select' => 0,
            'max_select' => 1,
            'sort_order' => 0,
        ])
        ->assertHasNoActionErrors();

    $nhom = OptionGroup::query()->where('name', 'Độ cay')->sole();

    expect($nhom->product_id)->toBe($product->id)
        ->and($nhom->category_id)->toBeNull();
});

it('E5: tạo nhóm tùy chọn qua form Filament với phạm vi "cả nhóm món" chỉ lưu category_id', function () {
    $category = Category::factory()->create();

    Livewire::test(ManageOptionGroups::class)
        ->callAction('create', data: [
            'name' => 'Đá',
            'scope' => 'category',
            'category_id' => $category->id,
            'min_select' => 0,
            'max_select' => 1,
            'sort_order' => 0,
        ])
        ->assertHasNoActionErrors();

    $nhom = OptionGroup::query()->where('name', 'Đá')->sole();

    expect($nhom->category_id)->toBe($category->id)
        ->and($nhom->product_id)->toBeNull();
});

it('E5: không chọn phạm vi nào thì form báo lỗi, không tạo được', function () {
    Livewire::test(ManageOptionGroups::class)
        ->callAction('create', data: [
            'name' => 'Thiếu phạm vi',
            'min_select' => 0,
            'max_select' => 1,
            'sort_order' => 0,
        ])
        ->assertHasActionErrors(['scope']);

    expect(OptionGroup::query()->where('name', 'Thiếu phạm vi')->exists())->toBeFalse();
});

it('E5: sửa một nhóm từ "cả nhóm món" sang "một món" thì form hiểu đúng phạm vi hiện tại', function () {
    $category = Category::factory()->create();
    $product = Product::factory()->create();
    $nhom = OptionGroup::factory()->forCategory($category)->create();

    Livewire::test(ManageOptionGroups::class)
        ->mountTableAction('edit', $nhom)
        ->assertTableActionDataSet(['scope' => 'category', 'category_id' => $category->id])
        ->setTableActionData([
            'scope' => 'product',
            'product_id' => $product->id,
        ])
        ->callMountedTableAction()
        ->assertHasNoTableActionErrors();

    expect($nhom->refresh()->product_id)->toBe($product->id)
        ->and($nhom->category_id)->toBeNull();
});

it('E5 (chốt DB cuối cùng): ck_option_groups_scope chặn cả product_id lẫn category_id cùng có giá trị', function () {
    $product = Product::factory()->create();
    $category = Category::factory()->create();

    expect(fn () => OptionGroup::query()->create([
        'name' => 'Sai cả hai',
        'product_id' => $product->id,
        'category_id' => $category->id,
        'is_required' => false,
        'min_select' => 0,
        'max_select' => 1,
        'sort_order' => 0,
        'is_active' => true,
    ]))->toThrow(QueryException::class);
});

it('E5 (chốt DB cuối cùng): ck_option_groups_scope chặn cả product_id lẫn category_id đều rỗng', function () {
    expect(fn () => OptionGroup::query()->create([
        'name' => 'Trống cả hai',
        'product_id' => null,
        'category_id' => null,
        'is_required' => false,
        'min_select' => 0,
        'max_select' => 1,
        'sort_order' => 0,
        'is_active' => true,
    ]))->toThrow(QueryException::class);
});
