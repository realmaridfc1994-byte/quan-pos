<?php

declare(strict_types=1);

use App\Domain\Billing\Enums\PaymentMethod;
use App\Domain\Billing\Models\Payment;
use App\Domain\Reservations\Actions\RecordReservationDeposit;
use App\Domain\Reservations\DTO\RecordReservationDepositData;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Domain\Reservations\Models\Reservation;
use App\Domain\Staffing\Models\Shift;
use App\Domain\Staffing\Models\User;
use App\Exceptions\DomainException;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->user = User::factory()->cashier()->create();
    $this->action = app(RecordReservationDeposit::class);
});

function duLieuCoc(User $user, Reservation $dat, array $overrides = []): RecordReservationDepositData
{
    return new RecordReservationDepositData(
        uuid: $overrides['uuid'] ?? (string) Str::uuid(),
        reservationId: $dat->id,
        method: $overrides['method'] ?? PaymentMethod::Cash,
        amount: Money::fromInt($overrides['amount'] ?? 200_000),
        tenderedAmount: array_key_exists('tenderedAmount', $overrides) ? $overrides['tenderedAmount'] : Money::fromInt($overrides['amount'] ?? 200_000),
        reference: $overrides['reference'] ?? null,
        receivedByUserId: $user->id,
        occurredAt: $overrides['occurredAt'] ?? now()->toImmutable(),
    );
}

it('M3+M5: ghi cọc — table_session_id null, reservation_id set, không chạm bàn nào', function () {
    Shift::factory()->open()->create();
    $dat = Reservation::factory()->create();

    $coc = $this->action->handle(duLieuCoc($this->user, $dat));

    expect($coc->table_session_id)->toBeNull()
        ->and($coc->reservation_id)->toBe($dat->id)
        ->and($coc->amount)->toBe(200_000)
        ->and($coc->change_amount)->toBe(0);
});

it('gọi lại đúng cùng uuid — chỉ ghi một phiếu cọc', function () {
    Shift::factory()->open()->create();
    $dat = Reservation::factory()->create();
    $uuid = (string) Str::uuid();

    $lan1 = $this->action->handle(duLieuCoc($this->user, $dat, ['uuid' => $uuid]));
    $lan2 = $this->action->handle(duLieuCoc($this->user, $dat, ['uuid' => $uuid]));

    expect($lan2->id)->toBe($lan1->id);
    expect(Payment::query()->count())->toBe(1);
});

it('chưa mở ca thì không thu cọc được', function () {
    // Cố tình KHÔNG tạo ca nào đang mở.
    $dat = Reservation::factory()->create();

    $this->action->handle(duLieuCoc($this->user, $dat));
})->throws(DomainException::class, 'Chưa mở ca. Phải mở ca trước khi thu tiền cọc.');

it('đặt bàn đã seated thì không thu cọc được nữa', function () {
    Shift::factory()->open()->create();
    $dat = Reservation::factory()->create(['status' => ReservationStatus::Seated]);

    $this->action->handle(duLieuCoc($this->user, $dat));
})->throws(DomainException::class);

it('thu cọc tiền mặt phải ghi số tiền khách đưa', function () {
    Shift::factory()->open()->create();
    $dat = Reservation::factory()->create();

    $this->action->handle(duLieuCoc($this->user, $dat, ['tenderedAmount' => null]));
})->throws(DomainException::class, 'Thu cọc tiền mặt phải ghi số tiền khách đưa.');

it('khách đưa nhiều hơn thì thối lại đúng phần dư', function () {
    Shift::factory()->open()->create();
    $dat = Reservation::factory()->create();

    $coc = $this->action->handle(duLieuCoc($this->user, $dat, ['amount' => 200_000, 'tenderedAmount' => Money::fromInt(250_000)]));

    expect($coc->change_amount)->toBe(50_000);
});

/**
 * Hồi quy bug F (Phase 3): occurred_at/paid_at phải là thời điểm NGHIỆP VỤ
 * do người gọi truyền vào, KHÔNG PHẢI now() lúc Action chạy (M7).
 */
it('hồi quy bug F: paid_at bằng occurredAt đã truyền vào, khác với now() lúc ghi', function () {
    Shift::factory()->open()->create();
    $dat = Reservation::factory()->create();
    $thoiDiemThuCoc = Carbon::parse('2026-08-10 18:00:00');

    Carbon::setTestNow(Carbon::parse('2026-08-11 09:00:00'));

    $coc = $this->action->handle(duLieuCoc($this->user, $dat, ['occurredAt' => $thoiDiemThuCoc->toImmutable()]));

    Carbon::setTestNow();

    expect($coc->paid_at->equalTo($thoiDiemThuCoc))->toBeTrue()
        ->and($coc->paid_at->equalTo(Carbon::parse('2026-08-11 09:00:00')))->toBeFalse();
});
