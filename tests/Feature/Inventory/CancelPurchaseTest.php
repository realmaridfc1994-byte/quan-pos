<?php

declare(strict_types=1);

use App\Domain\Inventory\Actions\CancelPurchase;
use App\Domain\Inventory\Actions\CreatePurchase;
use App\Domain\Inventory\Actions\ReceivePurchase;
use App\Domain\Inventory\Actions\RecordStockMovement;
use App\Domain\Inventory\DTO\CancelPurchaseData;
use App\Domain\Inventory\DTO\CreatePurchaseData;
use App\Domain\Inventory\DTO\PurchaseLineData;
use App\Domain\Inventory\DTO\ReceivePurchaseData;
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
        note: null,
        invoiceNo: null,
        lines: [new PurchaseLineData(ingredientId: $this->ingredient->id, unitName: 'Thùng', qtyInput: 5, unitCost: 300_000)],
        createdByUserId: $this->user->id,
    ));

    $this->action = new CancelPurchase;
});

it('huỷ phiếu draft có lý do thì thành công', function () {
    $cancelled = $this->action->handle(new CancelPurchaseData(purchaseId: $this->purchase->id, reason: 'Nhà cung cấp báo hết hàng'));

    expect($cancelled->status)->toBe(PurchaseStatus::Cancelled)
        ->and($cancelled->cancel_reason)->toBe('Nhà cung cấp báo hết hàng');
});

it('huỷ phiếu draft không lý do thì bị chặn', function () {
    $this->action->handle(new CancelPurchaseData(purchaseId: $this->purchase->id, reason: ''));
})->throws(DomainException::class);

it('huỷ phiếu draft mà lý do chỉ toàn khoảng trắng thì bị chặn', function () {
    $this->action->handle(new CancelPurchaseData(purchaseId: $this->purchase->id, reason: '   '));
})->throws(DomainException::class);

it('huỷ phiếu đã received thì bị chặn — hàng đã vào kho rồi', function () {
    (new ReceivePurchase(new RecordStockMovement))
        ->handle(new ReceivePurchaseData(purchaseId: $this->purchase->id, receivedByUserId: $this->user->id));

    try {
        $this->action->handle(new CancelPurchaseData(purchaseId: $this->purchase->id, reason: 'Muốn huỷ dù đã nhận'));
        $this->fail('Phải ném DomainException.');
    } catch (DomainException $e) {
        expect($e->getMessage())->toContain('đã nhận hàng');
    }

    expect($this->purchase->fresh()->status)->toBe(PurchaseStatus::Received);
});

it('huỷ phiếu đã cancelled thì báo đã huỷ rồi', function () {
    $this->action->handle(new CancelPurchaseData(purchaseId: $this->purchase->id, reason: 'Lần đầu'));

    $this->action->handle(new CancelPurchaseData(purchaseId: $this->purchase->id, reason: 'Lần hai'));
})->throws(DomainException::class);
