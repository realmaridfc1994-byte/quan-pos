<?php

declare(strict_types=1);

use App\Domain\Billing\Enums\PaymentStatus;
use App\Domain\Billing\Models\Payment;
use App\Domain\Reservations\Actions\MarkNoShow;
use App\Domain\Reservations\DTO\MarkNoShowData;
use App\Domain\Reservations\Enums\DepositStatus;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Domain\Reservations\Models\Reservation;
use App\Domain\Staffing\Models\User;
use App\Exceptions\DomainException;
use App\Exceptions\InvalidReservationTransitionException;
use Spatie\Activitylog\Models\Activity;

const LY_DO_KHONG_TOI = 'Quá 30 phút không thấy khách, gọi không nghe máy';

beforeEach(function () {
    $this->user = User::factory()->cashier()->create();
    $this->action = app(MarkNoShow::class);
});

it('M1: đánh dấu no_show từ pending', function () {
    $dat = Reservation::factory()->create();

    $ketQua = $this->action->handle(new MarkNoShowData($dat->id, LY_DO_KHONG_TOI, $this->user->id));

    expect($ketQua->status)->toBe(ReservationStatus::NoShow);
});

it('M1: đánh dấu no_show từ confirmed', function () {
    $dat = Reservation::factory()->confirmed()->create();

    $ketQua = $this->action->handle(new MarkNoShowData($dat->id, LY_DO_KHONG_TOI, $this->user->id));

    expect($ketQua->status)->toBe(ReservationStatus::NoShow);
});

it('M1: không đánh dấu no_show được đặt bàn đã seated', function () {
    $dat = Reservation::factory()->create(['status' => ReservationStatus::Seated]);

    $this->action->handle(new MarkNoShowData($dat->id, LY_DO_KHONG_TOI, $this->user->id));
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

    $this->action->handle(new MarkNoShowData($dat->id, LY_DO_KHONG_TOI, $this->user->id));

    expect($coc->refresh()->status)->toBe(PaymentStatus::Completed);
});

// ── Dấu vết cọc (chính sách chốt 12/08) ──────────────────────────────────

it('bắt buộc ghi lý do khi đánh dấu khách không tới', function () {
    $dat = Reservation::factory()->confirmed()->create();

    $this->action->handle(new MarkNoShowData($dat->id, '   ', $this->user->id));
})->throws(DomainException::class, 'Phải ghi rõ lý do đánh dấu khách không tới.');

it('lý do ghi vào status_reason, KHÔNG đè lên ghi chú của khách', function () {
    $dat = Reservation::factory()->confirmed()->create(['note' => 'Bàn gần quạt, có trẻ nhỏ']);

    $ketQua = $this->action->handle(new MarkNoShowData($dat->id, LY_DO_KHONG_TOI, $this->user->id));

    expect($ketQua->status_reason)->toBe(LY_DO_KHONG_TOI)
        // Ghi chú của khách còn nguyên — mất là mất vĩnh viễn, không khôi phục được.
        ->and($ketQua->note)->toBe('Bàn gần quạt, có trẻ nhỏ')
        ->and($ketQua->status_changed_by_user_id)->toBe($this->user->id)
        ->and($ketQua->status_changed_at)->not->toBeNull();
});

it('ghi một dòng nhật ký để ba tháng sau còn tra được', function () {
    $dat = Reservation::factory()->confirmed()->create();

    $this->action->handle(new MarkNoShowData($dat->id, LY_DO_KHONG_TOI, $this->user->id));

    $nhatKy = Activity::query()->where('log_name', 'dat-ban')->sole();
    expect($nhatKy->getExtraProperty('reason'))->toBe(LY_DO_KHONG_TOI)
        ->and($nhatKy->getExtraProperty('reservation_id'))->toBe($dat->id);
});

it('cọc của đặt bàn vừa đánh dấu no_show mặc định là CHƯA XỬ LÝ, hệ thống không tự suy diễn', function () {
    $dat = Reservation::factory()->confirmed()->create();

    $ketQua = $this->action->handle(new MarkNoShowData($dat->id, LY_DO_KHONG_TOI, $this->user->id));

    expect($ketQua->deposit_status)->toBe(DepositStatus::Unhandled)
        ->and($ketQua->deposit_handled_at)->toBeNull();
});
