<?php

declare(strict_types=1);

use App\Domain\Staffing\Models\User;
use Illuminate\Support\Facades\Hash;

use function Pest\Laravel\postJson;

it('đăng nhập đúng số điện thoại và mật khẩu thì nhận được token', function () {
    $user = User::factory()->create([
        'phone' => '0912345678',
        'password' => Hash::make('mat-khau-dung'),
    ]);

    $response = postJson('/api/v1/auth/login', [
        'phone' => '0912345678',
        'password' => 'mat-khau-dung',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.user.id', $user->id)
        ->assertJsonStructure(['data' => ['token', 'user']]);
});

it('sai mật khẩu thì không đăng nhập được', function () {
    User::factory()->create([
        'phone' => '0912345678',
        'password' => Hash::make('mat-khau-dung'),
    ]);

    postJson('/api/v1/auth/login', [
        'phone' => '0912345678',
        'password' => 'mat-khau-sai',
    ])->assertUnprocessable();
});

it('sai số điện thoại thì không đăng nhập được', function () {
    postJson('/api/v1/auth/login', [
        'phone' => '0999999999',
        'password' => 'bat-ky',
    ])->assertUnprocessable();
});

it('tài khoản đã vô hiệu hoá thì không đăng nhập được', function () {
    User::factory()->inactive()->create([
        'phone' => '0912345678',
        'password' => Hash::make('mat-khau-dung'),
    ]);

    postJson('/api/v1/auth/login', [
        'phone' => '0912345678',
        'password' => 'mat-khau-dung',
    ])
        ->assertUnprocessable()
        ->assertJsonPath('errors.phone.0', 'Tài khoản đã bị vô hiệu hoá, liên hệ quản lý.');
});
