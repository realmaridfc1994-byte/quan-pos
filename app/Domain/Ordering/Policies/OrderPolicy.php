<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Policies;

use App\Domain\Staffing\Enums\UserRole;
use App\Domain\Staffing\Models\User;

final class OrderPolicy
{
    /** Gọi món — gửi phiếu xuống bếp/quầy. */
    public function create(User $user): bool
    {
        return in_array($user->role, [UserRole::Owner, UserRole::Cashier, UserRole::Staff], true);
    }

    /** Gửi một phiếu đã gọi xuống bếp/quầy. */
    public function send(User $user): bool
    {
        return in_array($user->role, [UserRole::Owner, UserRole::Cashier, UserRole::Staff], true);
    }
}
