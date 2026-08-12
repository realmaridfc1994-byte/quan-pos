<?php

declare(strict_types=1);

use App\Domain\Inventory\Actions\AcknowledgeOrphanMovement;
use App\Domain\Inventory\Actions\ReconcileStockLedger;
use App\Domain\Inventory\Actions\RecordStockMovement;
use App\Domain\Inventory\DTO\AcknowledgeOrphanMovementData;
use App\Domain\Inventory\DTO\RecordStockMovementData;
use App\Domain\Inventory\Enums\StockMovementRefType;
use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Inventory\Models\Ingredient;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Inventory\Models\StockReconciliationNote;
use App\Domain\Ordering\Enums\OrderItemStatus;
use App\Domain\Ordering\Models\Order;
use App\Domain\Ordering\Models\OrderItem;
use App\Domain\Staffing\Models\User;
use App\Exceptions\DomainException;
use Illuminate\Database\QueryException;

/**
 * Đường thoát cho dòng sổ cái mồ côi — review Phase 3 mục 8.2-K.
 *
 * BUG CŨ: sổ cái không xoá được, nên một dòng mồ côi làm lệnh đối soát báo đỏ
 * mãi mãi và không có công cụ nào dọn. Người ta quen với màu đỏ rồi bỏ qua cả
 * lệch thật.
 */
beforeEach(function () {
    $this->chuQuan = User::factory()->owner()->create();
});

/** Dựng đúng tình huống mồ côi: có dòng sổ cái, dòng món chưa/không còn served. */
function dungDongMoCoi(User $nguoi): StockMovement
{
    $ga = Ingredient::factory()->create();

    app(RecordStockMovement::class)->handle(new RecordStockMovementData(
        uuid: (string) Str::uuid(),
        ingredientId: $ga->id,
        type: StockMovementType::Purchase,
        qtyDelta: 100,
        knownCost: 500_000,
        refType: StockMovementRefType::Manual,
        refId: null,
        reason: null,
        approvedByUserId: null,
        createdByUserId: $nguoi->id,
        shiftId: null,
    ));

    $item = OrderItem::factory()->for(Order::factory()->create(), 'order')->create([
        'status' => OrderItemStatus::Ordered,
        'served_at' => null,
    ]);

    return app(RecordStockMovement::class)->handle(new RecordStockMovementData(
        uuid: (string) Str::uuid(),
        ingredientId: $ga->id,
        type: StockMovementType::Sale,
        qtyDelta: -5,
        knownCost: null,
        refType: StockMovementRefType::OrderItem,
        refId: $item->id,
        reason: null,
        approvedByUserId: null,
        createdByUserId: $nguoi->id,
        shiftId: null,
    ));
}

function ghiChuMoCoi(StockMovement $movement, User $nguoi, array $doi = []): StockReconciliationNote
{
    return app(AcknowledgeOrphanMovement::class)->handle(new AcknowledgeOrphanMovementData(
        stockMovementId: $doi['stockMovementId'] ?? $movement->id,
        note: $doi['note'] ?? 'Đã đối chiếu với phiếu bếp đêm 11/08',
        reason: $doi['reason'] ?? 'Món bị huỷ sau khi đã trừ kho, nguyên liệu đã dùng thật',
        acknowledgedByUserId: $doi['acknowledgedByUserId'] ?? $nguoi->id,
    ));
}

it('BUG CŨ: dòng mồ côi chưa ai xem thì đối soát báo đỏ và không có cách nào dọn', function () {
    dungDongMoCoi($this->chuQuan);

    $ketQua = (new ReconcileStockLedger)->handle();

    expect($ketQua->sach())->toBeFalse()
        ->and($ketQua->soCaiMoCoi)->toHaveCount(1)
        ->and($ketQua->moCoiDaGhiChu)->toBe([]);
});

it('xác nhận xong thì đối soát sạch trở lại, nhưng dòng đó VẪN được liệt kê', function () {
    $movement = dungDongMoCoi($this->chuQuan);

    ghiChuMoCoi($movement, $this->chuQuan);

    $ketQua = (new ReconcileStockLedger)->handle();

    expect($ketQua->sach())->toBeTrue()
        ->and($ketQua->soCaiMoCoi)->toBe([])
        ->and($ketQua->moCoiDaGhiChu)->toHaveCount(1)
        ->and($ketQua->moCoiDaGhiChu[0]['stock_movement_id'])->toBe($movement->id)
        ->and($ketQua->moCoiDaGhiChu[0]['acknowledged_by'])->toBe($this->chuQuan->name)
        ->and($ketQua->moCoiDaGhiChu[0]['reason'])->toContain('đã dùng thật');
});

it('xác nhận không đụng một chữ nào vào dòng sổ cái', function () {
    $movement = dungDongMoCoi($this->chuQuan);
    $truoc = $movement->only(['qty_delta', 'cost_delta', 'qty_after', 'ref_id', 'reason']);

    ghiChuMoCoi($movement, $this->chuQuan);

    expect($movement->fresh()->only(['qty_delta', 'cost_delta', 'qty_after', 'ref_id', 'reason']))->toBe($truoc);
});

it('thu ngân cũng được xác nhận', function () {
    $movement = dungDongMoCoi($this->chuQuan);
    $thuNgan = User::factory()->cashier()->create();

    ghiChuMoCoi($movement, $thuNgan, ['acknowledgedByUserId' => $thuNgan->id]);

    expect(StockReconciliationNote::query()->sole()->acknowledged_by_user_id)->toBe($thuNgan->id);
});

it('phục vụ không có quyền xác nhận', function () {
    $movement = dungDongMoCoi($this->chuQuan);
    $phucVu = User::factory()->staff()->create();

    ghiChuMoCoi($movement, $phucVu, ['acknowledgedByUserId' => $phucVu->id]);
})->throws(DomainException::class, 'Chỉ chủ quán hoặc thu ngân');

it('không ghi lý do đủ rõ thì bị chặn, không lưu gì', function () {
    $movement = dungDongMoCoi($this->chuQuan);

    expect(fn () => ghiChuMoCoi($movement, $this->chuQuan, ['reason' => 'ok']))
        ->toThrow(DomainException::class)
        ->and(fn () => ghiChuMoCoi($movement, $this->chuQuan, ['note' => '  ']))
        ->toThrow(DomainException::class);

    expect(StockReconciliationNote::query()->count())->toBe(0);
});

it('xác nhận hai lần chỉ lưu một ghi chú, giữ nguyên lý do của người đầu tiên', function () {
    $movement = dungDongMoCoi($this->chuQuan);

    $lanDau = ghiChuMoCoi($movement, $this->chuQuan, ['reason' => 'Lý do của người xem đầu tiên']);
    $lanHai = ghiChuMoCoi($movement, $this->chuQuan, ['reason' => 'Lý do khác hẳn của lần sau']);

    expect(StockReconciliationNote::query()->count())->toBe(1)
        ->and($lanHai->id)->toBe($lanDau->id)
        ->and($lanHai->reason)->toBe('Lý do của người xem đầu tiên');
});

it('database cũng chặn ghi chú cụt lủn, không chỉ dựa vào code (ck_srn_reason)', function () {
    $movement = dungDongMoCoi($this->chuQuan);

    StockReconciliationNote::query()->create([
        'stock_movement_id' => $movement->id,
        'note' => 'x',
        'reason' => 'y',
        'acknowledged_by_user_id' => $this->chuQuan->id,
        'acknowledged_at' => now(),
    ]);
})->throws(QueryException::class);

it('lệnh stock:ghi-chu-mo-coi ghi được và lệnh đối soát thôi trả về mã lỗi', function () {
    $movement = dungDongMoCoi($this->chuQuan);

    $this->artisan('stock:doi-soat')->assertFailed();

    $this->artisan('stock:ghi-chu-mo-coi', [
        'movement' => $movement->id,
        '--ghi-chu' => 'Đã đối chiếu phiếu bếp',
        '--ly-do' => 'Món huỷ sau khi đã trừ kho, nguyên liệu dùng thật',
    ])->assertSuccessful();

    $this->artisan('stock:doi-soat')
        ->expectsOutputToContain('ĐÃ XEM')
        ->assertSuccessful();
});

it('lệnh thiếu lý do thì báo lỗi tiếng Việt, không nổ lỗi thô', function () {
    $movement = dungDongMoCoi($this->chuQuan);

    $this->artisan('stock:ghi-chu-mo-coi', [
        'movement' => $movement->id,
        '--ghi-chu' => 'Đã xem rồi',
    ])->assertFailed();

    expect(StockReconciliationNote::query()->count())->toBe(0);
});
