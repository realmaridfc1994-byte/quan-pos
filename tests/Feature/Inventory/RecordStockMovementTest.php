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

/**
 * ── NHẬP HÀNG BÙ CHO SỐ ĐÃ BÁN LÚC KHO ÂM (sửa 12/08, review Phase 3 Bước 10)
 *
 * Kho được phép xuống âm (bếp không bao giờ bị chặn báo món xong). Khi hàng về,
 * phần lô hàng dùng để trả nợ cho số đã bán KHÔNG được nằm lại trong kho — nó
 * đi ra bằng một dòng sổ cái `close_residual` riêng.
 */
it('nhập bù ĐÚNG BẰNG số đang âm thì không nổ lỗi, tồn về 0 và trị giá về 0', function () {
    ghiSoCai($this->action, $this->ingredient, $this->user, ['qtyDelta' => 10, 'knownCost' => 500_000]);
    ghiSoCai($this->action, $this->ingredient, $this->user, [
        'type' => StockMovementType::Sale, 'qtyDelta' => -12, 'knownCost' => null,
        'refType' => StockMovementRefType::OrderItem, 'refId' => 1,
    ]);

    $balance = StockBalance::query()->find($this->ingredient->id);
    expect($balance->qty)->toBe(-2)->and($balance->total_cost)->toBe(0);

    // Trước khi sửa, đúng dòng này ném StockLedgerInvariantViolatedException
    // và cả phiếu nhập bị quay lui — không nhận được hàng.
    $nhap = ghiSoCai($this->action, $this->ingredient, $this->user, ['qtyDelta' => 2, 'knownCost' => 120_000]);

    $balance = StockBalance::query()->find($this->ingredient->id);
    expect($balance->qty)->toBe(0)
        ->and($balance->total_cost)->toBe(0)
        ->and($nhap->cost_delta)->toBe(120_000);

    // Tiền không biến mất: có một dòng đứng tên nó, tra ngược được.
    $chotNo = StockMovement::query()->where('type', StockMovementType::CloseResidual)->sole();
    expect($chotNo->qty_delta)->toBe(0)
        ->and($chotNo->cost_delta)->toBe(-120_000)
        ->and($chotNo->reason)->toContain('đã bán lúc kho âm');
});

it('nhập bù NHIỀU HƠN số đang âm thì giá vốn trung bình đúng bằng đơn giá lô vừa nhập', function () {
    ghiSoCai($this->action, $this->ingredient, $this->user, ['qtyDelta' => 10, 'knownCost' => 500_000]);
    ghiSoCai($this->action, $this->ingredient, $this->user, [
        'type' => StockMovementType::Sale, 'qtyDelta' => -12, 'knownCost' => null,
        'refType' => StockMovementRefType::OrderItem, 'refId' => 1,
    ]);

    // Nhập 5 lon giá 300.000đ = 60.000đ/lon. Hai lon trả nợ, ba lon vào kho.
    ghiSoCai($this->action, $this->ingredient, $this->user, ['qtyDelta' => 5, 'knownCost' => 300_000]);

    $balance = StockBalance::query()->find($this->ingredient->id);
    expect($balance->qty)->toBe(3)
        // Trước khi sửa: 300.000đ cho 3 lon = 100.000đ/lon, gấp rưỡi giá thật.
        ->and($balance->total_cost)->toBe(180_000)
        ->and(intdiv($balance->total_cost, $balance->qty))->toBe(60_000);
});

it('nhập bù ÍT HƠN số đang âm thì tồn vẫn âm, trị giá vẫn 0', function () {
    ghiSoCai($this->action, $this->ingredient, $this->user, ['qtyDelta' => 10, 'knownCost' => 500_000]);
    ghiSoCai($this->action, $this->ingredient, $this->user, [
        'type' => StockMovementType::Sale, 'qtyDelta' => -20, 'knownCost' => null,
        'refType' => StockMovementRefType::OrderItem, 'refId' => 1,
    ]);

    ghiSoCai($this->action, $this->ingredient, $this->user, ['qtyDelta' => 4, 'knownCost' => 240_000]);

    $balance = StockBalance::query()->find($this->ingredient->id);
    expect($balance->qty)->toBe(-6)->and($balance->total_cost)->toBe(0);
});

it('nhập bù khi kho âm vẫn giữ đúng đẳng thức sổ cái = bảng tồn, không rơi đồng nào', function () {
    ghiSoCai($this->action, $this->ingredient, $this->user, ['qtyDelta' => 3, 'knownCost' => 100_000]);
    ghiSoCai($this->action, $this->ingredient, $this->user, [
        'type' => StockMovementType::Sale, 'qtyDelta' => -7, 'knownCost' => null,
        'refType' => StockMovementRefType::OrderItem, 'refId' => 1,
    ]);
    ghiSoCai($this->action, $this->ingredient, $this->user, ['qtyDelta' => 9, 'knownCost' => 123_457]);

    $balance = StockBalance::query()->find($this->ingredient->id);
    $tongQty = (int) StockMovement::query()->sum('qty_delta');
    $tongCost = (int) StockMovement::query()->sum('cost_delta');

    expect($balance->qty)->toBe($tongQty)
        ->and($balance->total_cost)->toBe($tongCost)
        ->and($balance->total_cost)->toBeGreaterThanOrEqual(0);
});

it('gửi lại phiếu nhập bù lần hai không sinh thêm dòng chốt nợ nào', function () {
    ghiSoCai($this->action, $this->ingredient, $this->user, ['qtyDelta' => 10, 'knownCost' => 500_000]);
    ghiSoCai($this->action, $this->ingredient, $this->user, [
        'type' => StockMovementType::Sale, 'qtyDelta' => -12, 'knownCost' => null,
        'refType' => StockMovementRefType::OrderItem, 'refId' => 1,
    ]);

    $uuid = (string) Str::uuid();
    ghiSoCai($this->action, $this->ingredient, $this->user, ['uuid' => $uuid, 'qtyDelta' => 5, 'knownCost' => 300_000]);
    ghiSoCai($this->action, $this->ingredient, $this->user, ['uuid' => $uuid, 'qtyDelta' => 5, 'knownCost' => 300_000]);

    expect(StockMovement::query()->where('type', StockMovementType::CloseResidual)->count())->toBe(1);

    $balance = StockBalance::query()->find($this->ingredient->id);
    expect($balance->qty)->toBe(3)->and($balance->total_cost)->toBe(180_000);
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
