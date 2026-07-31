<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Policies;

use App\Domain\Staffing\Enums\UserRole;
use App\Domain\Staffing\Models\User;

final class OrderItemPolicy
{
    /**
     * Huỷ một dòng món đã gửi bếp (đã có trong CSDL).
     *
     * Món chưa gửi bếp chỉ tồn tại ở giỏ hàng phía màn hình, chưa có dòng nào để
     * phân quyền — nhân viên/thu ngân/chủ quán đều huỷ được tự do ở đó.
     * Một khi đã gửi bếp (dòng đã lưu), chỉ chủ quán/thu ngân được huỷ.
     * Riêng món đã phục vụ ra bàn (status=served) còn cần xác thực PIN qua
     * /api/v1/auth/pin-verify trước khi gọi hành động huỷ — đó là việc của
     * Action CancelOrderItem sau này, không nằm trong Policy này.
     */
    public function cancel(User $user): bool
    {
        return in_array($user->role, [UserRole::Owner, UserRole::Cashier], true);
    }

    /** Đổi trạng thái món trên màn hình bếp/quầy (KDS). */
    public function updateStatus(User $user): bool
    {
        return in_array($user->role, [UserRole::Owner, UserRole::Cashier, UserRole::Kitchen], true);
    }
}
