<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Ordering\Actions\RecalculateSessionSubtotal;
use App\Domain\Ordering\Enums\OrderItemStatus;
use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Enums\TableSessionStatus;
use App\Domain\Ordering\Models\Order;
use App\Domain\Ordering\Models\OrderItem;
use App\Domain\Ordering\Models\TableSession;
use App\Domain\Staffing\Models\Shift;
use App\Domain\Staffing\Models\User;
use Illuminate\Testing\TestResponse;

function boDongMon(User $user, Order $order, OrderItem $item, array $payload = []): TestResponse
{
    $token = $user->createToken('pos-app')->plainTextToken;

    return test()->deleteJson("/api/v1/orders/{$order->id}/items/{$item->id}", $payload, [
        'Authorization' => "Bearer {$token}",
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

it('bỏ món khỏi phiếu thành công, không xoá cứng — chỉ đổi trạng thái', function () {
    boDongMon($this->staff, $this->order, $this->item, ['reason' => 'Khách đổi ý'])
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');

    $this->item->refresh();
    expect($this->item->status)->toBe(OrderItemStatus::Cancelled)
        ->and($this->item->cancel_reason)->toBe('Khách đổi ý')
        ->and($this->item->cancelled_by_user_id)->toBe($this->staff->id)
        ->and($this->item->cancelled_at)->not->toBeNull();

    expect(OrderItem::query()->count())->toBe(1);
});

it('không truyền lý do vẫn bỏ được, dùng lý do mặc định', function () {
    boDongMon($this->staff, $this->order, $this->item)->assertOk();

    expect($this->item->refresh()->cancel_reason)->toBe('Bỏ trước khi gửi bếp');
});

it('bỏ món thì tạm tính của lượt khách giảm theo', function () {
    $khac = OrderItem::factory()->for($this->order)->create([
        'unit_price' => 20_000,
        'options_amount' => 0,
        'quantity' => 1,
        'status' => OrderItemStatus::Ordered,
    ]);
    app(RecalculateSessionSubtotal::class)->handle($this->luot);
    expect($this->luot->refresh()->subtotal_amount)->toBe(120_000);

    boDongMon($this->staff, $this->order, $this->item)->assertOk();

    expect($this->luot->refresh()->subtotal_amount)->toBe(20_000);
});

it('món đã phục vụ thì không bỏ được nữa', function () {
    $this->item->update(['status' => OrderItemStatus::Served, 'served_at' => now()]);

    boDongMon($this->staff, $this->order, $this->item)
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Món này đã bếp xử lý hoặc đã huỷ, không bỏ được nữa.');
});

it('bếp không có quyền bỏ dòng món', function () {
    $bep = User::factory()->kitchen()->create();

    boDongMon($bep, $this->order, $this->item)->assertForbidden();
});
