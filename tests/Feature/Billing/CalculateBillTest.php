<?php

declare(strict_types=1);

use App\Domain\Billing\Enums\PaymentMethod;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Ordering\Actions\RecalculateSessionSubtotal;
use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Enums\TableSessionStatus;
use App\Domain\Ordering\Models\Order;
use App\Domain\Ordering\Models\OrderItem;
use App\Domain\Ordering\Models\TableSession;
use App\Domain\Staffing\Models\Shift;
use App\Domain\Staffing\Models\User;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

function giamGia(User $user, TableSession $luot, array $payload): TestResponse
{
    return test()->postJson(
        "/api/v1/table-sessions/{$luot->id}/discount",
        $payload,
        array_merge(authHeaderFor($user), ['Idempotency-Key' => (string) Str::uuid()])
    );
}

function thuTruoc(User $user, TableSession $luot, int $soTien): TestResponse
{
    return test()->postJson(
        "/api/v1/table-sessions/{$luot->id}/payments",
        [
            'uuid' => (string) Str::uuid(),
            'method' => PaymentMethod::Cash->value,
            'amount' => $soTien,
            'tendered_amount' => $soTien,
        ],
        array_merge(authHeaderFor($user), ['Idempotency-Key' => (string) Str::uuid()])
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

    $order = Order::factory()->for($this->luot, 'tableSession')->create(['status' => OrderStatus::Sent]);
    OrderItem::factory()->for($order)->create([
        'product_id' => $variant->product_id,
        'product_variant_id' => $variant->id,
        'unit_price' => 100_000,
        'options_amount' => 0,
        'quantity' => 5, // tạm tính 500.000
    ]);

    app(RecalculateSessionSubtotal::class)->handle($this->luot);
});

it('T2: tạm tính tính lại đúng từ dòng món hiện có', function () {
    giamGia($this->owner, $this->luot, ['discount_amount' => 0])
        ->assertOk()
        ->assertJsonPath('data.subtotal_amount', 500_000)
        ->assertJsonPath('data.total_amount', 500_000);
});

it('T3+T4: chủ quán giảm giá có lý do thì tổng phải thu giảm đúng, không âm', function () {
    giamGia($this->owner, $this->luot, ['discount_amount' => 500_000, 'discount_reason' => 'Khách quen'])
        ->assertOk()
        ->assertJsonPath('data.total_amount', 0);

    expect($this->luot->refresh()->discount_amount)->toBe(500_000);
});

it('T4: giảm giá không ghi lý do thì bị chặn', function () {
    giamGia($this->owner, $this->luot, ['discount_amount' => 50_000, 'discount_reason' => ''])
        ->assertUnprocessable();

    expect($this->luot->refresh()->discount_amount)->toBe(0);
});

it('T3: giảm giá vượt quá tạm tính thì bị chặn', function () {
    giamGia($this->owner, $this->luot, ['discount_amount' => 600_000, 'discount_reason' => 'Quá tay'])
        ->assertUnprocessable();

    expect($this->luot->refresh()->discount_amount)->toBe(0);
});

it('thu ngân giảm giá trong mức 20% thì không cần duyệt', function () {
    giamGia($this->thuNgan, $this->luot, ['discount_amount' => 100_000, 'discount_reason' => 'Bớt lẻ'])
        ->assertOk()
        ->assertJsonPath('data.total_amount', 400_000);
});

it('thu ngân giảm giá vượt 20% mà không có PIN duyệt thì bị chặn', function () {
    giamGia($this->thuNgan, $this->luot, ['discount_amount' => 150_000, 'discount_reason' => 'Khách quen'])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Giảm giá vượt mức cho phép, phải có người duyệt bằng mã PIN.');

    expect($this->luot->refresh()->discount_amount)->toBe(0);
});

it('thu ngân giảm giá vượt 20% có PIN duyệt đúng của chủ quán thì được', function () {
    giamGia($this->thuNgan, $this->luot, [
        'discount_amount' => 150_000,
        'discount_reason' => 'Khách quen',
        'approver_user_id' => $this->owner->id,
        'approver_pin' => '1111',
    ])->assertOk()->assertJsonPath('data.total_amount', 350_000);
});

it('nhân viên không được tự giảm giá, vượt mức cần PIN duyệt', function () {
    $staff = User::factory()->staff()->create();

    giamGia($staff, $this->luot, ['discount_amount' => 10_000, 'discount_reason' => 'Test'])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Giảm giá vượt mức cho phép, phải có người duyệt bằng mã PIN.');
});

it('PIN duyệt sai thì không giảm giá được', function () {
    giamGia($this->thuNgan, $this->luot, [
        'discount_amount' => 150_000,
        'discount_reason' => 'Khách quen',
        'approver_user_id' => $this->owner->id,
        'approver_pin' => '9999',
    ])->assertUnprocessable();

    expect($this->luot->refresh()->discount_amount)->toBe(0);
});

it('bếp không có quyền gọi API giảm giá', function () {
    $bep = User::factory()->kitchen()->create();

    giamGia($bep, $this->luot, ['discount_amount' => 0])->assertForbidden();
});

it('không giảm giá được xuống dưới số tiền khách đã trả', function () {
    thuTruoc($this->owner, $this->luot, 300_000)->assertCreated();

    giamGia($this->owner, $this->luot, ['discount_amount' => 250_000, 'discount_reason' => 'Khách quen'])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Không giảm được xuống 250.000 đ vì khách đã trả 300.000 đ. Muốn giảm thêm thì phải huỷ bớt phiếu thu trước.');

    $this->luot->refresh();
    expect($this->luot->discount_amount)->toBe(0)
        ->and($this->luot->total_amount)->toBe(500_000)
        ->and($this->luot->paid_amount)->toBe(300_000);
});

it('MỤC A: ngưỡng 20% tính trên subtotal và trên TỔNG discount_amount, không phải cộng dồn từng lần bấm', function () {
    // Thu ngân giảm 15% subtotal (75.000) → được.
    giamGia($this->thuNgan, $this->luot, ['discount_amount' => 75_000, 'discount_reason' => 'Lần 1'])
        ->assertOk()
        ->assertJsonPath('data.total_amount', 425_000);

    expect($this->luot->refresh()->discount_amount)->toBe(75_000);

    // Ngay sau đó giảm thêm tới TỔNG 25% subtotal (125.000, không phải +10% chồng lên 15% cũ)
    // → bị chặn, vì 125.000/500.000 = 25% > 20%. discount_amount lần 1 giữ nguyên, không bị đè.
    giamGia($this->thuNgan, $this->luot, ['discount_amount' => 125_000, 'discount_reason' => 'Lần 2'])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Giảm giá vượt mức cho phép, phải có người duyệt bằng mã PIN.');

    expect($this->luot->refresh()->discount_amount)->toBe(75_000);
});

it('MỤC A: chủ quán không giới hạn — làm đúng hai bước 15% rồi 25% ở trên vẫn được cả hai', function () {
    giamGia($this->owner, $this->luot, ['discount_amount' => 75_000, 'discount_reason' => 'Lần 1'])->assertOk();
    giamGia($this->owner, $this->luot, ['discount_amount' => 125_000, 'discount_reason' => 'Lần 2'])->assertOk();

    expect($this->luot->refresh()->discount_amount)->toBe(125_000);
});

it('giảm giá xuống đúng bằng số tiền đã trả thì được, và lượt khách đóng luôn vì đã thu đủ', function () {
    thuTruoc($this->owner, $this->luot, 300_000)->assertCreated();

    giamGia($this->owner, $this->luot, ['discount_amount' => 200_000, 'discount_reason' => 'Bớt cho tròn'])
        ->assertOk()
        ->assertJsonPath('data.total_amount', 300_000)
        ->assertJsonPath('data.status', 'closed');

    $this->luot->refresh();
    expect($this->luot->status)->toBe(TableSessionStatus::Closed)
        ->and($this->luot->paid_amount)->toBe(300_000)
        ->and($this->luot->total_amount)->toBe(300_000);
});

it('chưa thu đồng nào thì giảm giá bình thường, không bị chặn', function () {
    giamGia($this->owner, $this->luot, ['discount_amount' => 400_000, 'discount_reason' => 'Khách quen'])
        ->assertOk()
        ->assertJsonPath('data.total_amount', 100_000);

    expect($this->luot->refresh()->discount_amount)->toBe(400_000);
});
