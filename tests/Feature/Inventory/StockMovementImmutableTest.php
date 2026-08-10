<?php

declare(strict_types=1);

use App\Domain\Inventory\Actions\RecordStockMovement;
use App\Domain\Inventory\Actions\WriteOffStock;
use App\Domain\Inventory\DTO\RecordStockMovementData;
use App\Domain\Inventory\DTO\WriteOffStockData;
use App\Domain\Inventory\Enums\StockMovementRefType;
use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Inventory\Enums\WasteReasonCategory;
use App\Domain\Inventory\Models\Ingredient;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Staffing\Models\User;
use App\Exceptions\StockMovementImmutableException;
use App\Filament\Resources\WasteRecordResource\Pages\ManageWasteRecords;
use Livewire\Livewire;

beforeEach(function () {
    $this->ingredient = Ingredient::factory()->create();
    $this->movement = app(RecordStockMovement::class)->handle(new RecordStockMovementData(
        ingredientId: $this->ingredient->id,
        type: StockMovementType::Purchase,
        qtyDelta: 100,
        knownCost: 100_000,
        refType: StockMovementRefType::Manual,
        refId: null,
        reason: null,
        approvedByUserId: null,
        createdByUserId: User::factory()->owner()->create()->id,
        shiftId: null,
    ));
});

it('không ai xoá được dòng sổ cái nào — gọi delete() trên instance bị chặn', function () {
    expect(fn () => $this->movement->delete())->toThrow(StockMovementImmutableException::class);

    expect(StockMovement::query()->find($this->movement->id))->not->toBeNull();
});

it('không ai xoá được dòng sổ cái nào — gọi forceDelete() trên instance bị chặn', function () {
    expect(fn () => $this->movement->forceDelete())->toThrow(StockMovementImmutableException::class);

    expect(StockMovement::query()->find($this->movement->id))->not->toBeNull();
});

it('không ai xoá được dòng sổ cái nào — xoá hàng loạt qua query builder cũng bị chặn', function () {
    expect(fn () => StockMovement::query()->where('id', $this->movement->id)->delete())
        ->toThrow(StockMovementImmutableException::class);

    expect(StockMovement::query()->find($this->movement->id))->not->toBeNull();
});

it('màn hình Hao hụt không có nút Xoá', function () {
    $chuQuan = User::factory()->owner()->create();
    $this->actingAs($chuQuan);

    $dongHaoHut = app(WriteOffStock::class)->handle(new WriteOffStockData(
        ingredientId: $this->ingredient->id,
        category: WasteReasonCategory::Broken,
        detail: 'Vỡ khi bưng bê',
        qty: 5,
        createdByUserId: $chuQuan->id,
        shiftId: null,
    ));

    Livewire::test(ManageWasteRecords::class)
        ->assertTableActionDoesNotExist('delete', record: $dongHaoHut)
        ->assertTableBulkActionDoesNotExist('delete');
});
