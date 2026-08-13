<?php

declare(strict_types=1);

namespace App\Domain\Reservations\DTO;

use App\Domain\Billing\Enums\PaymentMethod;
use App\Support\Money;
use Carbon\CarbonImmutable;

final readonly class RecordReservationDepositData
{
    public function __construct(
        /** Vân tay do máy POS sinh trước khi gửi — chống ghi cọc trùng khi bấm hai lần */
        public string $uuid,
        public int $reservationId,
        public PaymentMethod $method,
        public Money $amount,
        public ?Money $tenderedAmount,
        public ?string $reference,
        public int $receivedByUserId,
        /** Thời điểm NGHIỆP VỤ lúc thu cọc — KHÔNG được lấy now() bên trong Action (M7) */
        public CarbonImmutable $occurredAt,
    ) {}
}
