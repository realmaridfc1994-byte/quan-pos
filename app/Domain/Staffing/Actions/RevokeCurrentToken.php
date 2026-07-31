<?php

declare(strict_types=1);

namespace App\Domain\Staffing\Actions;

use App\Domain\Staffing\DTO\LogoutData;

/**
 * Đăng xuất: thu hồi đúng token đang dùng trên thiết bị đó, không đụng token của thiết bị khác.
 */
final class RevokeCurrentToken
{
    public function handle(LogoutData $data): void
    {
        $data->user->currentAccessToken()->delete();
    }
}
