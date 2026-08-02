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
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

function huyMon(User $user, Order $order, OrderItem $item, array $payload): TestResponse
{
    return test()->postJson(
        "/api/v1/orders/{$order->id}/items/{$item->id}/cancel",
        $payload,
        array_merge(authHeaderFor($user), ['Idempotency-Key' => (string) Str::uuid()])
    );
}

beforeEach(function () {
    $ca = Shift::factory()->open()->create();
    $this->owner = User::factory()->owner()->create();
    $this->thuNgan = User::factory()->cashier()->withPin('1234')->create();
    $luot = TableSession::factory()->withTable()->create(['shift_id' => $ca->id, 'status' => TableSessionStatus::Open]);
    $category = Category::factory()->create();
    $product = Product::factory()->for($category)->create();
    $variant = ProductVariant::factory()->for($product)->create(['price' => 25_000]);

    $this->luot = $luot;
    $this->order = Order::factory()->for($luot, 'tableSession')->create(['status' => OrderStatus::Sent]);
    $this->item = OrderItem::factory()->for($this->order)->create([
        'product_id' => $product->id,
        'product_variant_id' => $variant->id,
        'unit_price' => 25_000,
        'options_amount' => 0,
        'quantity' => 5,
        'status' => OrderItemStatus::Ordered,
    ]);
    app(RecalculateSessionSubtotal::class)->handle($luot);
});

it('H1+H2: huỷ toàn bộ dòng món chưa phục vụ — không xoá dòng, ghi đủ ai/lúc nào/vì sao', function () {
    huyMon($this->owner, $this->order, $this->item, ['quantity' => 5, 'reason' => 'Khách đổi ý'])
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');

    $this->item->refresh();
    expect($this->item->status)->toBe(OrderItemStatus::Cancelled)
        ->and($this->item->cancel_reason)->toBe('Khách đổi ý')
        ->and($this->item->cancelled_by_user_id)->toBe($this->owner->id)
        ->and($this->item->cancelled_at)->not->toBeNull();

    expect(OrderItem::query()->count())->toBe(1); // không dòng nào bị xoá
});

it('H2: không ghi lý do thì bị chặn', function () {
    huyMon($this->owner, $this->order, $this->item, ['quantity' => 5, 'reason' => ''])
        ->assertUnprocessable();

    expect($this->item->refresh()->status)->toBe(OrderItemStatus::Ordered);
});

it('H3: dòng đã huỷ không tính vào tạm tính của lượt khách', function () {
    expect($this->luot->refresh()->subtotal_amount)->toBe(125_000); // 5 x 25.000

    huyMon($this->owner, $this->order, $this->item, ['quantity' => 5, 'reason' => 'Bấm nhầm'])->assertOk();

    expect($this->luot->refresh()->subtotal_amount)->toBe(0);
});

it('H4: huỷ 1 trong 5 — tách thành dòng còn 4 (giữ nguyên) và dòng huỷ 1, tổng số lượng bằng nhau', function () {
    $tongTruoc = $this->item->quantity;

    $response = huyMon($this->owner, $this->order, $this->item, ['quantity' => 1, 'reason' => 'Khách trả bớt'])
        ->assertOk();

    $dongMoi = OrderItem::query()->findOrFail($response->json('data.id'));
    expect($dongMoi->id)->not->toBe($this->item->id)
        ->and($dongMoi->status)->toBe(OrderItemStatus::Cancelled)
        ->and($dongMoi->quantity)->toBe(1)
        ->and($dongMoi->split_from_item_id)->toBe($this->item->id)
        ->and($dongMoi->product_name)->toBe($this->item->product_name)
        ->and($dongMoi->unit_price)->toBe($this->item->unit_price);

    $this->item->refresh();
    expect($this->item->status)->toBe(OrderItemStatus::Ordered)
        ->and($this->item->quantity)->toBe(4);

    $tongSau = $this->item->quantity + $dongMoi->quantity;
    expect($tongSau)->toBe($tongTruoc);

    expect(OrderItem::query()->where('order_id', $this->order->id)->count())->toBe(2);
    expect($this->luot->refresh()->subtotal_amount)->toBe(100_000); // 4 x 25.000 còn lại
});

it('Bước 2: tách dòng từ món ĐÃ served — dòng mới kế thừa served_at của dòng gốc', function () {
    $this->item->update(['status' => OrderItemStatus::Served, 'served_at' => now()->subMinutes(10)]);
    // Cột served_at là DATETIME (không có phần mili-giây) — đọc lại từ DB để so
    // sánh đúng độ chính xác đã lưu, tránh lệch mili-giây với giá trị PHP gốc.
    $servedAtDaLuu = $this->item->refresh()->served_at;

    $response = huyMon($this->owner, $this->order, $this->item, [
        'quantity' => 1,
        'reason' => 'Khách trả bớt',
        'approver_user_id' => $this->thuNgan->id,
        'approver_pin' => '1234',
    ])->assertOk();

    $dongMoi = OrderItem::query()->findOrFail($response->json('data.id'));
    expect($dongMoi->served_at)->not->toBeNull()
        ->and($dongMoi->served_at->equalTo($servedAtDaLuu))->toBeTrue();

    expect($this->item->refresh()->served_at->equalTo($servedAtDaLuu))->toBeTrue();
});

it('Bước 2: tách dòng từ món CHƯA served — dòng mới cũng served_at = NULL', function () {
    expect($this->item->status)->toBe(OrderItemStatus::Ordered)
        ->and($this->item->served_at)->toBeNull();

    $response = huyMon($this->owner, $this->order, $this->item, ['quantity' => 1, 'reason' => 'Khách trả bớt'])
        ->assertOk();

    $dongMoi = OrderItem::query()->findOrFail($response->json('data.id'));
    expect($dongMoi->served_at)->toBeNull();
    expect($this->item->refresh()->served_at)->toBeNull();
});

it('huỷ nhiều hơn số lượng còn lại thì bị chặn', function () {
    huyMon($this->owner, $this->order, $this->item, ['quantity' => 6, 'reason' => 'Test'])
        ->assertUnprocessable();

    expect($this->item->refresh()->quantity)->toBe(5);
});

it('huỷ dòng đã huỷ rồi thì bị chặn', function () {
    huyMon($this->owner, $this->order, $this->item, ['quantity' => 5, 'reason' => 'Lần 1'])->assertOk();

    huyMon($this->owner, $this->order, $this->item, ['quantity' => 5, 'reason' => 'Lần 2'])
        ->assertUnprocessable();
});

it('H5: món đã phục vụ ra bàn phải có PIN duyệt mới huỷ được', function () {
    $this->item->update(['status' => OrderItemStatus::Served, 'served_at' => now()]);

    huyMon($this->owner, $this->order, $this->item, ['quantity' => 5, 'reason' => 'Khách trả món'])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Món đã phục vụ ra bàn, phải có người duyệt bằng mã PIN mới huỷ được.');

    expect($this->item->refresh()->status)->toBe(OrderItemStatus::Served);
});

it('H5: PIN sai thì không huỷ được món đã phục vụ', function () {
    $this->item->update(['status' => OrderItemStatus::Served, 'served_at' => now()]);

    huyMon($this->owner, $this->order, $this->item, [
        'quantity' => 5,
        'reason' => 'Khách trả món',
        'approver_user_id' => $this->thuNgan->id,
        'approver_pin' => '9999',
    ])->assertUnprocessable();

    expect($this->item->refresh()->status)->toBe(OrderItemStatus::Served);
});

it('H5: PIN đúng của thu ngân thì huỷ được món đã phục vụ', function () {
    $this->item->update(['status' => OrderItemStatus::Served, 'served_at' => now()]);

    huyMon($this->owner, $this->order, $this->item, [
        'quantity' => 5,
        'reason' => 'Khách trả món',
        'approver_user_id' => $this->thuNgan->id,
        'approver_pin' => '1234',
    ])->assertOk();

    expect($this->item->refresh()->status)->toBe(OrderItemStatus::Cancelled);
});

it('nhân viên phục vụ không có quyền huỷ món đã gửi bếp', function () {
    $staff = User::factory()->staff()->create();

    huyMon($staff, $this->order, $this->item, ['quantity' => 5, 'reason' => 'Test'])->assertForbidden();
});

it('bếp không có quyền huỷ món', function () {
    $bep = User::factory()->kitchen()->create();

    huyMon($bep, $this->order, $this->item, ['quantity' => 5, 'reason' => 'Test'])->assertForbidden();
});
