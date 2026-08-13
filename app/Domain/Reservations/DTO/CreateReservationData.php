<?php

declare(strict_types=1);

namespace App\Domain\Reservations\DTO;

use Carbon\CarbonImmutable;

final readonly class CreateReservationData
{
    public function __construct(
        public ?int $customerId,
        public ?int $diningTableId,
        public int $guestCount,
        public CarbonImmutable $reservedAt,
        public ?string $note,
        public int $createdByUserId,
    ) {}
}
