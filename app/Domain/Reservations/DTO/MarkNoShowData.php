<?php

declare(strict_types=1);

namespace App\Domain\Reservations\DTO;

final readonly class MarkNoShowData
{
    public function __construct(
        public int $reservationId,
        /** Vì sao đánh dấu khách không tới — bắt buộc, chính sách cọc chốt 12/08 */
        public string $reason,
        public int $markedByUserId,
    ) {}
}
