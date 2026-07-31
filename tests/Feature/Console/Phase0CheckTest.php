<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Product;
use App\Domain\Ordering\Models\DiningTable;
use App\Domain\Staffing\Models\Shift;
use App\Domain\Staffing\Models\User;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    Shift::factory()->closed()->create();
    DiningTable::factory()->count(2)->create();
    Product::factory()->count(2)->create();
});

it('chạy phase0:check không cờ thì không xoá bảng dữ liệu nào', function () {
    $demTruoc = [
        'users' => User::query()->count(),
        'shifts' => Shift::query()->count(),
        'dining_tables' => DiningTable::query()->count(),
        'products' => Product::query()->count(),
    ];

    Artisan::call('phase0:check');

    expect(User::query()->count())->toBe($demTruoc['users'])
        ->and(Shift::query()->count())->toBe($demTruoc['shifts'])
        ->and(DiningTable::query()->count())->toBe($demTruoc['dining_tables'])
        ->and(Product::query()->count())->toBe($demTruoc['products']);
});

it('chạy --with-tests khi đang có ca mở thì dừng lại, không chạy test', function () {
    Shift::factory()->open()->create();

    $exitCode = Artisan::call('phase0:check', ['--with-tests' => true]);

    expect($exitCode)->not->toBe(0);
});
