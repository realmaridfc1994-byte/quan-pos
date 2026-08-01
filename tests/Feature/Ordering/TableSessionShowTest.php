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

function xemLuotKhach(User $user, TableSession $luot): TestResponse
{
    $token = $user->createToken('pos-app')->plainTextToken;

    return test()->getJson("/api/v1/table-sessions/{$luot->id}", [
        'Authorization' => "Bearer {$token}",
    ]);
}

it('trả về lượt khách kèm bàn, phiếu gọi món và tạm tính', function () {
    $ca = Shift::factory()->open()->create();
    $staff = User::factory()->staff()->create();
    $luot = TableSession::factory()->withTable()->create(['shift_id' => $ca->id, 'status' => TableSessionStatus::Open]);

    $category = Category::factory()->create();
    $product = Product::factory()->for($category)->create(['name' => 'Bia Tiger']);
    $variant = ProductVariant::factory()->for($product)->create(['name' => 'Lon', 'price' => 25_000]);

    $order = Order::factory()->for($luot, 'tableSession')->create(['sequence_no' => 1, 'status' => OrderStatus::Sent]);
    OrderItem::factory()->for($order)->create([
        'product_id' => $product->id,
        'product_variant_id' => $variant->id,
        'product_name' => 'Bia Tiger',
        'variant_name' => 'Lon',
        'unit_price' => 25_000,
        'options_amount' => 0,
        'quantity' => 4,
        'status' => OrderItemStatus::Ordered,
    ]);
    app(RecalculateSessionSubtotal::class)->handle($luot);

    xemLuotKhach($staff, $luot)
        ->assertOk()
        ->assertJsonPath('data.id', $luot->id)
        ->assertJsonPath('data.subtotal_amount', 100_000)
        ->assertJsonPath('data.total_amount', 100_000)
        ->assertJsonPath('data.orders.0.sequence_no', 1)
        ->assertJsonPath('data.orders.0.items.0.product_name', 'Bia Tiger')
        ->assertJsonPath('data.orders.0.items.0.line_amount', 100_000)
        ->assertJsonPath('data.tables.0.is_primary', true);
});

it('lượt khách chưa gọi món nào thì tạm tính là 0', function () {
    $ca = Shift::factory()->open()->create();
    $staff = User::factory()->staff()->create();
    $luot = TableSession::factory()->withTable()->create(['shift_id' => $ca->id, 'status' => TableSessionStatus::Open]);

    xemLuotKhach($staff, $luot)
        ->assertOk()
        ->assertJsonPath('data.subtotal_amount', 0)
        ->assertJsonPath('data.orders', []);
});
