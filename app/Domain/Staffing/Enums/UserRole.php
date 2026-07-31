<?php

declare(strict_types=1);

namespace App\Domain\Staffing\Enums;

enum UserRole: string
{
    case Owner = 'owner';
    case Cashier = 'cashier';
    case Waiter = 'waiter';
    case Kitchen = 'kitchen';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Chủ quán',
            self::Cashier => 'Thu ngân',
            self::Waiter => 'Phục vụ',
            self::Kitchen => 'Bếp',
        };
    }
}
