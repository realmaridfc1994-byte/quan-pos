<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Policies;

use App\Domain\Staffing\Enums\UserRole;
use App\Domain\Staffing\Models\User;

final class DiningTablePolicy
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
