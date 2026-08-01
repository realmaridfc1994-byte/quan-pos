<?php

declare(strict_types=1);

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
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/** authHeaderFor() (tests/Pest.php) tự gọi Auth::forgetGuards(). */
function danhDauXongMon(User $user, OrderItem $item): TestResponse
{
    return test()->postJson("/api/v1/kds/items/{$item->id}/status", [], array_merge(
        authHeaderFor($user),
        ['Idempotency-Key' => (string) Str::uuid()]
    ));
}

beforeEach(function () {
    $ca = Shift::factory()->open()->create();
    $luot = TableSession::factory()->withTable()->create(['shift_id' => $ca->id, 'status' => TableSessionStatus::Open]);
    $category = Category::factory()->create();
    $product = Product::factory()->for($category)->create();
    $variant = ProductVariant::factory()->for($product)->create();

    $this->bep = User::factory()->kitchen()->create();
    $this->order = Order::factory()->for($luot, 'tableSession')->create(['status' => OrderStatus::Sent]);
    $this->itemA = OrderItem::factory()->for($this->order)->create([
        'product_id' => $product->id,
        'product_variant_id' => $variant->id,
        'status' => OrderItemStatus::Ordered,
    ]);
    $this->itemB = OrderItem::factory()->for($this->order)->create([
        'product_id' => $product->id,
        'product_variant_id' => $variant->id,
        'status' => OrderItemStatus::Ordered,
    ]);
});

it('đánh dấu dòng món đầu tiên xong: món sang served, phiếu sang preparing (còn dòng khác chưa xong)', function () {
    danhDauXongMon($this->bep, $this->itemA)
        ->assertOk()
        ->assertJsonPath('data.status', 'served');

    expect($this->itemA->refresh()->status)->toBe(OrderItemStatus::Served)
        ->and($this->itemA->served_at)->not->toBeNull()
        ->and($this->order->refresh()->status)->toBe(OrderStatus::Preparing);
});

it('đánh dấu hết mọi dòng món xong thì phiếu tự sang served', function () {
    danhDauXongMon($this->bep, $this->itemA)->assertOk();
    danhDauXongMon($this->bep, $this->itemB)->assertOk();

    expect($this->order->refresh()->status)->toBe(OrderStatus::Served)
        ->and($this->order->served_at)->not->toBeNull();
});

it('phiếu chỉ một dòng món: đánh dấu xong thì phiếu đi thẳng sent → preparing → served trong cùng một lần, không lỗi', function () {
    $luotRieng = $this->order->tableSession;
    $orderMotDong = Order::factory()->for($luotRieng, 'tableSession')->create([
        'sequence_no' => 2,
        'status' => OrderStatus::Sent,
    ]);
    $itemDuyNhat = OrderItem::factory()->for($orderMotDong)->create([
        'product_id' => $this->itemA->product_id,
        'product_variant_id' => $this->itemA->product_variant_id,
        'status' => OrderItemStatus::Ordered,
    ]);

    danhDauXongMon($this->bep, $itemDuyNhat)->assertOk();

    expect($orderMotDong->refresh()->status)->toBe(OrderStatus::Served);
});

it('món đã huỷ không tính vào điều kiện "hết món" của phiếu', function () {
    $this->itemB->update([
        'status' => OrderItemStatus::Cancelled,
        'cancelled_at' => now(),
        'cancelled_by_user_id' => $this->bep->id,
        'cancel_reason' => 'Hết hàng',
    ]);

    danhDauXongMon($this->bep, $this->itemA)->assertOk();

    expect($this->order->refresh()->status)->toBe(OrderStatus::Served);
});

it('chặn lùi: đánh dấu lại một món đã served', function () {
    danhDauXongMon($this->bep, $this->itemA)->assertOk();

    danhDauXongMon($this->bep, $this->itemA)
        ->assertUnprocessable()
        ->assertJsonPath('code', 'DOMAIN_ERROR');
});

it('phục vụ không có quyền đổi trạng thái món trên KDS', function () {
    $staff = User::factory()->staff()->create();

    danhDauXongMon($staff, $this->itemA)->assertForbidden();
});

it('chủ quán và thu ngân cũng đổi được trạng thái món trên KDS', function () {
    $owner = User::factory()->owner()->create();

    danhDauXongMon($owner, $this->itemA)->assertOk();
});
