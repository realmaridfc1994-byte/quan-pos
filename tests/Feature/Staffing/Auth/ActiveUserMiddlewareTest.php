<?php

declare(strict_types=1);

use App\Domain\Staffing\Models\User;

use function Pest\Laravel\withHeader;

it('user bị vô hiệu hoá giữa chừng thì token cũ bị chặn ngay', function () {
    $user = User::factory()->cashier()->withPin('1234')->create();
    $token = $user->createToken('pos-app')->plainTextToken;

    // Chủ quán tắt cờ is_active sau khi nhân viên đã đăng nhập từ trước.
    $user->update(['is_active' => false]);

    withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/auth/pin-verify', ['user_id' => $user->id, 'pin' => '1234'])
        ->assertForbidden();
});
