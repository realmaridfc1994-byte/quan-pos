<?php

declare(strict_types=1);

namespace App\Domain\Billing\Enums;

enum PromotionType: string
{
    case Percent = 'percent';
    case Amount = 'amount';
    case HappyHour = 'happy_hour';

    public function label(): string
    {
        return match ($this) {
            self::Percent => 'Giảm theo %',
            self::Amount => 'Giảm số tiền cố định',
            self::HappyHour => 'Giờ vàng (giảm % theo khung giờ)',
        };
    }
}
