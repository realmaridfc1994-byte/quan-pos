<?php

declare(strict_types=1);

use App\Domain\Inventory\Actions\CreatePurchase;
use App\Domain\Inventory\Actions\ReceivePurchase;
use App\Domain\Inventory\Actions\RecordStockMovement;
use App\Domain\Inventory\DTO\CreatePurchaseData;
use App\Domain\Inventory\DTO\PurchaseLineData;
use App\Domain\Inventory\DTO\ReceivePurchaseData;
use App\Domain\Inventory\Enums\PurchaseStatus;
use App\Domain\Inventory\Models\Ingredient;
use App\Domain\Inventory\Models\IngredientUnit;
use App\Domain\Inventory\Models\Purchase;
use App\Domain\Inventory\Models\StockBalance;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Inventory\Models\Supplier;
use App\Domain\Staffing\Models\User;
use App\Exceptions\DomainException;

beforeEach(function () {
    $this->user = User::factory()->owner()->create();
    $this->supplier = Supplier::factory()->create();
    $this->createPurchase = new CreatePurchase;
    $this->receivePurchase = new ReceivePurchase(new RecordStockMovement);
});

function taoPhieuMotDong(User $user, Supplier $supplier, Ingredient $ingredient, string $unitName, int $qtyInput, int $unitCost): Purchase
{
    return app(CreatePurchase::class)->handle(new CreatePurchaseData(
        supplierId: $supplier->id,
        note: null,
        invoiceNo: null,
        lines: [new PurchaseLineData(ingredientId: $ingredient->id, unitName: $unitName, qtyInput: $qtyInput, unitCost: $unitCost)],
        createdByUserId: $user->id,
    ));
}

it('nhận phiếu tăng đúng qty và total_cost trong stock_balances, sinh một dòng sổ cái mỗi dòng phiếu', function () {
    $bia = Ingredient::factory()->create();
    IngredientUnit::factory()->for($bia, 'ingredient')->create(['unit_name' => 'Thùng', 'factor' => 24]);

    $ga = Ingredient::factory()->create();
    IngredientUnit::factory()->for($ga, 'ingredient')->create(['unit_name' => 'Kg', 'factor' => 1]);

    $purchase = $this->createPurchase->handle(new CreatePurchaseData(
        supplierId: $this->supplier->id,
        note: null,
        invoiceNo: null,
        lines: [
            new PurchaseLineData(ingredientId: $bia->id, unitName: 'Thùng', qtyInput: 5, unitCost: 300_000),
            new PurchaseLineData(ingredientId: $ga->id, unitName: 'Kg', qtyInput: 10, unitCost: 90_000),
        ],
        createdByUserId: $this->user->id,
    ));

    $received = $this->receivePurchase->handle(new ReceivePurchaseData(purchaseId: $purchase->id, receivedByUserId: $this->user->id));

    expect($received->status)->toBe(PurchaseStatus::Received)
        ->and($received->received_at)->not->toBeNull()
        ->and($received->received_by_user_id)->toBe($this->user->id);

    $tonBia = StockBalance::query()->find($bia->id);
    expect($tonBia->qty)->toBe(5 * 24)->and($tonBia->total_cost)->toBe(5 * 300_000);

    $tonGa = StockBalance::query()->find($ga->id);
    expect($tonGa->qty)->toBe(10)->and($tonGa->total_cost)->toBe(10 * 90_000);

    expect(StockMovement::query()->where('ref_type', 'purchase_item')->count())->toBe(2);
});

it('đúng ví dụ tài liệu: nhận 100 lon giá 2.000.000 rồi 50 lon giá 1.200.000 → qty=150, total_cost=3.200.000', function () {
    $bia = Ingredient::factory()->create();
    IngredientUnit::factory()->for($bia, 'ingredient')->create(['unit_name' => 'Lon', 'factor' => 1, 'is_purchase_default' => true]);

    // "100 lon giá 2.000.000" = tổng tiền lô đó 2.000.000 đ, tức đơn giá 20.000 đ/lon.
    $phieu1 = taoPhieuMotDong($this->user, $this->supplier, $bia, 'Lon', 100, 20_000);
    $this->receivePurchase->handle(new ReceivePurchaseData(purchaseId: $phieu1->id, receivedByUserId: $this->user->id));

    $phieu2 = taoPhieuMotDong($this->user, $this->supplier, $bia, 'Lon', 50, 24_000);
    $this->receivePurchase->handle(new ReceivePurchaseData(purchaseId: $phieu2->id, receivedByUserId: $this->user->id));

    $ton = StockBalance::query()->find($bia->id);
    expect($ton->qty)->toBe(150)->and($ton->total_cost)->toBe(3_200_000);
});

it('nhận cùng một phiếu hai lần thì lần hai bị chặn, không sinh thêm sổ cái, tồn không đổi', function () {
    $bia = Ingredient::factory()->create();
    IngredientUnit::factory()->for($bia, 'ingredient')->create(['unit_name' => 'Lon', 'factor' => 1]);

    $purchase = taoPhieuMotDong($this->user, $this->supplier, $bia, 'Lon', 20, 400_000);

    $this->receivePurchase->handle(new ReceivePurchaseData(purchaseId: $purchase->id, receivedByUserId: $this->user->id));

    $tonSauLan1 = StockBalance::query()->find($bia->id);
    $soDongSauLan1 = StockMovement::query()->count();

    try {
        $this->receivePurchase->handle(new ReceivePurchaseData(purchaseId: $purchase->id, receivedByUserId: $this->user->id));
        $this->fail('Phải ném DomainException.');
    } catch (DomainException $e) {
        expect($e->getMessage())->toContain('đã nhận hàng');
    }

    expect(StockMovement::query()->count())->toBe($soDongSauLan1);

    $tonSauLan2 = StockBalance::query()->find($bia->id);
    expect($tonSauLan2->qty)->toBe($tonSauLan1->qty)
        ->and($tonSauLan2->total_cost)->toBe($tonSauLan1->total_cost);
});

it('phiếu có 3 nguyên liệu thì sổ cái được ghi theo thứ tự ingredient_id tăng dần', function () {
    $nl1 = Ingredient::factory()->create();
    $nl2 = Ingredient::factory()->create();
    $nl3 = Ingredient::factory()->create();

    foreach ([$nl1, $nl2, $nl3] as $nl) {
        IngredientUnit::factory()->for($nl, 'ingredient')->create(['unit_name' => 'Thùng', 'factor' => 5]);
    }

    // Cố tình đưa dòng vào KHÔNG theo thứ tự id tăng dần.
    $purchase = $this->createPurchase->handle(new CreatePurchaseData(
        supplierId: $this->supplier->id,
        note: null,
        invoiceNo: null,
        lines: [
            new PurchaseLineData(ingredientId: $nl3->id, unitName: 'Thùng', qtyInput: 1, unitCost: 10_000),
            new PurchaseLineData(ingredientId: $nl1->id, unitName: 'Thùng', qtyInput: 1, unitCost: 10_000),
            new PurchaseLineData(ingredientId: $nl2->id, unitName: 'Thùng', qtyInput: 1, unitCost: 10_000),
        ],
        createdByUserId: $this->user->id,
    ));

    $this->receivePurchase->handle(new ReceivePurchaseData(purchaseId: $purchase->id, receivedByUserId: $this->user->id));

    $thuTuGhi = StockMovement::query()
        ->where('ref_type', 'purchase_item')
        ->orderBy('id')
        ->pluck('ingredient_id')
        ->all();

    expect($thuTuGhi)->toBe([$nl1->id, $nl2->id, $nl3->id]);
});

it('nhận phiếu đã cancelled thì bị chặn', function () {
    $bia = Ingredient::factory()->create();
    IngredientUnit::factory()->for($bia, 'ingredient')->create(['unit_name' => 'Lon', 'factor' => 1]);

    $purchase = taoPhieuMotDong($this->user, $this->supplier, $bia, 'Lon', 10, 100_000);
    $purchase->update(['status' => PurchaseStatus::Cancelled, 'cancel_reason' => 'test']);

    $this->receivePurchase->handle(new ReceivePurchaseData(purchaseId: $purchase->id, receivedByUserId: $this->user->id));
})->throws(DomainException::class);
