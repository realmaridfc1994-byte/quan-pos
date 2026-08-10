<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Enums;

enum StockTakeStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Đang kiểm kê',
            self::Closed => 'Đã chốt',
            self::Cancelled => 'Đã huỷ',
        };
    }
}
