<?php

declare(strict_types=1);

use App\Domain\Billing\Enums\PaymentMethod;
use App\Domain\Billing\Models\Payment;
use App\Domain\Ordering\Enums\TableSessionStatus;
use App\Domain\Ordering\Models\TableSession;
use App\Domain\Staffing\Enums\ShiftStatus;
use App\Domain\Staffing\Models\Shift;
use App\Domain\Staffing\Models\User;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

function thuTien(User $user, TableSession $luot, array $payload, ?string $idempotencyKey = null): TestResponse
{
    $payload = array_merge(['uuid' => (string) Str::uuid()], $payload);

    return test()->postJson(
        "/api/v1/table-sessions/{$luot->id}/payments",
        $payload,
        array_merge(authHeaderFor($user), ['Idempotency-Key' => $idempotencyKey ?? (string) Str::uuid()])
    );
}

beforeEach(function () {
    $this->ca = Shift::factory()->open()->create();
    $this->thuNgan = User::factory()->cashier()->create();

    $this->luot = TableSession::factory()
        ->for($this->ca, 'shift')
        ->withTable()
        ->create([
            'status' => TableSessionStatus::Billing,
            'subtotal_amount' => 500_000,
            'discount_amount' => 0,
            'total_amount' => 500_000,
            'paid_amount' => 0,
        ]);
});

it('T1+T7: thu tiền mặt đủ thì đóng lượt khách và nhả bàn ra ngay', function () {
    thuTien($this->thuNgan, $this->luot, [
        'method' => PaymentMethod::Cash->value,
        'amount' => 500_000,
        'tendered_amount' => 500_000,
    ])
        ->assertCreated()
        ->assertJsonPath('data.amount', 500_000)
        ->assertJsonPath('data.change_amount', 0);

    $this->luot->refresh();
    expect($this->luot->paid_amount)->toBe(500_000)
        ->and($this->luot->status)->toBe(TableSessionStatus::Closed)
        ->and($this->luot->closed_at)->not->toBeNull()
        ->and($this->luot->tables()->whereNull('detached_at')->count())->toBe(0);
});

it('T7: khách đưa 600k thì thối lại 100k, doanh thu vẫn ghi 500k', function () {
    thuTien($this->thuNgan, $this->luot, [
        'method' => PaymentMethod::Cash->value,
        'amount' => 500_000,
        'tendered_amount' => 600_000,
    ])->assertCreated();

    $payment = Payment::query()->sole();
    expect($payment->amount)->toBe(500_000)
        ->and($payment->change_amount)->toBe(100_000)
        ->and($payment->tendered_amount)->toBe(600_000);
});

it('T9: thu hai lần với cùng Idempotency-Key chỉ ghi nhận một phiếu thu', function () {
    $key = (string) Str::uuid();
    $payload = ['uuid' => (string) Str::uuid(), 'method' => PaymentMethod::Cash->value, 'amount' => 200_000, 'tendered_amount' => 200_000];

    thuTien($this->thuNgan, $this->luot, $payload, $key)->assertCreated();
    thuTien($this->thuNgan, $this->luot, $payload, $key)->assertCreated();

    expect(Payment::query()->count())->toBe(1);
    expect($this->luot->refresh()->paid_amount)->toBe(200_000);
});

it('T9: cùng uuid nhưng khác Idempotency-Key vẫn chỉ ghi nhận một phiếu thu, trả về đúng phiếu cũ', function () {
    $uuid = (string) Str::uuid();
    $payload = ['uuid' => $uuid, 'method' => PaymentMethod::Cash->value, 'amount' => 200_000, 'tendered_amount' => 200_000];

    $lanDau = thuTien($this->thuNgan, $this->luot, $payload);
    $lanDau->assertCreated();
    $idPhieuThu = $lanDau->json('data.id');

    // Mô phỏng máy POS khởi động lại: Idempotency-Key cũ đã mất, gửi lại với key MỚI nhưng cùng uuid phiếu thu.
    $lanHai = thuTien($this->thuNgan, $this->luot, $payload);
    $lanHai->assertCreated()->assertJsonPath('data.id', $idPhieuThu);

    expect(Payment::query()->count())->toBe(1)
        ->and(Payment::query()->sole()->uuid)->toBe($uuid)
        ->and($this->luot->refresh()->paid_amount)->toBe(200_000); // chỉ cộng một lần
});

it('T9: hai uuid khác nhau thì ghi hai phiếu thu riêng biệt', function () {
    thuTien($this->thuNgan, $this->luot, [
        'uuid' => (string) Str::uuid(), 'method' => PaymentMethod::Cash->value, 'amount' => 200_000, 'tendered_amount' => 200_000,
    ])->assertCreated();

    thuTien($this->thuNgan, $this->luot, [
        'uuid' => (string) Str::uuid(), 'method' => PaymentMethod::Cash->value, 'amount' => 300_000, 'tendered_amount' => 300_000,
    ])->assertCreated();

    expect(Payment::query()->count())->toBe(2);
    expect($this->luot->refresh()->paid_amount)->toBe(500_000);
});

it('T6: thu thiếu tiền thì lượt khách vẫn mở, không đóng được', function () {
    thuTien($this->thuNgan, $this->luot, [
        'method' => PaymentMethod::Cash->value,
        'amount' => 300_000,
        'tendered_amount' => 300_000,
    ])->assertCreated();

    $this->luot->refresh();
    expect($this->luot->paid_amount)->toBe(300_000)
        ->and($this->luot->status)->toBe(TableSessionStatus::Billing);

    $dong = test()->postJson(
        "/api/v1/table-sessions/{$this->luot->id}/close",
        [],
        array_merge(authHeaderFor($this->thuNgan), ['Idempotency-Key' => (string) Str::uuid()])
    );
    $dong->assertUnprocessable()->assertJsonPath('message', 'Bàn chưa thu đủ tiền, không đóng được.');
});

it('thu tiền mặt mà khách đưa ít hơn tiền phải thu thì bị chặn', function () {
    thuTien($this->thuNgan, $this->luot, [
        'method' => PaymentMethod::Cash->value,
        'amount' => 500_000,
        'tendered_amount' => 400_000,
    ])->assertUnprocessable();

    expect(Payment::query()->count())->toBe(0);
});

it('không cho thu quá số tiền còn thiếu', function () {
    thuTien($this->thuNgan, $this->luot, [
        'method' => PaymentMethod::Cash->value,
        'amount' => 700_000,
        'tendered_amount' => 700_000,
    ])->assertUnprocessable();

    expect(Payment::query()->count())->toBe(0);
});

it('T8: chuyển khoản không có tiền khách đưa và không có tiền thối', function () {
    thuTien($this->thuNgan, $this->luot, [
        'method' => PaymentMethod::Transfer->value,
        'amount' => 500_000,
        'reference' => 'FT26073012345',
    ])->assertCreated();

    $payment = Payment::query()->sole();
    expect($payment->tendered_amount)->toBeNull()
        ->and($payment->change_amount)->toBe(0)
        ->and($payment->reference)->toBe('FT26073012345');
});

it('chưa mở ca thì không thu được tiền', function () {
    $this->ca->update([
        'status' => ShiftStatus::Closed,
        'closed_at' => now(),
        'closed_by_user_id' => $this->thuNgan->id,
        'counted_cash' => 0,
        'expected_cash' => 0,
    ]);

    thuTien($this->thuNgan, $this->luot, [
        'method' => PaymentMethod::Cash->value,
        'amount' => 500_000,
        'tendered_amount' => 500_000,
    ])->assertUnprocessable();

    expect(Payment::query()->count())->toBe(0);
});

it('lượt khách đã đóng rồi thì không thu tiền được nữa', function () {
    $this->luot->update([
        'status' => TableSessionStatus::Closed,
        'paid_amount' => 500_000,
        'closed_at' => now(),
        'closed_by_user_id' => $this->thuNgan->id,
    ]);

    thuTien($this->thuNgan, $this->luot, [
        'method' => PaymentMethod::Cash->value,
        'amount' => 100_000,
        'tendered_amount' => 100_000,
    ])->assertUnprocessable();
});

it('phục vụ có quyền thu tiền', function () {
    $staff = User::factory()->staff()->create();

    thuTien($staff, $this->luot, [
        'method' => PaymentMethod::Cash->value,
        'amount' => 500_000,
        'tendered_amount' => 500_000,
    ])->assertCreated();
});

it('bếp không có quyền thu tiền', function () {
    $bep = User::factory()->kitchen()->create();

    thuTien($bep, $this->luot, [
        'method' => PaymentMethod::Cash->value,
        'amount' => 500_000,
        'tendered_amount' => 500_000,
    ])->assertForbidden();
});
