<?php

declare(strict_types=1);

namespace App\Domain\Reservations\DTO;

final readonly class ConfirmReservationData
{
    public function __construct(
        public int $reservationId,
        public int $confirmedByUserId,
    ) {}
}
