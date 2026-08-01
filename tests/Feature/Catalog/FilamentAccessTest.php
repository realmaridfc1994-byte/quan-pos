<?php

declare(strict_types=1);

use App\Domain\Staffing\Models\User;
use Filament\Facades\Filament;

it('owner vào được trang quản lý /admin', function () {
    $owner = User::factory()->owner()->make();

    expect($owner->canAccessPanel(Filament::getDefaultPanel()))->toBeTrue();
});

it('thu ngân vào được trang quản lý /admin', function () {
    $thuNgan = User::factory()->cashier()->make();

    expect($thuNgan->canAccessPanel(Filament::getDefaultPanel()))->toBeTrue();
});

it('phục vụ không vào được trang quản lý /admin', function () {
    $staff = User::factory()->staff()->make();

    expect($staff->canAccessPanel(Filament::getDefaultPanel()))->toBeFalse();
});

it('bếp không vào được trang quản lý /admin', function () {
    $bep = User::factory()->kitchen()->make();

    expect($bep->canAccessPanel(Filament::getDefaultPanel()))->toBeFalse();
});

it('thu ngân đã bị vô hiệu hoá không vào được trang quản lý dù đúng vai trò', function () {
    $thuNgan = User::factory()->cashier()->inactive()->make();

    expect($thuNgan->canAccessPanel(Filament::getDefaultPanel()))->toBeFalse();
});
