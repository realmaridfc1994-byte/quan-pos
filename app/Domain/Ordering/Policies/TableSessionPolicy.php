<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Policies;

use App\Domain\Ordering\Models\TableSession;
use App\Domain\Staffing\Enums\UserRole;
use App\Domain\Staffing\Models\User;

final class TableSessionPolicy
{
    /** Mở bàn cho lượt khách mới. */
    public function open(User $user): bool
    {
        return in_array($user->role, [UserRole::Owner, UserRole::Cashier, UserRole::Staff], true);
    }

    /** Ghép thêm bàn vào lượt khách đang mở. */
    public function attachTable(User $user, TableSession $tableSession): bool
    {
        return in_array($user->role, [UserRole::Owner, UserRole::Cashier, UserRole::Staff], true);
    }

    /** Nhả một bàn ra khỏi lượt khách đang mở. */
    public function detachTable(User $user, TableSession $tableSession): bool
    {
        return in_array($user->role, [UserRole::Owner, UserRole::Cashier, UserRole::Staff], true);
    }

    /** Chuyển bàn cho lượt khách đang mở. */
    public function transferTable(User $user, TableSession $tableSession): bool
    {
        return in_array($user->role, [UserRole::Owner, UserRole::Cashier, UserRole::Staff], true);
    }

    /** Đóng lượt khách. */
    public function close(User $user, TableSession $tableSession): bool
    {
        return in_array($user->role, [UserRole::Owner, UserRole::Cashier, UserRole::Staff], true);
    }

    /** Tách lượt khách đang mở thành hai. */
    public function splitTableSession(User $user, TableSession $tableSession): bool
    {
        return in_array($user->role, [UserRole::Owner, UserRole::Cashier, UserRole::Staff], true);
    }

    /** Chuyển dòng món sang một lượt khách đang mở khác. */
    public function moveItems(User $user, TableSession $tableSession): bool
    {
        return in_array($user->role, [UserRole::Owner, UserRole::Cashier, UserRole::Staff], true);
    }

    /**
     * Giảm giá trên hoá đơn.
     * Chủ quán giảm bao nhiêu cũng được, thu ngân tối đa 20%, nhân viên không được giảm.
     */
    public function discount(User $user, TableSession $tableSession, int $percent): bool
    {
        return match ($user->role) {
            UserRole::Owner => true,
            UserRole::Cashier => $percent <= 20,
            default => false,
        };
    }

    /** Huỷ toàn bộ hoá đơn của một lượt khách. */
    public function void(User $user, TableSession $tableSession): bool
    {
        return in_array($user->role, [UserRole::Owner, UserRole::Cashier], true);
    }

    /**
     * Được phép gọi API giảm giá hay không. Mức % cụ thể được duyệt trong
     * CalculateBill theo ngưỡng vai trò của discount() ở trên — hàm này chỉ
     * chặn từ vòng ngoài những người không bao giờ được đụng vào giảm giá.
     */
    public function requestDiscount(User $user, TableSession $tableSession): bool
    {
        return in_array($user->role, [UserRole::Owner, UserRole::Cashier, UserRole::Staff], true);
    }
}
