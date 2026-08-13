<?php

declare(strict_types=1);

namespace App\Domain\Reservations\DTO;

final readonly class SeatReservationData
{
    public function __construct(
        public int $reservationId,
        /** Lượt khách ĐANG MỞ đã có sẵn — Action này không tự mở phiên mới (M4). */
        public int $tableSessionId,
        public int $seatedByUserId,
    ) {}
}
