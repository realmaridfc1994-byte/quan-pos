<?php

declare(strict_types=1);

namespace App\Domain\Staffing\Policies;

use App\Domain\Staffing\Enums\UserRole;
use App\Domain\Staffing\Models\Shift;
use App\Domain\Staffing\Models\User;

final class ShiftPolicy
{
    /**
     * Mở ca: chủ quán, thu ngân, phục vụ đều mở được — quán ít người, ai mở
     * cửa buổi đó thì mở ca đó, không đợi chủ quán/thu ngân tới mới bán được.
     */
    public function open(User $user): bool
    {
        return in_array($user->role, [UserRole::Owner, UserRole::Cashier, UserRole::Staff], true);
    }

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

    /** Thu chi vặt: chỉ chủ quán/thu ngân — người giữ két. */
    public function recordCashMovement(User $user, Shift $shift): bool
    {
        return in_array($user->role, [UserRole::Owner, UserRole::Cashier], true);
    }

    /** Xem báo cáo doanh thu cuối ca: chỉ chủ quán/thu ngân, kể cả ca của người khác. */
    public function viewReport(User $user, Shift $shift): bool
    {
        return in_array($user->role, [UserRole::Owner, UserRole::Cashier], true);
    }
}
