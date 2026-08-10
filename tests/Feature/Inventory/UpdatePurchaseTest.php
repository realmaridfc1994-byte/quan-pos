<?php

declare(strict_types=1);

use App\Domain\Inventory\Actions\CreatePurchase;
use App\Domain\Inventory\Actions\UpdatePurchase;
use App\Domain\Inventory\DTO\CreatePurchaseData;
use App\Domain\Inventory\DTO\PurchaseLineData;
use App\Domain\Inventory\DTO\UpdatePurchaseData;
use App\Domain\Inventory\Enums\PurchaseStatus;
use App\Domain\Inventory\Models\Ingredient;
use App\Domain\Inventory\Models\IngredientUnit;
use App\Domain\Inventory\Models\Supplier;
use App\Domain\Staffing\Models\User;
use App\Exceptions\DomainException;

beforeEach(function () {
    $this->user = User::factory()->owner()->create();
    $this->supplier = Supplier::factory()->create();
    $this->ingredient = Ingredient::factory()->create();
    IngredientUnit::factory()->for($this->ingredient, 'ingredient')->create(['unit_name' => 'Thùng', 'factor' => 24]);

    $this->purchase = (new CreatePurchase)->handle(new CreatePurchaseData(
        supplierId: $this->supplier->id,
        note: 'Bản gốc',
        invoiceNo: null,
        lines: [new PurchaseLineData(ingredientId: $this->ingredient->id, unitName: 'Thùng', qtyInput: 5, unitCost: 300_000)],
        createdByUserId: $this->user->id,
    ));

    $this->action = new UpdatePurchase;
});

it('sửa phiếu draft cập nhật lại dòng và tổng tiền', function () {
    $nlThuHai = Ingredient::factory()->create();
    IngredientUnit::factory()->for($nlThuHai, 'ingredient')->create(['unit_name' => 'Kg', 'factor' => 1000]);

    $updated = $this->action->handle(new UpdatePurchaseData(
        purchaseId: $this->purchase->id,
        supplierId: $this->supplier->id,
        note: 'Đã sửa',
        invoiceNo: 'HD-999',
        lines: [
            new PurchaseLineData(ingredientId: $this->ingredient->id, unitName: 'Thùng', qtyInput: 10, unitCost: 300_000),
            new PurchaseLineData(ingredientId: $nlThuHai->id, unitName: 'Kg', qtyInput: 2, unitCost: 90_000),
        ],
    ));

    expect($updated->note)->toBe('Đã sửa')
        ->and($updated->invoice_no)->toBe('HD-999')
        ->and($updated->items)->toHaveCount(2)
        ->and($updated->total_cost)->toBe(10 * 300_000 + 2 * 90_000);
});

it('sửa phiếu đã received thì bị chặn', function () {
    $this->purchase->update(['status' => PurchaseStatus::Received, 'received_at' => now(), 'received_by_user_id' => $this->user->id]);

    $this->action->handle(new UpdatePurchaseData(
        purchaseId: $this->purchase->id,
        supplierId: $this->supplier->id,
        note: null,
        invoiceNo: null,
        lines: [new PurchaseLineData(ingredientId: $this->ingredient->id, unitName: 'Thùng', qtyInput: 1, unitCost: 100_000)],
    ));
})->throws(DomainException::class);

it('sửa phiếu đã cancelled thì bị chặn', function () {
    $this->purchase->update(['status' => PurchaseStatus::Cancelled, 'cancel_reason' => 'test']);

    $this->action->handle(new UpdatePurchaseData(
        purchaseId: $this->purchase->id,
        supplierId: $this->supplier->id,
        note: null,
        invoiceNo: null,
        lines: [new PurchaseLineData(ingredientId: $this->ingredient->id, unitName: 'Thùng', qtyInput: 1, unitCost: 100_000)],
    ));
})->throws(DomainException::class);
