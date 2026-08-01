<?php

declare(strict_types=1);

use App\Domain\Catalog\Enums\Station;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Ordering\Enums\OrderItemStatus;
use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Enums\TableSessionStatus;
use App\Domain\Ordering\Models\Order;
use App\Domain\Ordering\Models\OrderItem;
use App\Domain\Ordering\Models\TableSession;
use App\Domain\Staffing\Models\Shift;
use App\Domain\Staffing\Models\User;
use Illuminate\Testing\TestResponse;

/** authHeaderFor() (tests/Pest.php) tự gọi Auth::forgetGuards(). */
function xemKds(User $user, string $tram): TestResponse
{
    return test()->getJson("/api/v1/kds/tickets?station={$tram}", authHeaderFor($user));
}

beforeEach(function () {
    $ca = Shift::factory()->open()->create();
    $this->bep = User::factory()->kitchen()->create();
    $this->luot = TableSession::factory()->withTable()->create(['shift_id' => $ca->id, 'status' => TableSessionStatus::Open]);
});

it('chỉ trả về phiếu đúng nơi làm được lọc, không lẫn phiếu quầy vào bếp', function () {
    $nhomBep = Category::factory()->create(['station' => Station::Kitchen, 'name' => 'Đồ nhắm']);
    $monBep = Product::factory()->for($nhomBep)->create(['name' => 'Gà nướng']);
    $variantBep = ProductVariant::factory()->for($monBep)->create();

    $phieuBep = Order::factory()->for($this->luot, 'tableSession')->create(['station' => Station::Kitchen, 'status' => OrderStatus::Sent]);
    OrderItem::factory()->for($phieuBep)->create([
        'product_id' => $monBep->id,
        'product_variant_id' => $variantBep->id,
        'product_name' => $monBep->name,
        'variant_name' => $variantBep->name,
        'unit_price' => $variantBep->price,
        'status' => OrderItemStatus::Ordered,
    ]);

    $nhomQuay = Category::factory()->create(['station' => Station::Bar, 'name' => 'Nước']);
    $monQuay = Product::factory()->for($nhomQuay)->create(['name' => 'Bia']);
    $variantQuay = ProductVariant::factory()->for($monQuay)->create();

    $phieuQuay = Order::factory()->for($this->luot, 'tableSession')->create(['station' => Station::Bar, 'status' => OrderStatus::Sent]);
    OrderItem::factory()->for($phieuQuay)->create([
        'product_id' => $monQuay->id,
        'product_variant_id' => $variantQuay->id,
        'product_name' => $monQuay->name,
        'variant_name' => $variantQuay->name,
        'unit_price' => $variantQuay->price,
        'status' => OrderItemStatus::Ordered,
    ]);

    $ketQuaBep = xemKds($this->bep, 'kitchen')->assertOk();
    expect($ketQuaBep->json('data'))->toHaveCount(1);
    expect($ketQuaBep->json('data.0.items.0.product_name'))->toBe('Gà nướng');

    $ketQuaQuay = xemKds($this->bep, 'bar')->assertOk();
    expect($ketQuaQuay->json('data'))->toHaveCount(1);
    expect($ketQuaQuay->json('data.0.items.0.product_name'))->toBe('Bia');
});

it('phiếu đã served không hiện trên màn hình bếp nữa', function () {
    $category = Category::factory()->create(['station' => Station::Kitchen]);
    $product = Product::factory()->for($category)->create();
    $variant = ProductVariant::factory()->for($product)->create();

    $phieuXong = Order::factory()->for($this->luot, 'tableSession')->create(['station' => Station::Kitchen, 'status' => OrderStatus::Served, 'served_at' => now()]);
    OrderItem::factory()->for($phieuXong)->create(['product_id' => $product->id, 'product_variant_id' => $variant->id, 'status' => OrderItemStatus::Served, 'served_at' => now()]);

    $ketQua = xemKds($this->bep, 'kitchen')->assertOk();
    expect($ketQua->json('data'))->toHaveCount(0);
});

it('E6: món ghi đè nơi làm riêng (station_override) thì lên đúng màn hình đó, không theo nhóm món', function () {
    // Trà đá nằm nhóm "Mồi" (bếp) nhưng do quầy pha, giống ví dụ thật trong CLAUDE.md.
    $nhomMoi = Category::factory()->create(['station' => Station::Kitchen, 'name' => 'Mồi']);
    $traDa = Product::factory()->for($nhomMoi)->create(['name' => 'Trà đá', 'station_override' => Station::Bar]);
    $variant = ProductVariant::factory()->for($traDa)->create();

    $phieu = Order::factory()->for($this->luot, 'tableSession')->create(['station' => Station::Bar, 'status' => OrderStatus::Sent]);
    OrderItem::factory()->for($phieu)->create(['product_id' => $traDa->id, 'product_variant_id' => $variant->id, 'status' => OrderItemStatus::Ordered]);

    xemKds($this->bep, 'kitchen')->assertOk()->assertJsonCount(0, 'data');
    xemKds($this->bep, 'bar')->assertOk()->assertJsonCount(1, 'data');
});

it('gửi station không hợp lệ thì bị chặn', function () {
    xemKds($this->bep, 'quay-khong-ton-tai')->assertUnprocessable();
});

it('phục vụ không có quyền xem màn hình bếp', function () {
    $staff = User::factory()->staff()->create();

    xemKds($staff, 'kitchen')->assertForbidden();
});
