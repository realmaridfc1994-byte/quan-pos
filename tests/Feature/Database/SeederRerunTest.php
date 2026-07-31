<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\OptionGroup;
use App\Domain\Catalog\Models\Product;
use App\Domain\Ordering\Models\DiningTable;
use App\Domain\Ordering\Models\Order;
use App\Domain\Ordering\Models\TableSession;
use App\Domain\Staffing\Models\Shift;
use App\Domain\Staffing\Models\User;
use Illuminate\Support\Facades\Artisan;

it('db:seed chạy hai lần liên tiếp không lỗi và không tạo dữ liệu trùng', function () {
    Artisan::call('db:seed');
    Artisan::call('db:seed');

    expect(User::query()->count())->toBe(4)
        ->and(DiningTable::query()->count())->toBe(12)
        ->and(Product::query()->count())->toBe(60);
});

it('db:seed không đụng tới dữ liệu giao dịch có sẵn (shift + order còn nguyên)', function () {
    $shift = Shift::factory()->open()->create();
    $tableSession = TableSession::factory()->for($shift)->create();
    $order = Order::factory()->for($tableSession)->create();

    Artisan::call('db:seed');
    Artisan::call('db:seed');

    expect(Shift::query()->find($shift->id))->not->toBeNull()
        ->and(Shift::query()->find($shift->id)->code)->toBe($shift->code)
        ->and(Order::query()->find($order->id))->not->toBeNull()
        ->and(Order::query()->find($order->id)->uuid)->toBe($order->uuid);
});

it('db:seed không gộp nhầm hai option_group cùng tên nhưng khác phạm vi (product_id vs category_id)', function () {
    $product = Product::factory()->create();
    $category = Category::factory()->create();

    $nhomTheoMon = OptionGroup::factory()->forProduct($product)->create(['name' => 'Độ cay']);
    $nhomTheoDanhMuc = OptionGroup::factory()->forCategory($category)->create(['name' => 'Độ cay']);

    Artisan::call('db:seed');
    Artisan::call('db:seed');

    expect(OptionGroup::query()->where('name', 'Độ cay')->count())->toBeGreaterThanOrEqual(2)
        ->and(OptionGroup::query()->find($nhomTheoMon->id))->not->toBeNull()
        ->and(OptionGroup::query()->find($nhomTheoDanhMuc->id))->not->toBeNull();
});
