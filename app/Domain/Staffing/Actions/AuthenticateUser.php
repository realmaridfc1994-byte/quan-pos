<?php

declare(strict_types=1);

namespace App\Domain\Staffing\Actions;

use App\Domain\Staffing\DTO\AuthenticatedSession;
use App\Domain\Staffing\DTO\LoginData;
use App\Domain\Staffing\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Đăng nhập cho máy POS: số điện thoại + mật khẩu, trả về token Sanctum.
 */
final class AuthenticateUser
{
    public function handle(LoginData $data): AuthenticatedSession
    {
        $user = User::query()->where('phone', $data->phone)->first();

        // Không nói rõ "sai số điện thoại" hay "sai mật khẩu" để tránh lộ tài khoản nào tồn tại.
        if ($user === null || ! Hash::check($data->password, $user->password)) {
            throw ValidationException::withMessages([
                'phone' => 'Số điện thoại hoặc mật khẩu không đúng.',
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'phone' => 'Tài khoản đã bị vô hiệu hoá, liên hệ quản lý.',
            ]);
        }

        $token = $user->createToken('pos-app')->plainTextToken;

        return new AuthenticatedSession($user, $token);
    }
}
