<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Policies;

use App\Domain\Inventory\Models\Purchase;
use App\Domain\Staffing\Enums\UserRole;
use App\Domain\Staffing\Models\User;

/**
 * Nhập hàng và giá vốn chỉ dành owner/cashier — staff/kitchen không quản lý kho.
 */
final class PurchasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->laQuanLy($user);
    }

    public function view(User $user, Purchase $purchase): bool
    {
        return $this->laQuanLy($user);
    }

    public function create(User $user): bool
    {
        return $this->laQuanLy($user);
    }

    public function update(User $user, Purchase $purchase): bool
    {
        return $this->laQuanLy($user);
    }

    public function receive(User $user, Purchase $purchase): bool
    {
        return $this->laQuanLy($user);
    }

    public function cancel(User $user, Purchase $purchase): bool
    {
        return $this->laQuanLy($user);
    }

    private function laQuanLy(User $user): bool
    {
        return in_array($user->role, [UserRole::Owner, UserRole::Cashier], true);
    }
}
