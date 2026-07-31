<?php

declare(strict_types=1);

namespace App\Domain\Billing\Enums;

enum PaymentStatus: string
{
    case Completed = 'completed';
    case Voided = 'voided';
}
