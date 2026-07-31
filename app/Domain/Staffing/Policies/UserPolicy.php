<?php

declare(strict_types=1);

namespace App\Domain\Staffing\Policies;

use App\Domain\Staffing\Enums\UserRole;
use App\Domain\Staffing\Models\User;

final class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [UserRole::Owner, UserRole::Cashier], true);
    }

    public function create(User $user): bool
    {
        return in_array($user->role, [UserRole::Owner, UserRole::Cashier], true);
    }

    public function update(User $user): bool
    {
        return in_array($user->role, [UserRole::Owner, UserRole::Cashier], true);
    }
}
