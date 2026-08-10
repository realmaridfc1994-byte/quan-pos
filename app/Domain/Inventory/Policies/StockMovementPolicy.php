<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Policies;

use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Staffing\Enums\UserRole;
use App\Domain\Staffing\Models\User;

/**
 * Sổ cái kho chỉ owner/cashier xem/ghi được (màn hình hao hụt) — staff/kitchen
 * không quản lý kho, giống PurchasePolicy. Không có update/delete: sổ cái
 * không bao giờ sửa hay xoá (xem StockMovement::delete()).
 */
final class StockMovementPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->laQuanLy($user);
    }

    public function view(User $user, StockMovement $stockMovement): bool
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
