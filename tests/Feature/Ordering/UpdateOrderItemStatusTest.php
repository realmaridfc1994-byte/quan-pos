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
use Illuminate\Support\Facades\DB;
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

/**
 * THỨ TỰ KHOÁ — chống kẹt chéo (sửa 12/08, review Phase 3 Bước 10).
 *
 * CancelOrderItem khoá `orders` rồi mới tới `order_items`. Action này TỪNG khoá
 * ngược lại: `order_items` → `stock_balances` → `orders`. Hai bên giữ chặt cái
 * bên kia đang chờ — thu ngân huỷ món đúng lúc bếp bấm xong là kẹt chéo, MySQL
 * huỷ một bên và người dùng nhận một thông báo lỗi khó hiểu. Phase 3 còn nhét
 * cả công đoạn trừ kho vào giữa hai lần khoá, kéo dài khoảng nguy hiểm.
 *
 * Luật chuỗi khoá ở CLAUDE.md mục 4.11 chốt thứ tự: TableSession → Order →
 * OrderItem → ... → StockBalance. Test này đọc đúng thứ tự các câu `for update`
 * thật sự chạy, nên nó đỏ ngay nếu ai đảo lại.
 */
it('khoá phiếu bếp TRƯỚC dòng món và trước bảng tồn — đúng chuỗi khoá chống kẹt chéo', function () {
    $thuTuKhoa = [];

    DB::listen(function ($truyVan) use (&$thuTuKhoa): void {
        if (! str_contains(strtolower($truyVan->sql), 'for update')) {
            return;
        }

        foreach (['`orders`' => 'orders', '`order_items`' => 'order_items', '`stock_balances`' => 'stock_balances'] as $mau => $ten) {
            if (str_contains($truyVan->sql, $mau)) {
                $thuTuKhoa[] = $ten;
            }
        }
    });

    danhDauXongMon($this->bep, $this->itemA)->assertOk();

    $viTri = fn (string $bang): int|false => array_search($bang, $thuTuKhoa, true);

    expect($viTri('orders'))->not->toBeFalse('Không thấy câu khoá bảng orders nào.')
        ->and($viTri('order_items'))->not->toBeFalse('Không thấy câu khoá bảng order_items nào.')
        ->and($viTri('orders'))->toBeLessThan($viTri('order_items'));
});
