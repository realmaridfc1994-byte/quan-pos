<?php

declare(strict_types=1);

use App\Domain\Catalog\Enums\Station;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Ordering\Enums\TableSessionStatus;
use App\Domain\Ordering\Events\OrderSentToKitchen;
use App\Domain\Ordering\Models\Order;
use App\Domain\Ordering\Models\TableSession;
use App\Domain\Staffing\Models\Shift;
use App\Domain\Staffing\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/** authHeaderFor() (tests/Pest.php) tự gọi Auth::forgetGuards() — bắt buộc khi test xác thực nhiều người dùng khác nhau. */
function guiBep(User $user, Order $order, ?string $idempotencyKey = null): TestResponse
{
    return test()->postJson("/api/v1/orders/{$order->id}/send", [], array_merge(
        authHeaderFor($user),
        ['Idempotency-Key' => $idempotencyKey ?? (string) Str::uuid()]
    ));
}

beforeEach(function () {
    $ca = Shift::factory()->open()->create();
    $this->staff = User::factory()->staff()->create();
    $this->luot = TableSession::factory()->withTable()->create(['shift_id' => $ca->id, 'status' => TableSessionStatus::Open]);
});

function taoPhieuGoiMon(TableSession $luot, User $staff, Station $tram): Order
{
    $category = Category::factory()->create(['station' => $tram]);
    $product = Product::factory()->for($category)->create();
    $variant = ProductVariant::factory()->for($product)->create();

    $response = test()->postJson("/api/v1/table-sessions/{$luot->id}/orders", [
        'uuid' => (string) Str::uuid(),
        'items' => [['product_id' => $product->id, 'product_variant_id' => $variant->id, 'quantity' => 1]],
    ], array_merge(authHeaderFor($staff), ['Idempotency-Key' => (string) Str::uuid()]))->assertCreated();

    return Order::query()->findOrFail($response->json('data.id'));
}

it('gửi bếp thành công, bắn đúng một event OrderSentToKitchen', function () {
    Event::fake();
    $order = taoPhieuGoiMon($this->luot, $this->staff, Station::Kitchen);

    guiBep($this->staff, $order)->assertOk();

    Event::assertDispatchedTimes(OrderSentToKitchen::class, 1);
});

it('M3: gọi gửi bếp hai lần cùng Idempotency-Key chỉ bắn đúng một event, một phiếu', function () {
    Event::fake();
    $order = taoPhieuGoiMon($this->luot, $this->staff, Station::Kitchen);
    $key = (string) Str::uuid();

    guiBep($this->staff, $order, $key)->assertOk();
    guiBep($this->staff, $order, $key)->assertOk();

    Event::assertDispatchedTimes(OrderSentToKitchen::class, 1);
    expect(Order::query()->count())->toBe(1);
});

it('M7: món bếp và món quầy gọi thành hai phiếu riêng, mỗi phiếu gửi bếp độc lập', function () {
    Event::fake();
    $phieuBep = taoPhieuGoiMon($this->luot, $this->staff, Station::Kitchen);
    $phieuQuay = taoPhieuGoiMon($this->luot, $this->staff, Station::Bar);

    expect($phieuBep->station)->toBe(Station::Kitchen)
        ->and($phieuQuay->station)->toBe(Station::Bar)
        ->and($phieuBep->id)->not->toBe($phieuQuay->id);

    guiBep($this->staff, $phieuBep)->assertOk()->assertJsonPath('data.station', 'kitchen');
    guiBep($this->staff, $phieuQuay)->assertOk()->assertJsonPath('data.station', 'bar');

    Event::assertDispatchedTimes(OrderSentToKitchen::class, 2);
});

it('bếp không có quyền tự gửi phiếu (đó là việc của phục vụ)', function () {
    $bep = User::factory()->kitchen()->create();
    $order = taoPhieuGoiMon($this->luot, $this->staff, Station::Kitchen);

    guiBep($bep, $order)->assertForbidden();
});

it('gửi bếp một phiếu không tồn tại thì báo không tìm thấy', function () {
    test()->postJson('/api/v1/orders/999999/send', [], array_merge(
        authHeaderFor($this->staff),
        ['Idempotency-Key' => (string) Str::uuid()]
    ))->assertNotFound();
});
