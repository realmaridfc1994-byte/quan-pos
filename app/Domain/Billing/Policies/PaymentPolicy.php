<?php

declare(strict_types=1);

namespace App\Domain\Billing\Policies;

use App\Domain\Staffing\Enums\UserRole;
use App\Domain\Staffing\Models\User;

final class PaymentPolicy
{
    /** Thu tiền cho một lượt khách. */
    public function create(User $user): bool
    {
        return in_array($user->role, [UserRole::Owner, UserRole::Manager, UserRole::Staff], true);
    }
}
