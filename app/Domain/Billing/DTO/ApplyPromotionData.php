<?php

declare(strict_types=1);

namespace App\Domain\Billing\DTO;

final readonly class ApplyPromotionData
{
    public function __construct(
        public int $tableSessionId,
        public string $promotionCode,
        public int $requestedByUserId,
    ) {}
}
