<?php

declare(strict_types=1);

namespace App\Domain\Billing\Policies;

use App\Domain\Billing\Models\Payment;
use App\Domain\Staffing\Enums\UserRole;
use App\Domain\Staffing\Models\User;

final class PaymentPolicy
{
    /** Thu tiền cho một lượt khách. */
    public function create(User $user): bool
    {
        return in_array($user->role, [UserRole::Owner, UserRole::Cashier, UserRole::Staff], true);
    }

    /** Huỷ một phiếu thu — nhạy cảm hơn thu tiền thường, chỉ chủ quán/thu ngân. */
    public function void(User $user, Payment $payment): bool
    {
        return in_array($user->role, [UserRole::Owner, UserRole::Cashier], true);
    }
}
