<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Policies;

use App\Domain\Inventory\Models\StockTake;
use App\Domain\Staffing\Enums\UserRole;
use App\Domain\Staffing\Models\User;

/**
 * Kiểm kê chỉ dành owner/cashier — staff/kitchen không quản lý kho, giống
 * PurchasePolicy/StockMovementPolicy.
 */
final class StockTakePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->laQuanLy($user);
    }

    public function view(User $user, StockTake $stockTake): bool
    {
        return $this->laQuanLy($user);
    }

    public function create(User $user): bool
    {
        return $this->laQuanLy($user);
    }

    private function laQuanLy(User $user): bool
    {
        return in_array($user->role, [UserRole::Owner, UserRole::Cashier], true);
    }
}
