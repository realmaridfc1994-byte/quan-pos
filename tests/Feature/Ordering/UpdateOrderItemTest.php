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

function suaDongMon(User $user, Order $order, OrderItem $item, array $payload): TestResponse
{
    $token = $user->createToken('pos-app')->plainTextToken;

    return test()->patchJson("/api/v1/orders/{$order->id}/items/{$item->id}", $payload, [
        'Authorization' => "Bearer {$token}",
        'Idempotency-Key' => (string) Str::uuid(),
    ]);
}

beforeEach(function () {
    $ca = Shift::factory()->open()->create();
    $this->staff = User::factory()->staff()->create();
    $luot = TableSession::factory()->withTable()->create(['shift_id' => $ca->id, 'status' => TableSessionStatus::Open]);
    $category = Category::factory()->create();
    $product = Product::factory()->for($category)->create();
    $variant = ProductVariant::factory()->for($product)->create(['price' => 50_000]);

    $this->order = Order::factory()->for($luot, 'tableSession')->create(['status' => OrderStatus::Sent]);
    $this->item = OrderItem::factory()->for($this->order)->create([
        'product_id' => $product->id,
        'product_variant_id' => $variant->id,
        'unit_price' => 50_000,
        'options_amount' => 0,
        'quantity' => 2,
        'status' => OrderItemStatus::Ordered,
    ]);
    $this->luot = $luot;
});

it('sửa số lượng thành công khi món chưa gửi bếp, tạm tính cập nhật theo', function () {
    suaDongMon($this->staff, $this->order, $this->item, ['quantity' => 5])
        ->assertOk()
        ->assertJsonPath('data.quantity', 5)
        ->assertJsonPath('data.line_amount', 250_000);

    expect($this->luot->refresh()->subtotal_amount)->toBe(250_000);
});

it('sửa ghi chú thành công, không đụng số lượng', function () {
    suaDongMon($this->staff, $this->order, $this->item, ['note' => 'Ít cay'])
        ->assertOk()
        ->assertJsonPath('data.note', 'Ít cay')
        ->assertJsonPath('data.quantity', 2);
});

it('không cho sửa số lượng về 0', function () {
    suaDongMon($this->staff, $this->order, $this->item, ['quantity' => 0])->assertUnprocessable();

    expect($this->item->refresh()->quantity)->toBe(2);
});

it('món đã phục vụ thì không sửa được nữa', function () {
    $this->item->update(['status' => OrderItemStatus::Served, 'served_at' => now()]);

    suaDongMon($this->staff, $this->order, $this->item, ['quantity' => 9])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Món này đã bếp xử lý hoặc đã huỷ, không sửa được nữa.');
});

it('món đã huỷ thì không sửa được nữa', function () {
    $this->item->update([
        'status' => OrderItemStatus::Cancelled,
        'cancelled_at' => now(),
        'cancelled_by_user_id' => $this->staff->id,
        'cancel_reason' => 'Test',
    ]);

    suaDongMon($this->staff, $this->order, $this->item, ['quantity' => 9])->assertUnprocessable();
});

it('bếp không có quyền sửa dòng món', function () {
    $bep = User::factory()->kitchen()->create();

    suaDongMon($bep, $this->order, $this->item, ['quantity' => 3])->assertForbidden();
});
