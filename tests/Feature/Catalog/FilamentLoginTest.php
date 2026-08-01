<?php

declare(strict_types=1);

use App\Domain\Staffing\Models\User;
use App\Filament\Pages\Auth\Login;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

it('đăng nhập /admin bằng đúng số điện thoại và mật khẩu thì vào được', function () {
    User::factory()->owner()->create([
        'phone' => '0900000001',
        'password' => Hash::make('password'),
    ]);

    Livewire::test(Login::class)
        ->fillForm(['phone' => '0900000001', 'password' => 'password'])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    expect(auth()->guard('web')->check())->toBeTrue();
});

it('đăng nhập /admin bằng sai số điện thoại thì báo lỗi tiếng Việt, không vào được', function () {
    User::factory()->owner()->create([
        'phone' => '0900000001',
        'password' => Hash::make('password'),
    ]);

    Livewire::test(Login::class)
        ->fillForm(['phone' => '0999999999', 'password' => 'password'])
        ->call('authenticate')
        ->assertHasFormErrors()
        ->assertNotified();

    expect(auth()->guard('web')->check())->toBeFalse();
});

it('đăng nhập /admin bằng số điện thoại của phục vụ thì bị từ chối vào panel', function () {
    User::factory()->staff()->create([
        'phone' => '0900000003',
        'password' => Hash::make('password'),
    ]);

    Livewire::test(Login::class)
        ->fillForm(['phone' => '0900000003', 'password' => 'password'])
        ->call('authenticate')
        ->assertHasFormErrors();

    expect(auth()->guard('web')->check())->toBeFalse();
});

it('bảng users không có cột email', function () {
    expect(Schema::hasColumn('users', 'email'))->toBeFalse();
});
