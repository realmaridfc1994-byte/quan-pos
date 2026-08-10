<?php

declare(strict_types=1);

use App\Domain\Inventory\Actions\CreatePurchase;
use App\Domain\Inventory\DTO\CreatePurchaseData;
use App\Domain\Inventory\DTO\PurchaseLineData;
use App\Domain\Inventory\Enums\PurchaseStatus;
use App\Domain\Inventory\Models\Ingredient;
use App\Domain\Inventory\Models\IngredientUnit;
use App\Domain\Inventory\Models\Purchase;
use App\Domain\Inventory\Models\Supplier;
use App\Domain\Staffing\Models\User;
use App\Exceptions\DomainException;

beforeEach(function () {
    $this->user = User::factory()->owner()->create();
    $this->supplier = Supplier::factory()->create();
    $this->action = new CreatePurchase;
});

it('tạo phiếu 2 dòng thì total_cost bằng tổng line_cost, qty_base tính đúng', function () {
    $biaThung = Ingredient::factory()->create();
    IngredientUnit::factory()->for($biaThung, 'ingredient')->create(['unit_name' => 'Thùng', 'factor' => 24]);

    $ga = Ingredient::factory()->create();
    IngredientUnit::factory()->for($ga, 'ingredient')->create(['unit_name' => 'Kg', 'factor' => 1000]);

    $purchase = $this->action->handle(new CreatePurchaseData(
        supplierId: $this->supplier->id,
        note: 'Nhập đầu tuần',
        invoiceNo: 'HD-001',
        lines: [
            new PurchaseLineData(ingredientId: $biaThung->id, unitName: 'Thùng', qtyInput: 5, unitCost: 300_000),
            new PurchaseLineData(ingredientId: $ga->id, unitName: 'Kg', qtyInput: 10, unitCost: 90_000),
        ],
        createdByUserId: $this->user->id,
    ));

    expect($purchase->status)->toBe(PurchaseStatus::Draft)
        ->and($purchase->total_cost)->toBe(5 * 300_000 + 10 * 90_000)
        ->and($purchase->code)->toStartWith('NH-'.$purchase->created_at->format('Ymd').'-')
        ->and($purchase->items)->toHaveCount(2);

    $dongBia = $purchase->items->firstWhere('ingredient_id', $biaThung->id);
    expect($dongBia->factor_snapshot)->toBe(24)
        ->and($dongBia->qty_base)->toBe(5 * 24)
        ->and($dongBia->line_cost)->toBe(5 * 300_000);

    $dongGa = $purchase->items->firstWhere('ingredient_id', $ga->id);
    expect($dongGa->qty_base)->toBe(10 * 1000)
        ->and($dongGa->line_cost)->toBe(10 * 90_000);
});

it('phiếu có 3 nguyên liệu vẫn tạo đúng, mỗi dòng một nguyên liệu khác nhau', function () {
    $nguyenLieu = Ingredient::factory()->count(3)->create();

    foreach ($nguyenLieu as $nl) {
        IngredientUnit::factory()->for($nl, 'ingredient')->create(['unit_name' => 'Thùng', 'factor' => 10]);
    }

    $lines = $nguyenLieu->map(fn (Ingredient $nl) => new PurchaseLineData(
        ingredientId: $nl->id, unitName: 'Thùng', qtyInput: 2, unitCost: 100_000,
    ))->all();

    $purchase = $this->action->handle(new CreatePurchaseData(
        supplierId: $this->supplier->id,
        note: null,
        invoiceNo: null,
        lines: $lines,
        createdByUserId: $this->user->id,
    ));

    expect($purchase->items)->toHaveCount(3)
        ->and($purchase->total_cost)->toBe(3 * 2 * 100_000);
});

it('đơn vị nhập không tồn tại trong ingredient_units thì bị chặn với thông báo tiếng Việt', function () {
    $nl = Ingredient::factory()->create(['name' => 'Tôm sú']);

    try {
        $this->action->handle(new CreatePurchaseData(
            supplierId: $this->supplier->id,
            note: null,
            invoiceNo: null,
            lines: [new PurchaseLineData(ingredientId: $nl->id, unitName: 'Thùng', qtyInput: 1, unitCost: 10_000)],
            createdByUserId: $this->user->id,
        ));
        $this->fail('Phải ném DomainException.');
    } catch (DomainException $e) {
        expect($e->getMessage())->toContain('Tôm sú')->toContain('Thùng');
    }
});

it('phiếu có hai dòng cùng một nguyên liệu thì bị chặn', function () {
    $nl = Ingredient::factory()->create();
    IngredientUnit::factory()->for($nl, 'ingredient')->create(['unit_name' => 'Thùng', 'factor' => 10]);

    $this->action->handle(new CreatePurchaseData(
        supplierId: $this->supplier->id,
        note: null,
        invoiceNo: null,
        lines: [
            new PurchaseLineData(ingredientId: $nl->id, unitName: 'Thùng', qtyInput: 1, unitCost: 10_000),
            new PurchaseLineData(ingredientId: $nl->id, unitName: 'Thùng', qtyInput: 2, unitCost: 10_000),
        ],
        createdByUserId: $this->user->id,
    ));
})->throws(DomainException::class);

it('đổi factor trong ingredient_units SAU KHI tạo phiếu không làm đổi qty_base của phiếu cũ', function () {
    $nl = Ingredient::factory()->create();
    $donVi = IngredientUnit::factory()->for($nl, 'ingredient')->create(['unit_name' => 'Thùng', 'factor' => 24]);

    $purchase = $this->action->handle(new CreatePurchaseData(
        supplierId: $this->supplier->id,
        note: null,
        invoiceNo: null,
        lines: [new PurchaseLineData(ingredientId: $nl->id, unitName: 'Thùng', qtyInput: 5, unitCost: 100_000)],
        createdByUserId: $this->user->id,
    ));

    expect($purchase->items->first()->qty_base)->toBe(5 * 24);

    $donVi->update(['factor' => 30]);

    $dongCu = $purchase->items->first()->fresh();
    expect($dongCu->factor_snapshot)->toBe(24)
        ->and($dongCu->qty_base)->toBe(5 * 24);
});

it('staff tạo phiếu nhập bị chặn ở tầng quyền (policy)', function () {
    $staff = User::factory()->staff()->create();

    expect($staff->can('create', Purchase::class))->toBeFalse();
});

it('phiếu không có dòng nào thì bị chặn', function () {
    $this->action->handle(new CreatePurchaseData(
        supplierId: $this->supplier->id,
        note: null,
        invoiceNo: null,
        lines: [],
        createdByUserId: $this->user->id,
    ));
})->throws(DomainException::class);
