<?php

declare(strict_types=1);

use App\Domain\Inventory\Enums\PurchaseStatus;
use App\Domain\Inventory\Models\Ingredient;
use App\Domain\Inventory\Models\IngredientUnit;
use App\Domain\Inventory\Models\Purchase;
use App\Domain\Inventory\Models\PurchaseItem;
use App\Domain\Inventory\Models\StockBalance;
use App\Domain\Inventory\Models\Supplier;
use App\Domain\Staffing\Models\User;
use App\Filament\Resources\PurchaseResource\Pages\ManagePurchases;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->owner()->create());
});

it('trang Nhập hàng tải được, không có nút Xoá', function () {
    $purchase = Purchase::factory()->create();

    Livewire::test(ManagePurchases::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$purchase])
        ->assertTableActionDoesNotExist('delete', record: $purchase)
        ->assertTableBulkActionDoesNotExist('delete');
});

it('phiếu draft có nút Sửa, Nhận hàng, Huỷ', function () {
    $purchase = Purchase::factory()->create();

    Livewire::test(ManagePurchases::class)
        ->assertTableActionExists('edit', record: $purchase)
        ->assertTableActionExists('receive', record: $purchase)
        ->assertTableActionExists('cancel', record: $purchase);
});

it('phiếu đã received không có nút Sửa và nút Huỷ, chỉ còn Xem', function () {
    $purchase = Purchase::factory()->received()->create();

    Livewire::test(ManagePurchases::class)
        ->assertTableActionExists('view', record: $purchase)
        ->assertTableActionDoesNotExist('edit', record: $purchase)
        ->assertTableActionDoesNotExist('receive', record: $purchase)
        ->assertTableActionDoesNotExist('cancel', record: $purchase);
});

it('bấm Nhận hàng vào kho trên trang Filament gọi đúng ReceivePurchase, đổi trạng thái', function () {
    $supplier = Supplier::factory()->create();
    $nl = Ingredient::factory()->create();
    IngredientUnit::factory()->for($nl, 'ingredient')->create(['unit_name' => 'Thùng', 'factor' => 24]);

    $purchase = Purchase::factory()->for($supplier, 'supplier')->create();
    PurchaseItem::factory()->for($purchase)->for($nl, 'ingredient')->create([
        'unit_name' => 'Thùng',
        'qty_input' => 2,
        'factor_snapshot' => 24,
        'unit_cost' => 100_000,
    ]);

    Livewire::test(ManagePurchases::class)
        ->callTableAction('receive', $purchase);

    expect($purchase->fresh()->status)->toBe(PurchaseStatus::Received);

    $ton = StockBalance::query()->find($nl->id);
    expect($ton->qty)->toBe(2 * 24);
});

it('bấm Huỷ phiếu trên trang Filament có form nhập lý do, không lỗi', function () {
    $purchase = Purchase::factory()->create();

    Livewire::test(ManagePurchases::class)
        ->mountTableAction('cancel', $purchase)
        ->assertTableActionMounted('cancel')
        ->setTableActionData(['reason' => 'Nhà cung cấp báo hết hàng'])
        ->callMountedTableAction();

    expect($purchase->fresh()->status)->toBe(PurchaseStatus::Cancelled);
});

it('mở form Sửa phiếu draft có sẵn dòng thì hiện được Repeater, không lỗi', function () {
    $supplier = Supplier::factory()->create();
    $nl = Ingredient::factory()->create();
    IngredientUnit::factory()->for($nl, 'ingredient')->create(['unit_name' => 'Thùng', 'factor' => 24]);

    $purchase = Purchase::factory()->for($supplier, 'supplier')->create();
    PurchaseItem::factory()->for($purchase)->for($nl, 'ingredient')->create([
        'unit_name' => 'Thùng',
        'qty_input' => 2,
        'factor_snapshot' => 24,
        'unit_cost' => 100_000,
    ]);

    Livewire::test(ManagePurchases::class)
        ->mountTableAction('edit', $purchase)
        ->assertTableActionMounted('edit');
});
