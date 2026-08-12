<?php

declare(strict_types=1);

use App\Domain\Inventory\Actions\RecordStockMovement;
use App\Domain\Inventory\DTO\RecordStockMovementData;
use App\Domain\Inventory\Enums\StockMovementRefType;
use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Inventory\Models\Ingredient;
use App\Domain\Inventory\Models\StockBalance;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Staffing\Models\User;
use App\Exceptions\DomainException;
use Illuminate\Database\QueryException;

beforeEach(function () {
    $this->ingredient = Ingredient::factory()->create();
    $this->user = User::factory()->owner()->create();
    $this->action = new RecordStockMovement;
});

/**
 * @param  array<string, mixed>  $override
 */
function ghiSoCai(RecordStockMovement $action, Ingredient $ingredient, User $user, array $override = []): StockMovement
{
    $data = new RecordStockMovementData(
        uuid: $override['uuid'] ?? (string) Str::uuid(),
        ingredientId: $override['ingredientId'] ?? $ingredient->id,
        type: $override['type'] ?? StockMovementType::Purchase,
        qtyDelta: $override['qtyDelta'] ?? 100,
        knownCost: array_key_exists('knownCost', $override) ? $override['knownCost'] : 2_000_000,
        refType: $override['refType'] ?? StockMovementRefType::Manual,
        refId: $override['refId'] ?? null,
        reason: $override['reason'] ?? null,
        approvedByUserId: $override['approvedByUserId'] ?? null,
        createdByUserId: $override['createdByUserId'] ?? $user->id,
        shiftId: $override['shiftId'] ?? null,
        occurredAt: $override['occurredAt'] ?? null,
    );

    return $action->handle($data);
}

it('nhập hàng (purchase) cộng thẳng qty và total_cost theo tiền thật, không làm tròn', function () {
    ghiSoCai($this->action, $this->ingredient, $this->user, [
        'type' => StockMovementType::Purchase, 'qtyDelta' => 100, 'knownCost' => 2_000_000,
    ]);
    ghiSoCai($this->action, $this->ingredient, $this->user, [
        'type' => StockMovementType::Purchase, 'qtyDelta' => 50, 'knownCost' => 1_200_000,
    ]);

    $balance = StockBalance::query()->find($this->ingredient->id);
    expect($balance->qty)->toBe(150)
        ->and($balance->total_cost)->toBe(3_200_000);

    expect(StockMovement::query()->where('ingredient_id', $this->ingredient->id)->count())->toBe(2);
});

it('bán món (sale) trừ kho theo giá vốn bình quân gia quyền, đúng ví dụ tài liệu', function () {
    ghiSoCai($this->action, $this->ingredient, $this->user, ['qtyDelta' => 100, 'knownCost' => 2_000_000]);
    ghiSoCai($this->action, $this->ingredient, $this->user, ['qtyDelta' => 50, 'knownCost' => 1_200_000]);

    $movement = ghiSoCai($this->action, $this->ingredient, $this->user, [
        'type' => StockMovementType::Sale, 'qtyDelta' => -30, 'knownCost' => null,
        'refType' => StockMovementRefType::OrderItem, 'refId' => 999,
    ]);

    expect($movement->qty_delta)->toBe(-30)
        ->and($movement->cost_delta)->toBe(-640_000)
        ->and($movement->qty_after)->toBe(120)
        ->and($movement->cost_after)->toBe(2_560_000)
        ->and($movement->has_cost)->toBeTrue();

    $balance = StockBalance::query()->find($this->ingredient->id);
    expect($balance->qty)->toBe(120)->and($balance->total_cost)->toBe(2_560_000);
});

it('hỏng vỡ (waste) trừ kho và giữ lại lý do', function () {
    ghiSoCai($this->action, $this->ingredient, $this->user, ['qtyDelta' => 100, 'knownCost' => 2_000_000]);

    $movement = ghiSoCai($this->action, $this->ingredient, $this->user, [
        'type' => StockMovementType::Waste, 'qtyDelta' => -5, 'knownCost' => null,
        'reason' => 'Vỡ 5 lon lúc dọn kho',
    ]);

    expect($movement->qty_delta)->toBe(-5)
        ->and($movement->reason)->toBe('Vỡ 5 lon lúc dọn kho');
});

it('waste không ghi lý do đủ dài thì bị database chặn (ck_stock_movements_waste_reason)', function () {
    ghiSoCai($this->action, $this->ingredient, $this->user, ['qtyDelta' => 100, 'knownCost' => 2_000_000]);

    ghiSoCai($this->action, $this->ingredient, $this->user, [
        'type' => StockMovementType::Waste, 'qtyDelta' => -5, 'knownCost' => null, 'reason' => 'Vỡ',
    ]);
})->throws(QueryException::class);

it('điều chỉnh tăng (adjust) dùng giá trung bình hiện tại, giá TB không đổi', function () {
    ghiSoCai($this->action, $this->ingredient, $this->user, ['qtyDelta' => 150, 'knownCost' => 3_200_000]);

    $movement = ghiSoCai($this->action, $this->ingredient, $this->user, [
        'type' => StockMovementType::Adjust, 'qtyDelta' => 10, 'knownCost' => null,
        'reason' => 'Điều chỉnh tăng sau khi đếm lại kho',
        'approvedByUserId' => $this->user->id,
    ]);

    expect($movement->has_cost)->toBeTrue();

    $balance = StockBalance::query()->find($this->ingredient->id);
    expect(intdiv($balance->total_cost, $balance->qty))->toBe(intdiv(3_200_000, 150));
});

it('điều chỉnh thiếu người duyệt hoặc lý do ngắn thì bị database chặn (ck_stock_movements_adjust)', function () {
    ghiSoCai($this->action, $this->ingredient, $this->user, ['qtyDelta' => 150, 'knownCost' => 3_200_000]);

    ghiSoCai($this->action, $this->ingredient, $this->user, [
        'type' => StockMovementType::Adjust, 'qtyDelta' => 10, 'knownCost' => null,
        'reason' => 'Điều chỉnh tăng sau khi đếm lại kho',
        'approvedByUserId' => null,
    ]);
})->throws(QueryException::class);

it('kiểm kê thừa (stocktake) lúc tồn dương giữ nguyên giá trung bình', function () {
    ghiSoCai($this->action, $this->ingredient, $this->user, ['qtyDelta' => 150, 'knownCost' => 3_200_000]);

    ghiSoCai($this->action, $this->ingredient, $this->user, [
        'type' => StockMovementType::Stocktake, 'qtyDelta' => 20, 'knownCost' => null,
        'refType' => StockMovementRefType::StockTakeItem, 'refId' => 1,
    ]);

    $balance = StockBalance::query()->find($this->ingredient->id);
    expect(intdiv($balance->total_cost, $balance->qty))->toBe(intdiv(3_200_000, 150));
});

it('kiểm kê thiếu (stocktake) trừ kho theo giá vốn bình quân', function () {
    ghiSoCai($this->action, $this->ingredient, $this->user, ['qtyDelta' => 100, 'knownCost' => 2_000_000]);

    $movement = ghiSoCai($this->action, $this->ingredient, $this->user, [
        'type' => StockMovementType::Stocktake, 'qtyDelta' => -10, 'knownCost' => null,
        'refType' => StockMovementRefType::StockTakeItem, 'refId' => 2,
    ]);

    expect($movement->cost_delta)->toBe(-200_000);
});

it('trả hàng nhà cung cấp (return) trừ kho theo giá trung bình, không truy giá lô gốc', function () {
    ghiSoCai($this->action, $this->ingredient, $this->user, ['qtyDelta' => 100, 'knownCost' => 2_000_000]);
    ghiSoCai($this->action, $this->ingredient, $this->user, ['qtyDelta' => 50, 'knownCost' => 1_200_000]);

    $movement = ghiSoCai($this->action, $this->ingredient, $this->user, [
        'type' => StockMovementType::Return, 'qtyDelta' => -10, 'knownCost' => null,
    ]);

    expect($movement->cost_delta)->toBe(-213_333);
});

it('purchase không ghi knownCost thì bị chặn với thông báo tiếng Việt', function () {
    try {
        ghiSoCai($this->action, $this->ingredient, $this->user, [
            'type' => StockMovementType::Purchase, 'qtyDelta' => 10, 'knownCost' => null,
        ]);
        $this->fail('Phải ném DomainException.');
    } catch (DomainException $e) {
        expect($e->getMessage())->toBe('Nhập hàng phải ghi rõ số tiền thật trả nhà cung cấp.');
    }

    expect(StockMovement::query()->count())->toBe(0);
});

it('ghi sổ cái xong thì bảng tồn last_movement_id trỏ đúng dòng vừa tạo', function () {
    $movement = ghiSoCai($this->action, $this->ingredient, $this->user, ['qtyDelta' => 100, 'knownCost' => 2_000_000]);

    $balance = StockBalance::query()->find($this->ingredient->id);
    expect($balance->last_movement_id)->toBe($movement->id);
});
