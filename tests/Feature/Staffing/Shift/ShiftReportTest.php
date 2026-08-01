<?php

declare(strict_types=1);

use App\Domain\Billing\Models\Payment;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Ordering\Enums\OrderItemStatus;
use App\Domain\Ordering\Models\Order;
use App\Domain\Ordering\Models\OrderItem;
use App\Domain\Ordering\Models\TableSession;
use App\Domain\Staffing\Enums\ShiftStatus;
use App\Domain\Staffing\Models\Shift;
use App\Domain\Staffing\Models\User;
use Illuminate\Testing\TestResponse;

function xemBaoCaoCa(User $user, Shift $ca): TestResponse
{
    $token = $user->createToken('pos-app')->plainTextToken;

    return test()->getJson("/api/v1/shifts/{$ca->id}/report", [
        'Authorization' => "Bearer {$token}",
    ]);
}

it('Z-report: doanh thu theo hình thức, số lượt khách, món huỷ và giảm giá đúng', function () {
    $owner = User::factory()->owner()->create();
    $thuNgan = User::factory()->cashier()->create();
    $ca = Shift::factory()->closed()->create([
        'opened_by_user_id' => $thuNgan->id,
        'opening_cash' => 500_000,
        'expected_cash' => 700_000,
        'counted_cash' => 680_000,
    ]);

    $luot1 = TableSession::factory()->for($ca, 'shift')->create([
        'subtotal_amount' => 170_000,
        'discount_amount' => 20_000,
        'discount_reason' => 'Khách quen',
        'total_amount' => 150_000,
    ]);
    $luot2 = TableSession::factory()->for($ca, 'shift')->create();

    Payment::factory()->for($luot1, 'tableSession')->for($ca, 'shift')
        ->cash()->completed()->create(['amount' => 150_000, 'tendered_amount' => 150_000, 'change_amount' => 0]);
    Payment::factory()->for($luot2, 'tableSession')->for($ca, 'shift')
        ->transfer()->completed()->create(['amount' => 80_000]);
    // Phiếu đã huỷ không được tính vào doanh thu.
    Payment::factory()->for($luot1, 'tableSession')->for($ca, 'shift')
        ->cash()->voided()->create(['amount' => 999_999, 'tendered_amount' => 999_999, 'change_amount' => 0]);

    $variant = ProductVariant::factory()->for(Product::factory()->for(Category::factory()))->create();
    $order = Order::factory()->for($luot1, 'tableSession')->create();
    OrderItem::factory()->for($order)->create([
        'product_id' => $variant->product_id,
        'product_variant_id' => $variant->id,
        'product_name' => 'Gà nướng',
        'variant_name' => 'Phần',
        'status' => OrderItemStatus::Cancelled,
        'cancelled_by_user_id' => $thuNgan->id,
        'cancelled_at' => now(),
        'cancel_reason' => 'Khách trả món',
    ]);

    $ketQua = xemBaoCaoCa($owner, $ca)->assertOk();

    $ketQua->assertJsonPath('data.so_luot_khach', 2)
        ->assertJsonPath('data.doanh_thu.tien_mat', 150_000)
        ->assertJsonPath('data.doanh_thu.chuyen_khoan', 80_000)
        ->assertJsonPath('data.doanh_thu.tong', 230_000)
        ->assertJsonPath('data.mon_da_huy.0.product_name', 'Gà nướng')
        ->assertJsonPath('data.mon_da_huy.0.cancel_reason', 'Khách trả món')
        ->assertJsonPath('data.mon_da_huy.0.cancelled_by', $thuNgan->name)
        ->assertJsonPath('data.giam_gia.0.table_session_code', $luot1->code)
        ->assertJsonPath('data.giam_gia.0.discount_amount', 20_000)
        ->assertJsonPath('data.giam_gia.0.discount_reason', 'Khách quen')
        ->assertJsonPath('data.doi_soat_ket.expected_cash', 700_000)
        ->assertJsonPath('data.doi_soat_ket.counted_cash', 680_000)
        ->assertJsonPath('data.doi_soat_ket.variance_text', 'Thiếu 20.000 đ');
});

it('Z-report: ca chưa đóng thì phần đối soát két là null', function () {
    $owner = User::factory()->owner()->create();
    $ca = Shift::factory()->open()->create();

    xemBaoCaoCa($owner, $ca)
        ->assertOk()
        ->assertJsonPath('data.shift.status', ShiftStatus::Open->value)
        ->assertJsonPath('data.doi_soat_ket', null);
});

it('thu ngân xem được báo cáo cuối ca của người khác', function () {
    $thuNgan = User::factory()->cashier()->create();
    $ca = Shift::factory()->closed()->create();

    xemBaoCaoCa($thuNgan, $ca)->assertOk();
});

it('nhân viên phục vụ không có quyền xem báo cáo doanh thu', function () {
    $staff = User::factory()->staff()->create();
    $ca = Shift::factory()->closed()->create(['opened_by_user_id' => $staff->id]);

    xemBaoCaoCa($staff, $ca)->assertForbidden();
});

it('bếp không có quyền xem báo cáo doanh thu', function () {
    $bep = User::factory()->kitchen()->create();
    $ca = Shift::factory()->closed()->create();

    xemBaoCaoCa($bep, $ca)->assertForbidden();
});
