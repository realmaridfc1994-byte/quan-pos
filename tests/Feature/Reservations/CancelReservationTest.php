<?php

declare(strict_types=1);

use App\Domain\Billing\Enums\PaymentStatus;
use App\Domain\Billing\Models\Payment;
use App\Domain\Reservations\Actions\CancelReservation;
use App\Domain\Reservations\DTO\CancelReservationData;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Domain\Reservations\Models\Reservation;
use App\Domain\Staffing\Models\User;
use App\Exceptions\DomainException;
use App\Exceptions\InvalidReservationTransitionException;

beforeEach(function () {
    $this->user = User::factory()->cashier()->create();
    $this->action = app(CancelReservation::class);
});

it('M1+M2: huỷ đặt bàn pending, ghi đúng lý do/ai/lúc nào', function () {
    $dat = Reservation::factory()->create();

    $ketQua = $this->action->handle(new CancelReservationData($dat->id, 'Khách đổi ý', $this->user->id));

    expect($ketQua->status)->toBe(ReservationStatus::Cancelled)
        ->and($ketQua->note)->toBe('Khách đổi ý')
        ->and($ketQua->status_changed_by_user_id)->toBe($this->user->id)
        ->and($ketQua->status_changed_at)->not->toBeNull();
});

it('M2: không ghi lý do thì bị chặn', function () {
    $dat = Reservation::factory()->create();

    $this->action->handle(new CancelReservationData($dat->id, '   ', $this->user->id));
})->throws(DomainException::class, 'Phải ghi rõ lý do huỷ đặt bàn.');

it('M1: không huỷ được đặt bàn đã seated', function () {
    $dat = Reservation::factory()->create(['status' => ReservationStatus::Seated]);

    $this->action->handle(new CancelReservationData($dat->id, 'Nhầm lẫn', $this->user->id));
})->throws(InvalidReservationTransitionException::class);

it('M8: huỷ KHÔNG tự động đụng tới tiền cọc đã thu', function () {
    $dat = Reservation::factory()->confirmed()->create();
    $coc = Payment::factory()->deposit()->create([
        'reservation_id' => $dat->id,
        'method' => 'cash',
        'amount' => 200_000,
        'tendered_amount' => 200_000,
        'change_amount' => 0,
        'status' => PaymentStatus::Completed,
    ]);

    $this->action->handle(new CancelReservationData($dat->id, 'Khách báo huỷ', $this->user->id));

    expect($coc->refresh()->status)->toBe(PaymentStatus::Completed);
});
