<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Enums;

enum PurchaseStatus: string
{
    case Draft = 'draft';
    case Received = 'received';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Nháp',
            self::Received => 'Đã nhận hàng',
            self::Cancelled => 'Đã huỷ',
        };
    }
}
