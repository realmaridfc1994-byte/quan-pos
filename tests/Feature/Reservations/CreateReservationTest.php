<?php

declare(strict_types=1);

use App\Domain\Ordering\Models\DiningTable;
use App\Domain\Reservations\Actions\CreateReservation;
use App\Domain\Reservations\DTO\CreateReservationData;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Domain\Staffing\Models\User;
use App\Exceptions\DomainException;

beforeEach(function () {
    $this->user = User::factory()->staff()->create();
    $this->action = app(CreateReservation::class);
});

function duLieuDatBan(User $user, array $overrides = []): CreateReservationData
{
    return new CreateReservationData(
        customerId: $overrides['customerId'] ?? null,
        diningTableId: $overrides['diningTableId'] ?? null,
        guestCount: $overrides['guestCount'] ?? 4,
        reservedAt: $overrides['reservedAt'] ?? now()->addHours(3)->toImmutable(),
        note: $overrides['note'] ?? null,
        createdByUserId: $user->id,
    );
}

it('tạo đặt bàn mới ở trạng thái pending', function () {
    $ketQua = $this->action->handle(duLieuDatBan($this->user));

    expect($ketQua->reservation->status)->toBe(ReservationStatus::Pending)
        ->and($ketQua->reservation->created_by_user_id)->toBe($this->user->id)
        ->and($ketQua->overlappingReservationIds)->toBe([]);
});

it('số khách phải lớn hơn 0', function () {
    $this->action->handle(duLieuDatBan($this->user, ['guestCount' => 0]));
})->throws(DomainException::class);

it('M3: hai đặt bàn cùng bàn, cách nhau dưới 120 phút — cảnh báo, không chặn', function () {
    $ban = DiningTable::factory()->create();
    $gio = now()->addHours(5)->toImmutable();

    $lan1 = $this->action->handle(duLieuDatBan($this->user, ['diningTableId' => $ban->id, 'reservedAt' => $gio]));

    $lan2 = $this->action->handle(duLieuDatBan($this->user, [
        'diningTableId' => $ban->id,
        'reservedAt' => $gio->addMinutes(60),
    ]));

    expect($lan2->reservation->status)->toBe(ReservationStatus::Pending) // KHÔNG bị chặn
        ->and($lan2->overlappingReservationIds)->toBe([$lan1->reservation->id]);
});

it('cách nhau quá 120 phút thì không còn coi là trùng giờ', function () {
    $ban = DiningTable::factory()->create();
    $gio = now()->addHours(5)->toImmutable();

    $this->action->handle(duLieuDatBan($this->user, ['diningTableId' => $ban->id, 'reservedAt' => $gio]));

    $lan2 = $this->action->handle(duLieuDatBan($this->user, [
        'diningTableId' => $ban->id,
        'reservedAt' => $gio->addMinutes(200),
    ]));

    expect($lan2->overlappingReservationIds)->toBe([]);
});

it('chưa chọn bàn cụ thể thì không kiểm tra trùng giờ', function () {
    $ketQua = $this->action->handle(duLieuDatBan($this->user, ['diningTableId' => null]));

    expect($ketQua->overlappingReservationIds)->toBe([]);
});
