<?php

declare(strict_types=1);

namespace App\Domain\Reservations\DTO;

final readonly class MarkNoShowData
{
    public function __construct(
        public int $reservationId,
        public int $markedByUserId,
    ) {}
}
