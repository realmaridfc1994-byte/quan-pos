<?php

declare(strict_types=1);

/**
 * Phase 2 Bước 5, mục 3 của yêu cầu: chặn đóng ca khi còn xung đột đồng bộ
 * chưa xử lý — vì xung đột chưa xử lý nghĩa là số tiền chưa chắc đúng
 * (docs/thiet-ke-dong-bo.md mục 6.2).
 */

use App\Domain\Ordering\Models\DiningTable;
use App\Domain\Ordering\Models\TableSession;
use App\Domain\Ordering\Models\TableSessionTable;
use App\Domain\Staffing\Actions\CloseShift;
use App\Domain\Staffing\DTO\CloseShiftData;
use App\Domain\Staffing\Models\Shift;
use App\Domain\Staffing\Models\User;
use App\Domain\Sync\Actions\ResolveSyncConflict;
use App\Domain\Sync\DTO\ResolveSyncConflictData;
use App\Domain\Sync\Enums\ConflictKind;
use App\Domain\Sync\Enums\ConflictStatus;
use App\Domain\Sync\Models\SyncConflict;
use App\Exceptions\DomainException;
use App\Support\Money;
use Illuminate\Support\Str;

function dongCaTrucTiep(Shift $ca, User $thuNgan): Shift
{
    return app(CloseShift::class)->handle(new CloseShiftData(
        shiftId: $ca->id,
        countedCash: Money::fromInt($ca->opening_cash),
        note: null,
        closedByUserId: $thuNgan->id,
    ));
}

beforeEach(function () {
    $this->thuNgan = User::factory()->cashier()->withPin('1234')->create();
    $this->ca = Shift::factory()->open()->create(['opened_by_user_id' => $this->thuNgan->id]);
});

it('chặn đóng ca khi còn xung đột chưa xử lý gắn với lượt khách thuộc ca này', function () {
    $ban = DiningTable::factory()->create();
    $session = TableSession::factory()->closed()->create(['shift_id' => $this->ca->id]);
    TableSessionTable::factory()->for($session)->create(['dining_table_id' => $ban->id, 'detached_at' => now()]);

    SyncConflict::factory()->create([
        'conflict_kind' => ConflictKind::ThuTienTrung->value,
        'table_session_id' => $session->id,
        'status' => ConflictStatus::Pending,
    ]);

    expect(fn () => dongCaTrucTiep($this->ca, $this->thuNgan))
        ->toThrow(DomainException::class);
});

it('chặn đóng ca khi còn xung đột không xác định được lượt khách nào (dòng 10)', function () {
    SyncConflict::factory()->create([
        'conflict_kind' => ConflictKind::ThieuThaoTacGoc->value,
        'table_session_id' => null,
        'status' => ConflictStatus::Pending,
    ]);

    expect(fn () => dongCaTrucTiep($this->ca, $this->thuNgan))
        ->toThrow(DomainException::class);
});

it('không chặn đóng ca vì xung đột thuộc MỘT ca khác', function () {
    $caKhac = Shift::factory()->closed()->create();
    $session = TableSession::factory()->closed()->create(['shift_id' => $caKhac->id]);

    SyncConflict::factory()->create([
        'conflict_kind' => ConflictKind::ThuTienTrung->value,
        'table_session_id' => $session->id,
        'status' => ConflictStatus::Pending,
    ]);

    $ket = dongCaTrucTiep($this->ca, $this->thuNgan);
    expect($ket->status->value)->toBe('closed');
});

it('xử lý xong xung đột rồi thì đóng ca lại được', function () {
    $ban = DiningTable::factory()->create();
    $session = TableSession::factory()->closed()->create(['shift_id' => $this->ca->id]);
    TableSessionTable::factory()->for($session)->create(['dining_table_id' => $ban->id, 'detached_at' => now()]);

    $xungDot = SyncConflict::factory()->create([
        'conflict_kind' => ConflictKind::ThuTienTrung->value,
        'table_session_id' => $session->id,
        'status' => ConflictStatus::Pending,
        'options' => [['key' => 'ket_khong_thua', 'label' => 'Két không thừa']],
        'payload' => ['goc' => ['type' => 'record_payment', 'payload' => ['uuid' => (string) Str::uuid(), 'amount' => 10_000]], 'cum' => []],
    ]);

    app(ResolveSyncConflict::class)->handle(new ResolveSyncConflictData(
        conflictId: $xungDot->id,
        dismiss: false,
        resolution: 'ket_khong_thua',
        note: 'Két không thừa, bỏ phiếu này.',
        resolvedByUserId: $this->thuNgan->id,
        approverUserId: $this->thuNgan->id,
        approverPin: '1234',
    ));

    $ket = dongCaTrucTiep($this->ca, $this->thuNgan);
    expect($ket->status->value)->toBe('closed');
});
