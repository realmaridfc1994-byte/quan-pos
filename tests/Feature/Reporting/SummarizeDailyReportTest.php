<?php

declare(strict_types=1);

/**
 * Phase 2 Bước 8 — SummarizeDailyReport phải tổng hợp khớp ĐÚNG BẰNG tổng
 * tính trực tiếp từ orders/order_items/payments/table_sessions/shifts (đây
 * là chỗ duy nhất trong toàn bộ tính năng ĐƯỢC PHÉP so sánh với các bảng gốc
 * — vì đang kiểm chính việc tổng hợp, không phải màn hình hiển thị).
 */

use App\Domain\Billing\Enums\PaymentMethod;
use App\Domain\Billing\Enums\PaymentStatus;
use App\Domain\Billing\Models\Payment;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Ordering\Enums\OrderItemStatus;
use App\Domain\Ordering\Models\Order;
use App\Domain\Ordering\Models\OrderItem;
use App\Domain\Ordering\Models\TableSession;
use App\Domain\Reporting\Actions\SummarizeDailyReport;
use App\Domain\Reporting\Models\DailySummary;
use App\Domain\Reporting\Models\ProductSaleDaily;
use App\Domain\Staffing\Enums\ShiftStatus;
use App\Domain\Staffing\Models\Shift;
use App\Domain\Staffing\Models\User;
use Illuminate\Support\Carbon;

it('tổng hợp một ngày khớp đúng bằng tổng tính trực tiếp từ orders và payments', function () {
    $ngay = Carbon::parse('2026-08-03');

    $ca = Shift::factory()->closed()->create([
        'opened_at' => $ngay->clone()->setTime(18, 0),
        'opening_cash' => 500_000,
        'counted_cash' => 980_000,
        'expected_cash' => 1_000_000, // thiếu 20.000
    ]);

    $session1 = TableSession::factory()->withTable()->create([
        'shift_id' => $ca->id,
        'opened_at' => $ngay->clone()->setTime(18, 10),
        'guest_count' => 4,
        'subtotal_amount' => 100_000,
        'discount_amount' => 10_000,
        'discount_reason' => 'Khách quen',
        'total_amount' => 90_000,
    ]);
    $session2 = TableSession::factory()->withTable()->create([
        'shift_id' => $ca->id,
        'opened_at' => $ngay->clone()->setTime(20, 0),
        'guest_count' => 2,
    ]);

    $category = Category::factory()->create();
    $bia = Product::factory()->for($category)->create();
    $bienTheBia = ProductVariant::factory()->for($bia)->create(['price' => 25_000]);
    $ga = Product::factory()->for($category)->create();
    $bienTheGa = ProductVariant::factory()->for($ga)->create(['price' => 120_000]);

    $don1 = Order::factory()->for($session1, 'tableSession')->create(['sent_at' => $ngay->clone()->setTime(18, 15)]);
    OrderItem::factory()->for($don1, 'order')->create([
        'product_id' => $bia->id, 'product_variant_id' => $bienTheBia->id,
        'unit_price' => 25_000, 'options_amount' => 0, 'quantity' => 4, 'status' => OrderItemStatus::Served,
    ]);
    // Món huỷ — KHÔNG được tính vào doanh thu theo món, nhưng CÓ tính vào "giá trị món huỷ".
    OrderItem::factory()->for($don1, 'order')->create([
        'product_id' => $ga->id, 'product_variant_id' => $bienTheGa->id,
        'unit_price' => 120_000, 'options_amount' => 0, 'quantity' => 1, 'status' => OrderItemStatus::Cancelled,
        'cancelled_at' => $ngay->clone()->setTime(18, 20), 'cancel_reason' => 'Khách đổi ý',
        'cancelled_by_user_id' => User::factory(),
    ]);

    $don2 = Order::factory()->for($session2, 'tableSession')->create(['sent_at' => $ngay->clone()->setTime(20, 5)]);
    OrderItem::factory()->for($don2, 'order')->create([
        'product_id' => $bia->id, 'product_variant_id' => $bienTheBia->id,
        'unit_price' => 25_000, 'options_amount' => 0, 'quantity' => 2, 'status' => OrderItemStatus::Served,
    ]);

    Payment::factory()->for($session1, 'tableSession')->for($ca, 'shift')->create([
        'method' => PaymentMethod::Cash, 'amount' => 90_000, 'tendered_amount' => 90_000, 'change_amount' => 0,
        'status' => PaymentStatus::Completed, 'paid_at' => $ngay->clone()->setTime(19, 0),
    ]);
    Payment::factory()->for($session2, 'tableSession')->for($ca, 'shift')->create([
        'method' => PaymentMethod::Transfer, 'amount' => 50_000, 'tendered_amount' => null, 'change_amount' => 0,
        'status' => PaymentStatus::Completed, 'paid_at' => $ngay->clone()->setTime(20, 30),
    ]);
    // Phiếu thu đã huỷ — KHÔNG được tính vào doanh thu.
    Payment::factory()->for($session2, 'tableSession')->for($ca, 'shift')->create([
        'method' => PaymentMethod::Cash, 'amount' => 999_999, 'tendered_amount' => 999_999, 'change_amount' => 0,
        'status' => PaymentStatus::Voided,
        'paid_at' => $ngay->clone()->setTime(20, 31), 'voided_at' => now(), 'void_reason' => 'Thu nhầm',
        'voided_by_user_id' => User::factory(),
    ]);

    $tomTat = app(SummarizeDailyReport::class)->handle($ngay->toDateString());

    // ── Đối chiếu bằng tổng tính TRỰC TIẾP từ orders/payments ────────────
    $doanhThuThat = Payment::query()->where('status', PaymentStatus::Completed)->whereDate('paid_at', $ngay)->sum('amount');
    $tienMatThat = Payment::query()->where('status', PaymentStatus::Completed)->where('method', PaymentMethod::Cash)->whereDate('paid_at', $ngay)->sum('amount');
    $chuyenKhoanThat = Payment::query()->where('status', PaymentStatus::Completed)->where('method', PaymentMethod::Transfer)->whereDate('paid_at', $ngay)->sum('amount');
    $soLuotKhachThat = TableSession::query()->whereDate('opened_at', $ngay)->count();
    $soKhachThat = TableSession::query()->whereDate('opened_at', $ngay)->sum('guest_count');
    $giamGiaThat = TableSession::query()->whereDate('opened_at', $ngay)->sum('discount_amount');
    $soMonHuyThat = OrderItem::query()->where('status', OrderItemStatus::Cancelled)->whereDate('cancelled_at', $ngay)->count();
    $giaTriMonHuyThat = OrderItem::query()->where('status', OrderItemStatus::Cancelled)->whereDate('cancelled_at', $ngay)->sum('line_amount');
    $chenhLechThat = Shift::query()->where('status', ShiftStatus::Closed)->whereDate('opened_at', $ngay)->get()->sum(fn ($s) => $s->counted_cash - $s->expected_cash);

    expect($tomTat->revenue_amount)->toBe((int) $doanhThuThat)
        ->and($tomTat->cash_amount)->toBe((int) $tienMatThat)
        ->and($tomTat->transfer_amount)->toBe((int) $chuyenKhoanThat)
        ->and($tomTat->table_session_count)->toBe($soLuotKhachThat)
        ->and($tomTat->guest_count)->toBe((int) $soKhachThat)
        ->and($tomTat->discount_amount)->toBe((int) $giamGiaThat)
        ->and($tomTat->cancelled_item_count)->toBe($soMonHuyThat)
        ->and($tomTat->cancelled_item_amount)->toBe((int) $giaTriMonHuyThat)
        ->and($tomTat->cash_variance_amount)->toBe((int) $chenhLechThat);

    // ── Giá trị cụ thể, dễ đọc ────────────────────────────────────────────
    expect($tomTat->revenue_amount)->toBe(140_000) // 90.000 + 50.000, KHÔNG cộng phiếu đã huỷ
        ->and($tomTat->table_session_count)->toBe(2)
        ->and($tomTat->guest_count)->toBe(6)
        ->and($tomTat->discount_amount)->toBe(10_000)
        ->and($tomTat->cancelled_item_count)->toBe(1)
        ->and($tomTat->cancelled_item_amount)->toBe(120_000)
        ->and($tomTat->cash_variance_amount)->toBe(-20_000);

    // ── product_sales_daily: 6 lon bia (4+2), KHÔNG có gà (đã huỷ) ───────
    $dongBia = ProductSaleDaily::query()->where('date', $ngay->toDateString())->where('product_id', $bia->id)->sole();
    expect($dongBia->quantity_sold)->toBe(6)
        ->and($dongBia->revenue_amount)->toBe(150_000);

    expect(ProductSaleDaily::query()->where('date', $ngay->toDateString())->where('product_id', $ga->id)->exists())->toBeFalse();
});

it('chạy lại cho cùng một ngày thì ghi đè, không cộng dồn', function () {
    $ngay = Carbon::parse('2026-08-03');
    $ca = Shift::factory()->closed()->create(['opened_at' => $ngay->clone()->setTime(18, 0)]);
    $session = TableSession::factory()->withTable()->create(['shift_id' => $ca->id, 'opened_at' => $ngay->clone()->setTime(18, 10)]);
    Payment::factory()->for($session, 'tableSession')->for($ca, 'shift')->create([
        'method' => PaymentMethod::Cash, 'amount' => 100_000, 'tendered_amount' => 100_000, 'change_amount' => 0,
        'status' => PaymentStatus::Completed, 'paid_at' => $ngay->clone()->setTime(19, 0),
    ]);

    $action = app(SummarizeDailyReport::class);
    $action->handle($ngay->toDateString());
    $lanHai = $action->handle($ngay->toDateString());

    expect(DailySummary::query()->where('date', $ngay->toDateString())->count())->toBe(1)
        ->and($lanHai->revenue_amount)->toBe(100_000);
});
