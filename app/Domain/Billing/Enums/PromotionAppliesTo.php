<?php

declare(strict_types=1);

namespace App\Domain\Billing\Enums;

enum PromotionAppliesTo: string
{
    case All = 'all';
    case Category = 'category';
    case Product = 'product';

    public function label(): string
    {
        return match ($this) {
            self::All => 'Toàn bộ hoá đơn',
            self::Category => 'Một nhóm món',
            self::Product => 'Một món',
        };
    }
}
