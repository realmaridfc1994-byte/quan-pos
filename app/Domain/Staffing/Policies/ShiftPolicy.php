<?php

declare(strict_types=1);

namespace App\Domain\Staffing\Policies;

use App\Domain\Staffing\Enums\UserRole;
use App\Domain\Staffing\Models\Shift;
use App\Domain\Staffing\Models\User;

final class ShiftPolicy
{
    /**
     * Đóng ca. Ai cũng đóng được ca của chính mình; đóng ca của người khác
     * thì chỉ chủ quán/thu ngân.
     */
    public function close(User $user, Shift $shift): bool
    {
        if ($shift->opened_by_user_id === $user->id) {
            return in_array($user->role, [UserRole::Owner, UserRole::Cashier, UserRole::Staff], true);
        }

        return in_array($user->role, [UserRole::Owner, UserRole::Cashier], true);
    }
}
