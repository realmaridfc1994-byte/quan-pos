<?php

declare(strict_types=1);

use App\Domain\Ordering\Enums\TableSessionStatus;
use App\Domain\Ordering\Models\DiningTable;
use App\Domain\Ordering\Models\TableSession;
use App\Domain\Reservations\Actions\SeatReservation;
use App\Domain\Reservations\DTO\SeatReservationData;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Domain\Reservations\Models\Reservation;
use App\Domain\Staffing\Models\User;
use App\Exceptions\DomainException;
use App\Exceptions\InvalidReservationTransitionException;

beforeEach(function () {
    $this->user = User::factory()->staff()->create();
    $this->action = app(SeatReservation::class);
});

it('M4: nối đặt bàn pending vào lượt khách đang mở', function () {
    $dat = Reservation::factory()->create();
    $luot = TableSession::factory()->open()->create();

    $ketQua = $this->action->handle(new SeatReservationData($dat->id, $luot->id, $this->user->id));

    expect($ketQua->status)->toBe(ReservationStatus::Seated)
        ->and($ketQua->table_session_id)->toBe($luot->id)
        ->and($ketQua->status_changed_by_user_id)->toBe($this->user->id);
});

it('nối đặt bàn confirmed cũng hợp lệ', function () {
    $dat = Reservation::factory()->confirmed()->create();
    $luot = TableSession::factory()->open()->create();

    $ketQua = $this->action->handle(new SeatReservationData($dat->id, $luot->id, $this->user->id));

    expect($ketQua->status)->toBe(ReservationStatus::Seated);
});

it('M4: không có lượt khách đang mở thì bị chặn, không tự mở phiên mới', function () {
    $dat = Reservation::factory()->create();
    $luot = TableSession::factory()->create(['status' => TableSessionStatus::Billing]);

    $this->action->handle(new SeatReservationData($dat->id, $luot->id, $this->user->id));
})->throws(DomainException::class, 'Chưa có lượt khách đang mở ở bàn này — mở bàn trước rồi mới xếp đặt bàn vào.');

it('M1: không xếp bàn được cho đặt bàn đã cancelled', function () {
    $dat = Reservation::factory()->cancelled()->create();
    $luot = TableSession::factory()->open()->create();

    $this->action->handle(new SeatReservationData($dat->id, $luot->id, $this->user->id));
})->throws(InvalidReservationTransitionException::class);

it('xếp bàn không đòi dining_table_id của đặt bàn khớp bàn thật của lượt khách', function () {
    $banKhac = DiningTable::factory()->create();
    $dat = Reservation::factory()->create(['dining_table_id' => $banKhac->id]);
    $luot = TableSession::factory()->open()->create(); // không liên quan tới $banKhac

    $ketQua = $this->action->handle(new SeatReservationData($dat->id, $luot->id, $this->user->id));

    expect($ketQua->status)->toBe(ReservationStatus::Seated);
});
