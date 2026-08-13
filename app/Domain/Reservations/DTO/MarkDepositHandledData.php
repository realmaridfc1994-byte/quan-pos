<?php

declare(strict_types=1);

namespace App\Domain\Reservations\DTO;

use App\Domain\Reservations\Enums\DepositStatus;
use Carbon\CarbonImmutable;

final readonly class MarkDepositHandledData
{
    public function __construct(
        public int $reservationId,
        /** Đã hoàn khách hay quán giữ lại — không nhận `unhandled` */
        public DepositStatus $depositStatus,
        /** Vì sao xử lý như vậy — bắt buộc, luật 13 CLAUDE.md */
        public string $note,
        public int $handledByUserId,
        /** Thời điểm NGHIỆP VỤ lúc xử lý cọc — KHÔNG được lấy now() bên trong Action (M7) */
        public CarbonImmutable $occurredAt,
    ) {}
}
