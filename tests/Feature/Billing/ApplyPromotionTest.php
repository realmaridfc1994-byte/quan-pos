<?php

declare(strict_types=1);

use App\Domain\Billing\Actions\ApplyPromotion;
use App\Domain\Billing\DTO\ApplyPromotionData;
use App\Domain\Billing\Models\Promotion;
use App\Domain\Catalog\Enums\Station;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Ordering\Enums\TableSessionStatus;
use App\Domain\Ordering\Models\Order;
use App\Domain\Ordering\Models\OrderItem;
use App\Domain\Ordering\Models\TableSession;
use App\Domain\Staffing\Models\Shift;
use App\Domain\Staffing\Models\User;
use App\Exceptions\DomainException;
use Illuminate\Support\Carbon;

/** Tạo một lượt khách mở, có đúng $subtotal tiền món thật (order + order_item) để RecalculateSessionSubtotal tính đúng. */
function luotCoTamTinh(int $subtotal, ?Category $nhom = null, ?Product $mon = null): TableSession
{
    $ca = Shift::factory()->open()->create();
    $session = TableSession::factory()->withTable()->create(['shift_id' => $ca->id]);

    $nhom ??= Category::factory()->create(['station' => Station::Bar]);
    $mon ??= Product::factory()->for($nhom)->create();

    $don = Order::factory()->for($session, 'tableSession')->create();
    OrderItem::factory()->for($don, 'order')->create([
        'product_id' => $mon->id,
        'unit_price' => $subtotal,
        'options_amount' => 0,
        'quantity' => 1,
    ]);

    return $session;
}

afterEach(function () {
    Carbon::setTestNow();
});

it('giờ vàng 17h-19h giảm 20% bia: gọi lúc 18h được giảm', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-04 18:00:00'));

    $session = luotCoTamTinh(100_000);
    $khuyenMai = Promotion::factory()->happyHour(20, ['from' => '17:00', 'to' => '19:00'])->create();
    $nguoi = User::factory()->cashier()->create();

    $ketQua = app(ApplyPromotion::class)->handle(new ApplyPromotionData($session->id, $khuyenMai->code, $nguoi->id));

    expect($ketQua->discount_amount)->toBe(20_000)
        ->and($ketQua->total_amount)->toBe(80_000)
        ->and($ketQua->promotion_id)->toBe($khuyenMai->id)
        ->and($khuyenMai->refresh()->used_count)->toBe(1);
});

it('giờ vàng 17h-19h giảm 20% bia: gọi lúc 20h KHÔNG được giảm', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-04 20:00:00'));

    $session = luotCoTamTinh(100_000);
    $khuyenMai = Promotion::factory()->happyHour(20, ['from' => '17:00', 'to' => '19:00'])->create();
    $nguoi = User::factory()->cashier()->create();

    expect(fn () => app(ApplyPromotion::class)->handle(new ApplyPromotionData($session->id, $khuyenMai->code, $nguoi->id)))
        ->toThrow(DomainException::class);

    $session->refresh();
    expect($session->promotion_id)->toBeNull()
        ->and($session->discount_amount)->toBe(0)
        ->and($khuyenMai->refresh()->used_count)->toBe(0);
});

it('khuyến mãi hết hạn thì không áp dụng được', function () {
    $session = luotCoTamTinh(100_000);
    $khuyenMai = Promotion::factory()->percent(10)->create(['ends_at' => now()->subDay()]);
    $nguoi = User::factory()->cashier()->create();

    expect(fn () => app(ApplyPromotion::class)->handle(new ApplyPromotionData($session->id, $khuyenMai->code, $nguoi->id)))
        ->toThrow(DomainException::class);

    expect($session->refresh()->promotion_id)->toBeNull();
});

it('khuyến mãi chưa bắt đầu thì không áp dụng được', function () {
    $session = luotCoTamTinh(100_000);
    $khuyenMai = Promotion::factory()->percent(10)->create(['starts_at' => now()->addDay()]);
    $nguoi = User::factory()->cashier()->create();

    expect(fn () => app(ApplyPromotion::class)->handle(new ApplyPromotionData($session->id, $khuyenMai->code, $nguoi->id)))
        ->toThrow(DomainException::class);
});

it('khuyến mãi đã tắt thì không áp dụng được', function () {
    $session = luotCoTamTinh(100_000);
    $khuyenMai = Promotion::factory()->percent(10)->ngungHoatDong()->create();
    $nguoi = User::factory()->cashier()->create();

    expect(fn () => app(ApplyPromotion::class)->handle(new ApplyPromotionData($session->id, $khuyenMai->code, $nguoi->id)))
        ->toThrow(DomainException::class);
});

it('khuyến mãi vượt max_usage thì không áp dụng được', function () {
    $session = luotCoTamTinh(100_000);
    $khuyenMai = Promotion::factory()->percent(10)->create(['max_usage' => 3, 'used_count' => 3]);
    $nguoi = User::factory()->cashier()->create();

    expect(fn () => app(ApplyPromotion::class)->handle(new ApplyPromotionData($session->id, $khuyenMai->code, $nguoi->id)))
        ->toThrow(DomainException::class);

    expect($khuyenMai->refresh()->used_count)->toBe(3);
});

it('áp khuyến mãi thứ hai khi đã có một cái thì bị chặn', function () {
    $session = luotCoTamTinh(100_000);
    $km1 = Promotion::factory()->percent(10)->create();
    $km2 = Promotion::factory()->percent(5)->create();
    $nguoi = User::factory()->cashier()->create();

    app(ApplyPromotion::class)->handle(new ApplyPromotionData($session->id, $km1->code, $nguoi->id));

    expect(fn () => app(ApplyPromotion::class)->handle(new ApplyPromotionData($session->id, $km2->code, $nguoi->id)))
        ->toThrow(DomainException::class);

    $session->refresh();
    expect($session->promotion_id)->toBe($km1->id)
        ->and($session->discount_amount)->toBe(10_000)
        ->and($km2->refresh()->used_count)->toBe(0);
});

it('khuyến mãi làm total xuống dưới paid_amount thì bị chặn, không đổi gì', function () {
    $ca = Shift::factory()->open()->create();
    $session = TableSession::factory()->withTable()->create([
        'shift_id' => $ca->id,
        'status' => TableSessionStatus::Billing,
        'paid_amount' => 95_000,
    ]);
    $mon = Product::factory()->create();
    $don = Order::factory()->for($session, 'tableSession')->create();
    OrderItem::factory()->for($don, 'order')->create(['product_id' => $mon->id, 'unit_price' => 100_000, 'options_amount' => 0, 'quantity' => 1]);

    // Giảm 10% của 100.000 = 10.000 → tổng còn 90.000, THẤP HƠN 95.000 đã thu.
    $khuyenMai = Promotion::factory()->percent(10)->create();
    $nguoi = User::factory()->owner()->create();

    expect(fn () => app(ApplyPromotion::class)->handle(new ApplyPromotionData($session->id, $khuyenMai->code, $nguoi->id)))
        ->toThrow(DomainException::class);

    $session->refresh();
    expect($session->promotion_id)->toBeNull()
        ->and($session->discount_amount)->toBe(0)
        ->and($khuyenMai->refresh()->used_count)->toBe(0);
});

it('khuyến mãi giảm % vượt 20% vẫn không cần PIN duyệt — thu ngân tự áp được', function () {
    $session = luotCoTamTinh(500_000);
    // 50% > ngưỡng 20% của thu ngân — nếu là giảm giá tay sẽ bị chặn đòi PIN.
    $khuyenMai = Promotion::factory()->percent(50)->create();
    $thuNgan = User::factory()->cashier()->create();

    $ketQua = app(ApplyPromotion::class)->handle(new ApplyPromotionData($session->id, $khuyenMai->code, $thuNgan->id));

    expect($ketQua->discount_amount)->toBe(250_000);
});

it('không tìm thấy mã khuyến mãi thì báo rõ, không áp gì', function () {
    $session = luotCoTamTinh(100_000);
    $nguoi = User::factory()->cashier()->create();

    expect(fn () => app(ApplyPromotion::class)->handle(new ApplyPromotionData($session->id, 'KHONG-TON-TAI', $nguoi->id)))
        ->toThrow(DomainException::class);
});

it('chưa đạt tạm tính tối thiểu thì không áp dụng được', function () {
    $session = luotCoTamTinh(50_000);
    $khuyenMai = Promotion::factory()->percent(10)->create(['min_order_amount' => 200_000]);
    $nguoi = User::factory()->cashier()->create();

    expect(fn () => app(ApplyPromotion::class)->handle(new ApplyPromotionData($session->id, $khuyenMai->code, $nguoi->id)))
        ->toThrow(DomainException::class);
});

it('trần số tiền được giảm chặn đúng mức, không giảm vượt', function () {
    $session = luotCoTamTinh(1_000_000);
    $khuyenMai = Promotion::factory()->percent(50)->create(['max_discount_amount' => 100_000]);
    $nguoi = User::factory()->cashier()->create();

    $ketQua = app(ApplyPromotion::class)->handle(new ApplyPromotionData($session->id, $khuyenMai->code, $nguoi->id));

    expect($ketQua->discount_amount)->toBe(100_000);
});

it('khuyến mãi giảm tiền cố định không vượt quá tạm tính', function () {
    $session = luotCoTamTinh(30_000);
    $khuyenMai = Promotion::factory()->amount(50_000)->create();
    $nguoi = User::factory()->cashier()->create();

    $ketQua = app(ApplyPromotion::class)->handle(new ApplyPromotionData($session->id, $khuyenMai->code, $nguoi->id));

    expect($ketQua->discount_amount)->toBe(30_000)
        ->and($ketQua->total_amount)->toBe(0);
});

it('khuyến mãi áp cho một nhóm món chỉ tính trên món thuộc nhóm đó', function () {
    $nhomBia = Category::factory()->create(['station' => Station::Bar]);
    $nhomMon = Category::factory()->create(['station' => Station::Kitchen]);
    $bia = Product::factory()->for($nhomBia)->create();
    $moiNhau = Product::factory()->for($nhomMon)->create();

    $ca = Shift::factory()->open()->create();
    $session = TableSession::factory()->withTable()->create(['shift_id' => $ca->id]);
    $don = Order::factory()->for($session, 'tableSession')->create();
    OrderItem::factory()->for($don, 'order')->create(['product_id' => $bia->id, 'unit_price' => 100_000, 'options_amount' => 0, 'quantity' => 1]);
    OrderItem::factory()->for($don, 'order')->create(['product_id' => $moiNhau->id, 'unit_price' => 200_000, 'options_amount' => 0, 'quantity' => 1]);

    $khuyenMai = Promotion::factory()->percent(10)->choDanhMuc($nhomBia->id)->create();
    $nguoi = User::factory()->cashier()->create();

    $ketQua = app(ApplyPromotion::class)->handle(new ApplyPromotionData($session->id, $khuyenMai->code, $nguoi->id));

    // Chỉ 10% của 100.000 (bia), KHÔNG tính trên 200.000 (mồi).
    expect($ketQua->discount_amount)->toBe(10_000);
});
