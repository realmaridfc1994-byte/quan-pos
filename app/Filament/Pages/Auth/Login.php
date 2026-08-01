<?php

declare(strict_types=1);

namespace App\Filament\Pages\Auth;

use Filament\Forms\Components\Component;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Auth\Login as BaseLogin;
use Illuminate\Validation\ValidationException;

/**
 * Hệ này đăng nhập bằng SỐ ĐIỆN THOẠI (users.phone), không phải email —
 * bảng users không có cột email. Chỉ đổi field và thông báo lỗi, mọi luồng
 * xác thực khác (rate limit, kiểm tra canAccessPanel...) giữ nguyên của Filament.
 */
final class Login extends BaseLogin
{
    // Trang này đã đăng ký riêng qua ->login() trong AdminPanelProvider. Tắt tự
    // dò tìm để Filament không đăng ký nhầm nó thêm một lần nữa như trang điều
    // hướng bình thường (nó nằm trong app/Filament/Pages bị discoverPages quét).
    protected static bool $isDiscovered = false;

    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('phone')
            ->label('Số điện thoại')
            ->required()
            ->autocomplete()
            ->autofocus()
            ->extraInputAttributes(['tabindex' => 1]);
    }

    /** @return array<string, mixed> */
    protected function getCredentialsFromFormData(array $data): array
    {
        return [
            'phone' => $data['phone'],
            'password' => $data['password'],
        ];
    }

    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.phone' => 'Số điện thoại hoặc mật khẩu không đúng.',
        ]);
    }
}
