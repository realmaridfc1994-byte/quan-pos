<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Policies;

use App\Domain\Ordering\Models\TableSession;
use App\Domain\Staffing\Enums\UserRole;
use App\Domain\Staffing\Models\User;

final class TableSessionPolicy
{
    /** Mở bàn cho lượt khách mới. */
    public function open(User $user): bool
    {
        return in_array($user->role, [UserRole::Owner, UserRole::Cashier, UserRole::Staff], true);
    }

    /**
     * Giảm giá trên hoá đơn.
     * Chủ quán giảm bao nhiêu cũng được, thu ngân tối đa 20%, nhân viên không được giảm.
     */
    public function discount(User $user, TableSession $tableSession, int $percent): bool
    {
        return match ($user->role) {
            UserRole::Owner => true,
            UserRole::Cashier => $percent <= 20,
            default => false,
        };
    }

    /** Huỷ toàn bộ hoá đơn của một lượt khách. */
    public function void(User $user, TableSession $tableSession): bool
    {
        return in_array($user->role, [UserRole::Owner, UserRole::Cashier], true);
    }
}
