<?php

declare(strict_types=1);

use App\Domain\Reservations\Actions\ConfirmReservation;
use App\Domain\Reservations\DTO\ConfirmReservationData;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Domain\Reservations\Models\Reservation;
use App\Domain\Staffing\Models\User;
use App\Exceptions\InvalidReservationTransitionException;

beforeEach(function () {
    $this->user = User::factory()->cashier()->create();
    $this->action = app(ConfirmReservation::class);
});

it('xác nhận đặt bàn đang pending thành confirmed', function () {
    $dat = Reservation::factory()->create();

    $ketQua = $this->action->handle(new ConfirmReservationData($dat->id, $this->user->id));

    expect($ketQua->status)->toBe(ReservationStatus::Confirmed)
        ->and($ketQua->status_changed_by_user_id)->toBe($this->user->id)
        ->and($ketQua->status_changed_at)->not->toBeNull();
});

it('M1: không xác nhận được đặt bàn đã confirmed', function () {
    $dat = Reservation::factory()->confirmed()->create();

    $this->action->handle(new ConfirmReservationData($dat->id, $this->user->id));
})->throws(InvalidReservationTransitionException::class);

it('M1: không xác nhận được đặt bàn đã cancelled', function () {
    $dat = Reservation::factory()->cancelled()->create();

    $this->action->handle(new ConfirmReservationData($dat->id, $this->user->id));
})->throws(InvalidReservationTransitionException::class);
