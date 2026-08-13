<?php

declare(strict_types=1);

namespace App\Domain\Reservations\DTO;

final readonly class CancelReservationData
{
    public function __construct(
        public int $reservationId,
        public string $reason,
        public int $cancelledByUserId,
    ) {}
}
