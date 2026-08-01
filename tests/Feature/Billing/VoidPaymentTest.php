<?php

declare(strict_types=1);

use App\Domain\Billing\Enums\PaymentMethod;
use App\Domain\Billing\Enums\PaymentStatus;
use App\Domain\Billing\Models\Payment;
use App\Domain\Ordering\Enums\TableSessionStatus;
use App\Domain\Ordering\Models\TableSession;
use App\Domain\Staffing\Actions\CloseShift;
use App\Domain\Staffing\DTO\CloseShiftData;
use App\Domain\Staffing\Enums\CashDirection;
use App\Domain\Staffing\Enums\ShiftStatus;
use App\Domain\Staffing\Models\CashMovement;
use App\Domain\Staffing\Models\Shift;
use App\Domain\Staffing\Models\User;
use App\Support\Money;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

function huyPhieuThu(User $user, Payment $payment, array $payload): TestResponse
{
    return test()->postJson(
        "/api/v1/payments/{$payment->id}/void",
        $payload,
        array_merge(authHeaderFor($user), ['Idempotency-Key' => (string) Str::uuid()])
    );
}

beforeEach(function () {
    $this->ca = Shift::factory()->open()->create();
    $this->owner = User::factory()->owner()->create();
});

it('H1+H2: huỷ phiếu thu — không xoá dòng, ghi đủ ai/lúc nào/vì sao', function () {
    $luot = TableSession::factory()->for($this->ca, 'shift')->create([
        'subtotal_amount' => 500_000, 'total_amount' => 500_000, 'paid_amount' => 200_000, 'status' => TableSessionStatus::Billing,
    ]);
    $payment = Payment::factory()->for($luot, 'tableSession')->for($this->ca, 'shift')->cash()->completed()->create(['amount' => 200_000, 'tendered_amount' => 200_000, 'change_amount' => 0]);

    huyPhieuThu($this->owner, $payment, ['reason' => 'Thu nhầm bàn'])
        ->assertOk()
        ->assertJsonPath('data.status', 'voided')
        ->assertJsonPath('data.void_reason', 'Thu nhầm bàn');

    $payment->refresh();
    expect($payment->status)->toBe(PaymentStatus::Voided)
        ->and($payment->void_reason)->toBe('Thu nhầm bàn')
        ->and($payment->voided_by_user_id)->toBe($this->owner->id)
        ->and($payment->voided_at)->not->toBeNull();

    expect(Payment::query()->count())->toBe(1); // không dòng nào bị xoá
});

it('H2: không ghi lý do thì bị chặn', function () {
    $luot = TableSession::factory()->for($this->ca, 'shift')->create([
        'subtotal_amount' => 200_000, 'total_amount' => 200_000, 'paid_amount' => 200_000, 'status' => TableSessionStatus::Billing,
    ]);
    $payment = Payment::factory()->for($luot, 'tableSession')->for($this->ca, 'shift')->cash()->completed()->create(['amount' => 200_000, 'tendered_amount' => 200_000, 'change_amount' => 0]);

    huyPhieuThu($this->owner, $payment, ['reason' => ''])->assertUnprocessable();

    expect($payment->refresh()->status)->toBe(PaymentStatus::Completed);
});

it('T5: huỷ một phiếu thu thì paid_amount tính lại đúng từ các phiếu còn hiệu lực', function () {
    $luot = TableSession::factory()->for($this->ca, 'shift')->create([
        'subtotal_amount' => 500_000, 'total_amount' => 500_000, 'paid_amount' => 500_000, 'status' => TableSessionStatus::Billing,
    ]);
    $phieu1 = Payment::factory()->for($luot, 'tableSession')->for($this->ca, 'shift')->cash()->completed()->create(['amount' => 300_000, 'tendered_amount' => 300_000, 'change_amount' => 0]);
    Payment::factory()->for($luot, 'tableSession')->for($this->ca, 'shift')->cash()->completed()->create(['amount' => 200_000, 'tendered_amount' => 200_000, 'change_amount' => 0]);

    huyPhieuThu($this->owner, $phieu1, ['reason' => 'Thu nhầm'])->assertOk();

    expect($luot->refresh()->paid_amount)->toBe(200_000)
        ->and($luot->status)->toBe(TableSessionStatus::Billing);
});

it('T6: huỷ phiếu thu làm lượt khách đã đóng tụt xuống thiếu tiền thì tự mở lại', function () {
    $luot = TableSession::factory()->for($this->ca, 'shift')->create([
        'subtotal_amount' => 500_000,
        'total_amount' => 500_000,
        'paid_amount' => 500_000,
        'status' => TableSessionStatus::Closed,
        'closed_at' => now(),
        'closed_by_user_id' => $this->owner->id,
    ]);
    $payment = Payment::factory()->for($luot, 'tableSession')->for($this->ca, 'shift')->cash()->completed()->create(['amount' => 500_000, 'tendered_amount' => 500_000, 'change_amount' => 0]);

    huyPhieuThu($this->owner, $payment, ['reason' => 'Khách khiếu nại'])->assertOk();

    $luot->refresh();
    expect($luot->status)->toBe(TableSessionStatus::Billing)
        ->and($luot->paid_amount)->toBe(0)
        ->and($luot->closed_at)->toBeNull()
        ->and($luot->closed_by_user_id)->toBeNull();
});

it('huỷ phiếu thu đã huỷ rồi thì bị chặn', function () {
    $luot = TableSession::factory()->for($this->ca, 'shift')->create([
        'subtotal_amount' => 200_000, 'total_amount' => 200_000, 'paid_amount' => 0, 'status' => TableSessionStatus::Billing,
    ]);
    $payment = Payment::factory()->for($luot, 'tableSession')->for($this->ca, 'shift')->cash()->voided()->create(['amount' => 200_000, 'tendered_amount' => 200_000, 'change_amount' => 0]);

    huyPhieuThu($this->owner, $payment, ['reason' => 'Lần 2'])->assertUnprocessable();
});

it('thu ngân có quyền huỷ phiếu thu', function () {
    $thuNgan = User::factory()->cashier()->create();
    $luot = TableSession::factory()->for($this->ca, 'shift')->create([
        'subtotal_amount' => 200_000, 'total_amount' => 200_000, 'paid_amount' => 200_000, 'status' => TableSessionStatus::Billing,
    ]);
    $payment = Payment::factory()->for($luot, 'tableSession')->for($this->ca, 'shift')->cash()->completed()->create(['amount' => 200_000, 'tendered_amount' => 200_000, 'change_amount' => 0]);

    huyPhieuThu($thuNgan, $payment, ['reason' => 'Thu nhầm'])->assertOk();
});

it('nhân viên phục vụ không có quyền huỷ phiếu thu', function () {
    $staff = User::factory()->staff()->create();
    $luot = TableSession::factory()->for($this->ca, 'shift')->create([
        'subtotal_amount' => 200_000, 'total_amount' => 200_000, 'paid_amount' => 200_000, 'status' => TableSessionStatus::Billing,
    ]);
    $payment = Payment::factory()->for($luot, 'tableSession')->for($this->ca, 'shift')->cash()->completed()->create(['amount' => 200_000, 'tendered_amount' => 200_000, 'change_amount' => 0]);

    huyPhieuThu($staff, $payment, ['reason' => 'Test'])->assertForbidden();
});

it('C4+C5: huỷ phiếu thu tiền mặt của ca ĐANG MỞ thì không tạo khoản chi, expected_cash tự giảm đúng', function () {
    $luot = TableSession::factory()->for($this->ca, 'shift')->create([
        'subtotal_amount' => 200_000, 'total_amount' => 200_000, 'paid_amount' => 200_000, 'status' => TableSessionStatus::Closed, 'closed_at' => now(), 'closed_by_user_id' => $this->owner->id,
    ]);
    $payment = Payment::factory()->for($luot, 'tableSession')->for($this->ca, 'shift')->cash()->completed()->create(['amount' => 200_000, 'tendered_amount' => 200_000, 'change_amount' => 0]);

    huyPhieuThu($this->owner, $payment, ['reason' => 'Thu nhầm, ca vẫn đang mở'])->assertOk();

    expect(CashMovement::query()->count())->toBe(0);

    // Huỷ phiếu làm lượt khách hụt tiền nên tự mở lại (T6) — không phải trọng
    // tâm của test này, đóng lượt khách bằng void để không cản trở đóng ca.
    $luot->refresh()->update([
        'status' => TableSessionStatus::Void,
        'voided_at' => now(),
        'voided_by_user_id' => $this->owner->id,
        'void_reason' => 'Dọn dẹp cho test',
    ]);

    // Đóng ca ngay sau đó: phiếu đã huỷ không được tính vào tiền mặt thu được (C4).
    $caDaDong = app(CloseShift::class)->handle(new CloseShiftData(
        shiftId: $this->ca->id,
        countedCash: Money::fromInt($this->ca->opening_cash),
        note: null,
        closedByUserId: $this->owner->id,
    ));

    expect($caDaDong->expected_cash)->toBe($this->ca->opening_cash);
});

it('hoàn tiền: huỷ phiếu thu tiền mặt của ca ĐÃ ĐÓNG thì tạo đúng 1 khoản chi trong ca hiện tại', function () {
    $caCu = $this->ca;
    $luot = TableSession::factory()->for($caCu, 'shift')->create([
        'subtotal_amount' => 800_000, 'total_amount' => 800_000, 'paid_amount' => 800_000, 'status' => TableSessionStatus::Closed, 'closed_at' => now(), 'closed_by_user_id' => $this->owner->id,
    ]);
    $payment = Payment::factory()->for($luot, 'tableSession')->for($caCu, 'shift')->cash()->completed()->create(['amount' => 800_000, 'tendered_amount' => 800_000, 'change_amount' => 0]);

    app(CloseShift::class)->handle(new CloseShiftData(
        shiftId: $caCu->id,
        countedCash: Money::fromInt(800_000),
        note: null,
        closedByUserId: $this->owner->id,
    ));

    $caMoi = Shift::factory()->open()->create();

    huyPhieuThu($this->owner, $payment, ['reason' => 'Khách trả lại hàng'])->assertOk();

    $khoanChi = CashMovement::query()->sole();
    expect($khoanChi->shift_id)->toBe($caMoi->id)
        ->and($khoanChi->direction)->toBe(CashDirection::Out)
        ->and($khoanChi->amount)->toBe(800_000)
        ->and($khoanChi->reason)->toContain((string) $payment->id)
        ->and($khoanChi->reason)->toContain($caCu->code)
        ->and($khoanChi->reason)->toContain('Khách trả lại hàng');

    // C5: ca cũ đã chốt, không đổi.
    $caCu->refresh();
    expect($caCu->expected_cash)->toBe(800_000)
        ->and($caCu->counted_cash)->toBe(800_000);
});

it('huỷ phiếu CHUYỂN KHOẢN của ca đã đóng thì không tạo khoản chi', function () {
    $caCu = $this->ca;
    $luot = TableSession::factory()->for($caCu, 'shift')->create([
        'subtotal_amount' => 300_000, 'total_amount' => 300_000, 'paid_amount' => 300_000, 'status' => TableSessionStatus::Closed, 'closed_at' => now(), 'closed_by_user_id' => $this->owner->id,
    ]);
    $payment = Payment::factory()->for($luot, 'tableSession')->for($caCu, 'shift')->transfer()->completed()->create(['amount' => 300_000]);

    app(CloseShift::class)->handle(new CloseShiftData(
        shiftId: $caCu->id,
        countedCash: Money::zero(),
        note: null,
        closedByUserId: $this->owner->id,
    ));

    Shift::factory()->open()->create();

    huyPhieuThu($this->owner, $payment, ['reason' => 'Chuyển khoản nhầm'])->assertOk();

    expect(CashMovement::query()->count())->toBe(0);
});

it('huỷ phiếu tiền mặt của ca đã đóng mà KHÔNG có ca nào đang mở thì bị chặn', function () {
    $caCu = $this->ca;
    $luot = TableSession::factory()->for($caCu, 'shift')->create([
        'subtotal_amount' => 500_000, 'total_amount' => 500_000, 'paid_amount' => 500_000, 'status' => TableSessionStatus::Closed, 'closed_at' => now(), 'closed_by_user_id' => $this->owner->id,
    ]);
    $payment = Payment::factory()->for($luot, 'tableSession')->for($caCu, 'shift')->cash()->completed()->create(['amount' => 500_000, 'tendered_amount' => 500_000, 'change_amount' => 0]);

    app(CloseShift::class)->handle(new CloseShiftData(
        shiftId: $caCu->id,
        countedCash: Money::fromInt(500_000),
        note: null,
        closedByUserId: $this->owner->id,
    ));

    // Không có ca nào đang mở lúc này.
    expect(Shift::query()->where('status', ShiftStatus::Open)->exists())->toBeFalse();

    huyPhieuThu($this->owner, $payment, ['reason' => 'Khách trả lại hàng'])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Chưa mở ca. Phải mở ca trước khi huỷ phiếu thu của lượt khách đã đóng ở ca cũ.');

    expect($payment->refresh()->status)->toBe(PaymentStatus::Completed);
    expect(CashMovement::query()->count())->toBe(0);

    // Bị chặn thì lượt khách phải giữ nguyên hoàn toàn — không đổi trạng thái,
    // không đổi ca, không đổi paid_amount — không có nửa vời.
    $luot->refresh();
    expect($luot->status)->toBe(TableSessionStatus::Closed)
        ->and($luot->shift_id)->toBe($caCu->id)
        ->and($luot->paid_amount)->toBe(500_000);
});

it('LỖI CA LỆCH: huỷ phiếu thu tiền mặt của ca đã đóng — sau khi hoàn tiền, lượt khách chuyển sang ca hiện tại và thu lại được ngay', function () {
    // 1. Ca A mở (dùng luôn ca mở sẵn ở beforeEach — chỉ được có một ca mở tại
    //    một thời điểm), lượt khách gọi món 800.000, thu đủ tiền mặt, tự đóng.
    $caA = $this->ca;
    $luot = TableSession::factory()->for($caA, 'shift')->create([
        'status' => TableSessionStatus::Open,
        'subtotal_amount' => 800_000,
        'total_amount' => 800_000,
        'paid_amount' => 0,
    ]);

    $phieuThuCu = test()->postJson(
        "/api/v1/table-sessions/{$luot->id}/payments",
        ['uuid' => (string) Str::uuid(), 'method' => PaymentMethod::Cash->value, 'amount' => 800_000, 'tendered_amount' => 800_000],
        array_merge(authHeaderFor($this->owner), ['Idempotency-Key' => (string) Str::uuid()])
    )->assertCreated();

    $luot->refresh();
    expect($luot->status)->toBe(TableSessionStatus::Closed)
        ->and($luot->shift_id)->toBe($caA->id);

    // 2. Đóng ca A.
    app(CloseShift::class)->handle(new CloseShiftData(
        shiftId: $caA->id,
        countedCash: Money::fromInt(800_000),
        note: null,
        closedByUserId: $this->owner->id,
    ));

    // 3. Mở ca B.
    $caB = Shift::factory()->open()->create();

    // 4. Huỷ phiếu thu → tạo CashMovement out trong ca B, lượt khách về billing,
    //    VÀ shift_id của lượt khách đổi sang ca B.
    $payment = Payment::query()->findOrFail($phieuThuCu->json('data.id'));

    huyPhieuThu($this->owner, $payment, ['reason' => 'Khách trả lại hàng'])->assertOk();

    $khoanChi = CashMovement::query()->sole();
    expect($khoanChi->shift_id)->toBe($caB->id)
        ->and($khoanChi->direction)->toBe(CashDirection::Out)
        ->and($khoanChi->amount)->toBe(800_000);

    $luot->refresh();
    expect($luot->status)->toBe(TableSessionStatus::Billing)
        ->and($luot->shift_id)->toBe($caB->id)
        ->and($luot->paid_amount)->toBe(0);

    // 5. Thu lại 800.000 trong ca B → PHẢI THÀNH CÔNG (trước khi sửa, bị từ chối
    //    vĩnh viễn vì shift_id vẫn trỏ về ca A đã đóng).
    $phieuThuMoi = test()->postJson(
        "/api/v1/table-sessions/{$luot->id}/payments",
        ['uuid' => (string) Str::uuid(), 'method' => PaymentMethod::Cash->value, 'amount' => 800_000, 'tendered_amount' => 800_000],
        array_merge(authHeaderFor($this->owner), ['Idempotency-Key' => (string) Str::uuid()])
    )->assertCreated();

    // 6. Phiếu thu mới có shift_id = ca B; ca A giữ nguyên expected_cash và
    //    counted_cash không đổi (C5).
    $payment2 = Payment::query()->findOrFail($phieuThuMoi->json('data.id'));
    expect($payment2->shift_id)->toBe($caB->id);

    $caA->refresh();
    expect($caA->expected_cash)->toBe(800_000)
        ->and($caA->counted_cash)->toBe(800_000);

    expect($luot->refresh()->status)->toBe(TableSessionStatus::Closed);
});
