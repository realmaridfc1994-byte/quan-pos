<?php

declare(strict_types=1);

use App\Domain\Billing\Enums\PaymentMethod;
use App\Domain\Billing\Models\Payment;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Enums\TableSessionStatus;
use App\Domain\Ordering\Models\Order;
use App\Domain\Ordering\Models\OrderItem;
use App\Domain\Ordering\Models\TableSession;
use App\Domain\Staffing\Models\Shift;
use App\Domain\Staffing\Models\User;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/**
 * Ghi chú thật thà (giống tests/Feature/Ordering/TableConcurrencyTest.php): Pest
 * ở dự án này chạy mỗi test trong một transaction bọc ngoài trên một kết nối
 * database duy nhất — không có cách nào tạo ra hai request THẬT chạm khoá cùng
 * lúc mà không dựng thêm tiến trình PHP thứ hai (nặng, không hợp quy mô quán).
 * Test dưới đây mô phỏng bằng hai request gửi LIÊN TIẾP — đúng hành vi người
 * dùng thật thấy khi hai máy "đụng" nhau: người thứ hai luôn nhận đúng thông
 * báo nghiệp vụ tiếng Việt, không phải lỗi database thô hay số tiền cộng sai.
 * Chốt chặn thật cho hai request đến CÙNG lúc là lockForUpdate() trong cùng
 * DB::transaction() của RecordPayment/CalculateBill — test này xác nhận rằng
 * logic đọc-lại-trước-khi-ghi phía trong khoá đó cho ra đúng kết quả nghiệp
 * vụ, không phải chỉ "có mặt trong code".
 */
function thuTienConcurrency(User $user, TableSession $luot, array $payload): TestResponse
{
    return test()->postJson(
        "/api/v1/table-sessions/{$luot->id}/payments",
        $payload,
        array_merge(authHeaderFor($user), ['Idempotency-Key' => (string) Str::uuid()])
    );
}

function giamGiaConcurrency(User $user, TableSession $luot, array $payload): TestResponse
{
    return test()->postJson(
        "/api/v1/table-sessions/{$luot->id}/discount",
        $payload,
        array_merge(authHeaderFor($user), ['Idempotency-Key' => (string) Str::uuid()])
    );
}

beforeEach(function () {
    $this->ca = Shift::factory()->open()->create();
    $this->thuNgan = User::factory()->cashier()->create();
    $this->luot = TableSession::factory()
        ->for($this->ca, 'shift')
        ->create([
            'status' => TableSessionStatus::Billing,
            'subtotal_amount' => 500_000,
            'discount_amount' => 0,
            'total_amount' => 500_000,
            'paid_amount' => 300_000, // còn thiếu đúng 200.000
        ]);

    // Dòng món thật — CalculateBill luôn tính lại subtotal từ đây, cần khớp với
    // subtotal_amount đã đặt sẵn ở trên, không thì test giảm giá sẽ thấy tạm tính 0.
    $variant = ProductVariant::factory()
        ->for(Product::factory()->for(Category::factory()))
        ->create(['price' => 500_000]);
    $order = Order::factory()->for($this->luot, 'tableSession')->create(['status' => OrderStatus::Sent]);
    OrderItem::factory()->for($order)->create([
        'product_id' => $variant->product_id,
        'product_variant_id' => $variant->id,
        'unit_price' => 500_000,
        'options_amount' => 0,
        'quantity' => 1,
    ]);
});

it('hai tiến trình cùng thu 200.000 với hai uuid khác nhau — đúng một thành công, cái kia bị chặn, paid_amount không cộng trùng', function () {
    $payload = fn () => [
        'uuid' => (string) Str::uuid(),
        'method' => PaymentMethod::Cash->value,
        'amount' => 200_000,
        'tendered_amount' => 200_000,
    ];

    $ketQua1 = thuTienConcurrency($this->thuNgan, $this->luot, $payload());
    $ketQua2 = thuTienConcurrency($this->thuNgan, $this->luot, $payload());

    $daThanhCong = collect([$ketQua1, $ketQua2])->filter(fn ($r) => $r->status() === 201);
    $daThatBai = collect([$ketQua1, $ketQua2])->filter(fn ($r) => $r->status() === 422);

    expect($daThanhCong)->toHaveCount(1)
        ->and($daThatBai)->toHaveCount(1);

    // Người bấm sau nhận đúng thông báo nghiệp vụ tiếng Việt (không phải lỗi CHECK/UNIQUE thô ở DB):
    // lần đầu thu đủ 200.000 còn thiếu, lượt khách tự đóng ngay (T6) — người bấm sau
    // thấy lượt khách đã đóng rồi, không thu tiếp được.
    $daThatBai->first()->assertJsonPath('message', 'Lượt khách này đã đóng hoặc đã huỷ, không thu tiền được.');

    expect(Payment::query()->count())->toBe(1);
    expect($this->luot->refresh()->paid_amount)->toBe(500_000); // không phải 700.000
});

it('hai tiến trình cùng thu với CÙNG uuid — chỉ một phiếu thu duy nhất trong database', function () {
    $uuid = (string) Str::uuid();
    $payload = [
        'uuid' => $uuid,
        'method' => PaymentMethod::Cash->value,
        'amount' => 200_000,
        'tendered_amount' => 200_000,
    ];

    $ketQua1 = thuTienConcurrency($this->thuNgan, $this->luot, $payload);
    $ketQua2 = thuTienConcurrency($this->thuNgan, $this->luot, $payload);

    $ketQua1->assertCreated();
    $ketQua2->assertCreated()->assertJsonPath('data.id', $ketQua1->json('data.id'));

    expect(Payment::query()->count())->toBe(1)
        ->and(Payment::query()->sole()->uuid)->toBe($uuid)
        ->and($this->luot->refresh()->paid_amount)->toBe(500_000);
});

it('một tiến trình thu tiền, một tiến trình giảm giá cùng lúc — kết quả cuối không có trạng thái nửa vời', function () {
    // Tiến trình A: thu thêm 150.000 (chưa đủ, còn thiếu 50.000) — lượt khách vẫn
    // "billing", cố tình KHÔNG cho đóng luôn để tiến trình B còn cơ hội chạy vào
    // đúng lúc lượt khách đang ở trạng thái vừa bị tiến trình A đổi paid_amount.
    thuTienConcurrency($this->thuNgan, $this->luot, [
        'uuid' => (string) Str::uuid(),
        'method' => PaymentMethod::Cash->value,
        'amount' => 150_000,
        'tendered_amount' => 150_000,
    ])->assertCreated();

    $owner = User::factory()->owner()->create();

    // Tiến trình B: cùng lúc đó, chủ quán (không giới hạn %, để cô lập đúng phần
    // đang kiểm — không lẫn với chặn theo ngưỡng vai trò) cố giảm giá 50% —
    // CalculateBill khoá lượt khách, đọc LẠI paid_amount (450.000, đã bị tiến
    // trình A cập nhật) trước khi ghi, nên phát hiện giảm xuống 250.000 sẽ THẤP
    // HƠN số đã thu và chặn lại — không phải đọc số cũ (300.000) rồi ghi đè ra
    // một trạng thái sai.
    giamGiaConcurrency($owner, $this->luot, [
        'discount_amount' => 250_000,
        'discount_reason' => 'Khách quen',
    ])->assertUnprocessable()
        ->assertJsonPath('message', 'Không giảm được xuống 250.000 đ vì khách đã trả 450.000 đ. Muốn giảm thêm thì phải huỷ bớt phiếu thu trước.');

    // Kết quả cuối cùng nhất quán: total = subtotal - discount (ck_table_sessions_total),
    // không có nửa-đã-giảm-nửa-chưa.
    $this->luot->refresh();
    expect($this->luot->discount_amount)->toBe(0)
        ->and($this->luot->total_amount)->toBe(500_000)
        ->and($this->luot->subtotal_amount)->toBe(500_000)
        ->and($this->luot->total_amount + $this->luot->discount_amount)->toBe($this->luot->subtotal_amount)
        ->and($this->luot->paid_amount)->toBe(450_000)
        ->and($this->luot->status)->toBe(TableSessionStatus::Billing);
});
