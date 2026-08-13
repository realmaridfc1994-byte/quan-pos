<?php

declare(strict_types=1);

namespace App\Domain\Reservations\DTO;

use App\Domain\Reservations\Models\Reservation;

final readonly class CreateReservationResult
{
    /**
     * @param  list<int>  $overlappingReservationIds  Đặt bàn khác trùng bàn, trùng giờ (M3) —
     *                                                CẢNH BÁO, không chặn. Rỗng nếu không trùng
     *                                                hoặc chưa chọn bàn cụ thể.
     */
    public function __construct(
        public Reservation $reservation,
        public array $overlappingReservationIds,
    ) {}
}
