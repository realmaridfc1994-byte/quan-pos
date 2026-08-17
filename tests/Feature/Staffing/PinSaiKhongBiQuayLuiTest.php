<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Ordering\Actions\RecalculateSessionSubtotal;
use App\Domain\Ordering\Enums\OrderItemStatus;
use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Models\Order;
use App\Domain\Ordering\Models\OrderItem;
use App\Domain\Ordering\Models\TableSession;
use App\Domain\Staffing\Models\Shift;
use App\Domain\Staffing\Models\User;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/**
 * KHOÁ 15 PHÚT SAU 5 LẦN NHẬP SAI PIN PHẢI THẬT SỰ ĐẾM TỚI.
 *
 * Trước 17/08, hai đường giảm giá vượt ngưỡng và huỷ món đã phục vụ xác thực
 * PIN BÊN TRONG giao dịch. Nhập sai PIN thì bộ đếm số lần sai được cộng lên
 * (bảng `cache`), rồi lỗi "Mã PIN không đúng" ném ra, thoát khỏi giao dịch,
 * Laravel quay lui — và quay lui LUÔN CẢ bộ đếm lẫn dòng nhật ký vừa ghi.
 *
 * Hậu quả: bộ đếm vĩnh viễn đứng ở 1. Nhân viên có thể dò PIN 4 số không giới
 * hạn số lần, và nhật ký hoạt động không giữ lại một dấu vết nào. Chốt chặn
 * trông thì có, nhưng chưa bao giờ chạy.
 *
 * Hai test dưới đây đếm đúng cái mà cơ chế đó hứa: sai 5 lần thì lần thứ 6
 * phải bị khoá. Chạy chúng trên bản code cũ là đỏ cả hai.
 *
 * Xem CLAUDE.md mục 12 (PIN duyệt xong mới được mở giao dịch).
 */
function giamGiaThuPinSai(User $nguoiGoi, TableSession $luot, User $nguoiDuyet, string $pin): TestResponse
{
    return test()->postJson(
        "/api/v1/table-sessions/{$luot->id}/discount",
        [
            'discount_amount' => 200_000, // 40% của 500.000 — vượt ngưỡng 20% của thu ngân
            'discount_reason' => 'Khách quen',
            'approver_user_id' => $nguoiDuyet->id,
            'approver_pin' => $pin,
        ],
        array_merge(authHeaderFor($nguoiGoi), ['Idempotency-Key' => (string) Str::uuid()])
    );
}

function huyMonThuPinSai(User $nguoiGoi, Order $phieu, OrderItem $dongMon, User $nguoiDuyet, string $pin): TestResponse
{
    return test()->postJson(
        "/api/v1/orders/{$phieu->id}/items/{$dongMon->id}/cancel",
        [
            'quantity' => 5,
            'reason' => 'Khách trả món',
            'approver_user_id' => $nguoiDuyet->id,
            'approver_pin' => $pin,
        ],
        array_merge(authHeaderFor($nguoiGoi), ['Idempotency-Key' => (string) Str::uuid()])
    );
}

beforeEach(function () {
    $ca = Shift::factory()->open()->create();

    $this->owner = User::factory()->owner()->withPin('1111')->create();
    $this->thuNgan = User::factory()->cashier()->withPin('2222')->create();
    $this->luot = TableSession::factory()->for($ca, 'shift')->create();

    $variant = ProductVariant::factory()
        ->for(Product::factory()->for(Category::factory()))
        ->create(['price' => 100_000]);

    $this->order = Order::factory()->for($this->luot, 'tableSession')->create(['status' => OrderStatus::Sent]);
    $this->item = OrderItem::factory()->for($this->order)->create([
        'product_id' => $variant->product_id,
        'product_variant_id' => $variant->id,
        'unit_price' => 100_000,
        'options_amount' => 0,
        'quantity' => 5, // tạm tính 500.000
    ]);

    app(RecalculateSessionSubtotal::class)->handle($this->luot);
});

it('giảm giá: nhập sai PIN 5 lần thì lần thứ 6 bị khoá, bộ đếm không bốc hơi theo giao dịch', function () {
    foreach (range(1, 5) as $lanThu) {
        giamGiaThuPinSai($this->thuNgan, $this->luot, $this->owner, '9999')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Mã PIN không đúng.', "Lần thử thứ {$lanThu} phải báo sai PIN.");
    }

    giamGiaThuPinSai($this->thuNgan, $this->luot, $this->owner, '9999')
        ->assertUnprocessable()
        ->assertJsonPath(
            'message',
            'Bạn đã nhập sai mã PIN quá nhiều lần. Đợi 15 phút rồi thử lại.',
            'Sai 5 lần rồi mà lần thứ 6 vẫn chỉ báo "Mã PIN không đúng" nghĩa là bộ đếm '
            .'đang bị quay lui cùng giao dịch — PIN lại đang được xác thực bên trong '
            .'DB::transaction. Xem CLAUDE.md mục 12.'
        );

    // Khoá rồi thì PIN ĐÚNG cũng không qua được, và tiền không đổi một đồng.
    giamGiaThuPinSai($this->thuNgan, $this->luot, $this->owner, '1111')->assertUnprocessable();

    expect($this->luot->refresh()->discount_amount)->toBe(0);
});

it('huỷ món đã phục vụ: nhập sai PIN 5 lần thì lần thứ 6 bị khoá', function () {
    $this->item->update(['status' => OrderItemStatus::Served, 'served_at' => now()]);

    foreach (range(1, 5) as $lanThu) {
        huyMonThuPinSai($this->owner, $this->order, $this->item, $this->thuNgan, '9999')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Mã PIN không đúng.', "Lần thử thứ {$lanThu} phải báo sai PIN.");
    }

    huyMonThuPinSai($this->owner, $this->order, $this->item, $this->thuNgan, '9999')
        ->assertUnprocessable()
        ->assertJsonPath(
            'message',
            'Bạn đã nhập sai mã PIN quá nhiều lần. Đợi 15 phút rồi thử lại.',
            'Sai 5 lần rồi mà lần thứ 6 vẫn chỉ báo "Mã PIN không đúng" nghĩa là bộ đếm '
            .'đang bị quay lui cùng giao dịch — PIN lại đang được xác thực bên trong '
            .'DB::transaction. Xem CLAUDE.md mục 12.'
        );

    expect($this->item->refresh()->status)->toBe(OrderItemStatus::Served);
});
