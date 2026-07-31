<?php

declare(strict_types=1);

namespace App\Domain\Staffing\Enums;

enum UserRole: string
{
    case Owner = 'owner';
    case Manager = 'manager';
    case Staff = 'staff';
    case Kitchen = 'kitchen';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Chủ quán',
            self::Manager => 'Quản lý',
            self::Staff => 'Nhân viên',
            self::Kitchen => 'Bếp',
        };
    }
}
