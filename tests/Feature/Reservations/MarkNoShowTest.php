<?php

declare(strict_types=1);

use App\Domain\Billing\Enums\PaymentStatus;
use App\Domain\Billing\Models\Payment;
use App\Domain\Reservations\Actions\MarkNoShow;
use App\Domain\Reservations\DTO\MarkNoShowData;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Domain\Reservations\Models\Reservation;
use App\Domain\Staffing\Models\User;
use App\Exceptions\InvalidReservationTransitionException;

beforeEach(function () {
    $this->user = User::factory()->cashier()->create();
    $this->action = app(MarkNoShow::class);
});

it('M1: đánh dấu no_show từ pending', function () {
    $dat = Reservation::factory()->create();

    $ketQua = $this->action->handle(new MarkNoShowData($dat->id, $this->user->id));

    expect($ketQua->status)->toBe(ReservationStatus::NoShow);
});

it('M1: đánh dấu no_show từ confirmed', function () {
    $dat = Reservation::factory()->confirmed()->create();

    $ketQua = $this->action->handle(new MarkNoShowData($dat->id, $this->user->id));

    expect($ketQua->status)->toBe(ReservationStatus::NoShow);
});

it('M1: không đánh dấu no_show được đặt bàn đã seated', function () {
    $dat = Reservation::factory()->create(['status' => ReservationStatus::Seated]);

    $this->action->handle(new MarkNoShowData($dat->id, $this->user->id));
})->throws(InvalidReservationTransitionException::class);

it('M8: no_show KHÔNG tự động đụng tới tiền cọc đã thu', function () {
    $dat = Reservation::factory()->confirmed()->create();
    $coc = Payment::factory()->deposit()->create([
        'reservation_id' => $dat->id,
        'method' => 'cash',
        'amount' => 200_000,
        'tendered_amount' => 200_000,
        'change_amount' => 0,
        'status' => PaymentStatus::Completed,
    ]);

    $this->action->handle(new MarkNoShowData($dat->id, $this->user->id));

    expect($coc->refresh()->status)->toBe(PaymentStatus::Completed);
});
