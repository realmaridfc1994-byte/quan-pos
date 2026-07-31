<?php

declare(strict_types=1);

use App\Domain\Staffing\Models\User;

use function Pest\Laravel\withHeader;

it('đăng xuất thu hồi đúng token đang dùng, không đụng token thiết bị khác', function () {
    $user = User::factory()->create();
    $tokenBiThuHoi = $user->createToken('pos-app')->plainTextToken;
    $tokenMayKhac = $user->createToken('pos-app-may-khac')->plainTextToken;

    withHeader('Authorization', "Bearer {$tokenBiThuHoi}")
        ->postJson('/api/v1/auth/logout')
        ->assertOk();

    // Kiểm tra thẳng trong CSDL: đúng 1 token bị xoá, token của máy khác vẫn còn.
    // (Không gọi lại API bằng token cũ để kiểm — guard của Laravel giữ user đã xác thực
    // trong cùng một tiến trình test, gọi lần hai vẫn "qua" dù token đã bị xoá.)
    expect($user->tokens()->count())->toBe(1)
        ->and($user->tokens()->first()->name)->toBe('pos-app-may-khac');
});
