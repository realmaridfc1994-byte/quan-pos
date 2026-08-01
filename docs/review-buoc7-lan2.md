# REVIEW BƯỚC 7 — LẦN 2

Báo cáo tổng hợp 5 lượt sửa gần nhất của Bước 7 (Tính tiền & thu tiền):
🔴1 (uuid/T9), 🔴2 (giảm giá xuống dưới tiền đã trả), 🔴3 (hoàn tiền két khi
huỷ phiếu thu ca cũ), 🟡A (ngưỡng giảm giá 20% tính trên gì), 🟡B (test tranh
chấp hai người cùng thu tiền).

File này chỉ để đọc — không có sửa code nào đi kèm.

---

## 1. Nội dung đầy đủ 3 file Action

### 1.1. `app/Domain/Billing/Actions/RecordPayment.php`

```php
<?php

declare(strict_types=1);

namespace App\Domain\Billing\Actions;

use App\Domain\Billing\DTO\RecordPaymentData;
use App\Domain\Billing\Enums\PaymentMethod;
use App\Domain\Billing\Enums\PaymentStatus;
use App\Domain\Billing\Models\Payment;
use App\Domain\Ordering\Actions\CloseTableSession;
use App\Domain\Ordering\DTO\CloseTableSessionData;
use App\Domain\Ordering\Enums\TableSessionStatus;
use App\Domain\Ordering\Models\TableSession;
use App\Domain\Staffing\Enums\ShiftStatus;
use App\Domain\Staffing\Models\Shift;
use App\Exceptions\DomainException;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Ghi nhận MỘT lần khách trả tiền cho một lượt khách.
 *
 * T9: uuid do MÁY POS sinh và gửi lên (không sinh ở server) — đây là chốt
 * chặn thật cho việc thu trùng. Header Idempotency-Key (middleware
 * EnsureIdempotencyKey) chỉ chặn được request gửi trùng TRONG 24 GIỜ; nếu máy
 * POS khởi động lại thì key đó mất, nhưng uuid phiếu thu (máy tự lưu cùng đơn
 * hàng) vẫn giữ nguyên khi gửi lại — tra theo uuid trước khi ghi gì mới là
 * chỗ thật sự chống thu trùng.
 * T7/T8: tiền mặt luôn có "khách đưa" và thối đúng, chuyển khoản không có cả hai.
 * T6: thu đủ thì tự đóng lượt khách và nhả bàn ngay (gọi lại CloseTableSession
 * của Bước 3, đã có kiểm T6 thật).
 */
final class RecordPayment
{
    public function __construct(
        private readonly CloseTableSession $closeTableSession,
    ) {}

    public function handle(RecordPaymentData $data): Payment
    {
        return DB::transaction(function () use ($data): Payment {
            $phieuDaCo = Payment::query()->where('uuid', $data->uuid)->first();

            if ($phieuDaCo !== null) {
                return $phieuDaCo;
            }

            $tableSession = TableSession::query()->lockForUpdate()->findOrFail($data->tableSessionId);

            if (! in_array($tableSession->status, [TableSessionStatus::Open, TableSessionStatus::Billing], true)) {
                throw new DomainException('Lượt khách này đã đóng hoặc đã huỷ, không thu tiền được.');
            }

            $shift = Shift::query()->lockForUpdate()->findOrFail($tableSession->shift_id);

            if ($shift->status !== ShiftStatus::Open) {
                throw new DomainException('Ca của lượt khách này đã đóng, không thu tiền được.');
            }

            $tong = Money::fromInt($tableSession->total_amount);
            $daThu = Money::fromInt($tableSession->paid_amount);
            $conThieu = $tong->minus($daThu);

            if ($conThieu->isZero()) {
                throw new DomainException('Lượt khách này đã thu đủ tiền.');
            }

            if (! $conThieu->isAtLeast($data->amount)) {
                throw new DomainException(
                    "Thu quá số còn thiếu. Còn thiếu {$conThieu->format()}, đang thu {$data->amount->format()}."
                );
            }

            $tienThoi = Money::zero();

            if ($data->method === PaymentMethod::Cash) {
                if ($data->tenderedAmount === null) {
                    throw new DomainException('Thu tiền mặt phải ghi số tiền khách đưa.');
                }

                if ($data->tenderedAmount->isLessThan($data->amount)) {
                    throw new DomainException('Tiền khách đưa không được ít hơn số tiền thu.');
                }

                $tienThoi = $data->tenderedAmount->minus($data->amount);
            } elseif ($data->tenderedAmount !== null) {
                throw new DomainException('Chuyển khoản không có "tiền khách đưa".');
            }

            $payment = Payment::query()->create([
                'uuid' => $data->uuid,
                'table_session_id' => $tableSession->id,
                'shift_id' => $shift->id,
                'method' => $data->method,
                'amount' => $data->amount->amount,
                'tendered_amount' => $data->tenderedAmount?->amount,
                'change_amount' => $tienThoi->amount,
                'reference' => $data->reference,
                'status' => PaymentStatus::Completed,
                'received_by_user_id' => $data->receivedByUserId,
                'paid_at' => now(),
            ]);

            $daThuMoi = $daThu->plus($data->amount);

            $tableSession->update([
                'paid_amount' => $daThuMoi->amount,
                'status' => TableSessionStatus::Billing,
            ]);

            if ($daThuMoi->isAtLeast($tong)) {
                $this->closeTableSession->handle(new CloseTableSessionData(
                    tableSessionId: $tableSession->id,
                    closedByUserId: $data->receivedByUserId,
                ));
            }

            return $payment;
        });
    }
}
```

### 1.2. `app/Domain/Billing/Actions/CalculateBill.php`

```php
<?php

declare(strict_types=1);

namespace App\Domain\Billing\Actions;

use App\Domain\Billing\DTO\CalculateBillData;
use App\Domain\Ordering\Actions\CloseTableSession;
use App\Domain\Ordering\Actions\RecalculateSessionSubtotal;
use App\Domain\Ordering\DTO\CloseTableSessionData;
use App\Domain\Ordering\Enums\TableSessionStatus;
use App\Domain\Ordering\Models\TableSession;
use App\Domain\Ordering\Policies\TableSessionPolicy;
use App\Domain\Staffing\Actions\VerifyApproverPin;
use App\Domain\Staffing\DTO\PinVerifyData;
use App\Domain\Staffing\Models\User;
use App\Exceptions\DomainException;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Tính lại tạm tính, áp giảm giá và chốt tổng phải thu cho một lượt khách.
 *
 * T2: tạm tính luôn tính lại từ dòng món hiện có (gọi RecalculateSessionSubtotal),
 * không tin số cũ trong cột — tránh lệch nếu có món vừa huỷ mà chưa ai gọi lại.
 * T3: tổng phải thu = tạm tính - giảm giá, không bao giờ âm (Money tự chặn).
 * T4: có giảm giá thì bắt buộc có lý do.
 * Ngưỡng giảm giá theo vai trò dùng TableSessionPolicy::discount() đã có ở Bước 3.
 * Vượt ngưỡng của người gọi thì bắt buộc có người duyệt (chủ quán/thu ngân) nhập
 * đúng PIN, và chính người duyệt đó cũng phải đủ thẩm quyền cho mức % này.
 * Không cho giảm giá xuống dưới số tiền khách ĐÃ TRẢ (paid_amount) — nếu không,
 * quán giữ thừa tiền khách mà hệ thống không biết. Không có CHECK nào ở DB chặn
 * việc này (xem docs/viec-ton.md), nên phải tự kiểm ở đây, trong cùng transaction
 * đã khoá lượt khách — tránh đọc paid_amount cũ trong lúc người khác đang thu tiền.
 * Giảm giá làm tổng phải thu bằng đúng số đã trả thì tự đóng lượt khách luôn
 * (giống RecordPayment khi thu đủ) — không có lý do gì bắt thu ngân bấm thêm
 * một bước đóng bàn riêng khi tiền đã đủ ngay tại lúc giảm giá.
 */
final class CalculateBill
{
    public function __construct(
        private readonly VerifyApproverPin $verifyApproverPin,
        private readonly CloseTableSession $closeTableSession,
    ) {}

    public function handle(CalculateBillData $data): TableSession
    {
        return DB::transaction(function () use ($data): TableSession {
            $tableSession = TableSession::query()->lockForUpdate()->findOrFail($data->tableSessionId);

            if (! in_array($tableSession->status, [TableSessionStatus::Open, TableSessionStatus::Billing], true)) {
                throw new DomainException('Lượt khách này đã đóng hoặc đã huỷ, không tính lại tiền được.');
            }

            app(RecalculateSessionSubtotal::class)->handle($tableSession);
            $tableSession->refresh();

            $tamTinh = Money::fromInt($tableSession->subtotal_amount);

            if (! $tamTinh->isAtLeast($data->discountAmount)) {
                throw new DomainException('Số tiền giảm giá không được lớn hơn tạm tính.');
            }

            $lyDo = $data->discountReason !== null ? trim($data->discountReason) : null;

            if (! $data->discountAmount->isZero() && ($lyDo === null || $lyDo === '')) {
                throw new DomainException('Giảm giá phải ghi rõ lý do.');
            }

            $nguoiYeuCau = User::query()->findOrFail($data->requestedByUserId);
            $phanTram = $this->phanTramLamTron($data->discountAmount, $tamTinh);
            $chinhSachGiam = new TableSessionPolicy;

            if (! $chinhSachGiam->discount($nguoiYeuCau, $tableSession, $phanTram)) {
                if ($data->approverUserId === null || $data->approverPin === null) {
                    throw new DomainException('Giảm giá vượt mức cho phép, phải có người duyệt bằng mã PIN.');
                }

                $nguoiDuyet = $this->verifyApproverPin->handle(new PinVerifyData(
                    userId: $data->approverUserId,
                    pin: $data->approverPin,
                    requestedByUserId: $data->requestedByUserId,
                ));

                if (! $chinhSachGiam->discount($nguoiDuyet, $tableSession, $phanTram)) {
                    throw new DomainException('Người duyệt cũng không đủ thẩm quyền giảm giá ở mức này.');
                }
            }

            $tongMoi = $tamTinh->minus($data->discountAmount);
            $daThu = Money::fromInt($tableSession->paid_amount);

            if ($tongMoi->isLessThan($daThu)) {
                throw new DomainException(
                    "Không giảm được xuống {$tongMoi->format()} vì khách đã trả {$daThu->format()}. Muốn giảm thêm thì phải huỷ bớt phiếu thu trước."
                );
            }

            $tableSession->update([
                'discount_amount' => $data->discountAmount->amount,
                'discount_reason' => $data->discountAmount->isZero() ? null : $lyDo,
                'total_amount' => $tongMoi->amount,
            ]);

            if ($daThu->isAtLeast($tongMoi)) {
                $this->closeTableSession->handle(new CloseTableSessionData(
                    tableSessionId: $tableSession->id,
                    closedByUserId: $data->requestedByUserId,
                ));
            }

            return $tableSession->refresh();
        });
    }

    /**
     * Phần trăm giảm giá, làm tròn LÊN (ceiling) để không lách ngưỡng vai trò
     * bằng cách làm tròn xuống — ví dụ 20.001% phải bị tính là 21%, không phải 20%.
     * Toàn bộ phép tính là số nguyên, không dùng float.
     */
    private function phanTramLamTron(Money $giamGia, Money $tamTinh): int
    {
        if ($tamTinh->isZero()) {
            return 0;
        }

        return intdiv($giamGia->amount * 100 + $tamTinh->amount - 1, $tamTinh->amount);
    }
}
```

### 1.3. `app/Domain/Billing/Actions/VoidPayment.php`

```php
<?php

declare(strict_types=1);

namespace App\Domain\Billing\Actions;

use App\Domain\Billing\DTO\VoidPaymentData;
use App\Domain\Billing\Enums\PaymentMethod;
use App\Domain\Billing\Enums\PaymentStatus;
use App\Domain\Billing\Models\Payment;
use App\Domain\Ordering\Enums\TableSessionStatus;
use App\Domain\Ordering\Models\TableSession;
use App\Domain\Staffing\Enums\CashDirection;
use App\Domain\Staffing\Enums\ShiftStatus;
use App\Domain\Staffing\Models\CashMovement;
use App\Domain\Staffing\Models\Shift;
use App\Exceptions\DomainException;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Huỷ một phiếu thu — không xoá dòng, chỉ đổi trạng thái + ghi ai/lúc nào/vì sao.
 *
 * T5: paid_amount của lượt khách luôn tính lại bằng cách CỘNG LẠI từ đầu mọi
 * phiếu thu còn hiệu lực — không trừ trực tiếp, tránh lệch nếu có phiếu khác
 * đã bị huỷ trước đó mà chưa ai tính lại.
 *
 * Nếu lượt khách đã đóng (nhờ phiếu thu này) mà huỷ phiếu làm số tiền đã thu
 * không còn đủ (T6), phải mở lại lượt khách để thu ngân thu tiếp. Mở lại bằng
 * trạng thái "billing" ("đã in tạm tính đang chờ trả" — đúng nghĩa ở đây).
 *
 * NGOẠI LỆ CỦA BẤT BIẾN B2 ("lượt khách đang mở phải chiếm ít nhất một bàn"):
 * bàn vật lý đã nhả ra lúc đóng lượt khách (B4) thì KHÔNG được tự ghép lại ở
 * đây, vì giữa lúc đóng và lúc huỷ phiếu thu, bàn đó có thể đã có khách MỚI
 * ngồi vào — tự ghép lại sẽ đâm thẳng vào uq_tst_one_session_per_table (B1).
 * Vì vậy lượt khách "billing" sinh ra ở đây hợp lệ nhưng có thể KHÔNG chiếm
 * bàn nào. B2 chỉ là ràng buộc APP (xem docs/schema.md), không phải CHECK ở
 * DB, nên trường hợp này không vi phạm gì ở tầng cơ sở dữ liệu — chỉ là một
 * ngoại lệ cần biết khi đọc lại B2. Xem docs/viec-ton.md.
 *
 * TIỀN MẶT ĐÃ RA KHỎI KÉT CỦA CA CŨ: nếu phiếu thu tiền mặt thuộc một ca ĐÃ
 * ĐÓNG (C5 — ca đó đã chốt, không sửa lại được), huỷ phiếu này đồng nghĩa
 * quán phải trả lại tiền mặt cho khách NGAY BÂY GIỜ, ở ca ĐANG MỞ — nếu không
 * ghi gì, tối đóng ca hiện tại sẽ thấy két thiếu tiền không rõ lý do. Phải tạo
 * một khoản chi (CashMovement, direction=out) trong ca đang mở. Nếu ca của
 * phiếu thu vẫn đang mở, công thức C4 của CloseShift đã tự loại phiếu voided
 * ra (lọc status=Completed) — không cần tạo gì thêm. Phiếu chuyển khoản không
 * đụng tới két tiền mặt nên không tạo khoản chi dù ca đã đóng.
 */
final class VoidPayment
{
    public function handle(VoidPaymentData $data): Payment
    {
        return DB::transaction(function () use ($data): Payment {
            $payment = Payment::query()->lockForUpdate()->findOrFail($data->paymentId);

            if ($payment->status === PaymentStatus::Voided) {
                throw new DomainException('Phiếu thu này đã huỷ rồi.');
            }

            $lyDo = trim($data->reason);

            if ($lyDo === '') {
                throw new DomainException('Phải ghi rõ lý do huỷ phiếu thu.');
            }

            $tableSession = TableSession::query()->lockForUpdate()->findOrFail($payment->table_session_id);

            $payment->update([
                'status' => PaymentStatus::Voided,
                'voided_at' => now(),
                'voided_by_user_id' => $data->voidedByUserId,
                'void_reason' => $lyDo,
            ]);

            $caCuaPhieu = Shift::query()->lockForUpdate()->findOrFail($payment->shift_id);

            if ($caCuaPhieu->status === ShiftStatus::Closed && $payment->method === PaymentMethod::Cash) {
                $caHienTai = Shift::query()->where('status', ShiftStatus::Open)->lockForUpdate()->first();

                if ($caHienTai === null) {
                    throw new DomainException('Chưa mở ca. Phải mở ca trước khi huỷ phiếu thu tiền mặt của ca cũ.');
                }

                CashMovement::query()->create([
                    'shift_id' => $caHienTai->id,
                    'direction' => CashDirection::Out,
                    'amount' => $payment->amount,
                    'reason' => "Hoàn tiền phiếu thu #{$payment->id} của ca {$caCuaPhieu->code} — {$lyDo}",
                    'created_by_user_id' => $data->voidedByUserId,
                    'occurred_at' => now(),
                ]);
            }

            $tongDaThu = Payment::query()
                ->where('table_session_id', $tableSession->id)
                ->where('status', PaymentStatus::Completed)
                ->get()
                ->reduce(
                    fn (Money $tong, Payment $p) => $tong->plus(Money::fromInt($p->amount)),
                    Money::zero()
                );

            $capNhat = ['paid_amount' => $tongDaThu->amount];

            if (
                $tableSession->status === TableSessionStatus::Closed
                && $tongDaThu->isLessThan(Money::fromInt($tableSession->total_amount))
            ) {
                $capNhat['status'] = TableSessionStatus::Billing;
                $capNhat['closed_at'] = null;
                $capNhat['closed_by_user_id'] = null;
            }

            $tableSession->update($capNhat);

            return $payment;
        });
    }
}
```

---

## 2. Nội dung đầy đủ các test MỚI (chỉ test mới, không kèm test cũ)

### 2.1. 🔴1 (uuid/T9) — thêm vào `tests/Feature/Billing/RecordPaymentTest.php`

```php
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
```

Ghi chú: test T9 cũ ("thu hai lần với cùng Idempotency-Key...") không phải test mới, nhưng NỘI DUNG của nó bị sửa (thêm field `uuid` bắt buộc vào payload) — không dán lại ở đây vì không phải "test mới" theo đúng nghĩa đen anh yêu cầu. Helper `thuTien()` cũng được sửa (tự điền `uuid` mặc định) chứ không phải tạo mới.

### 2.2. 🔴2 (chặn giảm giá xuống dưới tiền đã trả) — thêm vào `tests/Feature/Billing/CalculateBillTest.php`

```php
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
```

```php
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
```

### 2.3. 🔴3 (hoàn tiền két khi huỷ phiếu thu ca cũ) — thêm vào `tests/Feature/Billing/VoidPaymentTest.php`

```php
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
        ->assertJsonPath('message', 'Chưa mở ca. Phải mở ca trước khi huỷ phiếu thu tiền mặt của ca cũ.');

    expect($payment->refresh()->status)->toBe(PaymentStatus::Completed);
    expect(CashMovement::query()->count())->toBe(0);
});
```

### 2.4. 🟡A (ngưỡng 20% tính trên gì) — thêm vào `tests/Feature/Billing/CalculateBillTest.php`

```php
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
```

### 2.5. 🟡B (test tranh chấp) — toàn bộ file mới `tests/Feature/Billing/PaymentConcurrencyTest.php`

```php
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
```

---

## 3. MỤC 🟡A — ngưỡng 20% tính trên cái gì

**Trả lời dứt khoát: tính trên `subtotal_amount`, cả TRƯỚC và SAU (không sửa gì cả, vì không có lỗi).**

Dòng code (giống hệt nhau trước/sau, không đổi):

```php
// app/Domain/Billing/Actions/CalculateBill.php
$tamTinh = Money::fromInt($tableSession->subtotal_amount);   // KHÔNG phải total_amount còn lại

if (! $tamTinh->isAtLeast($data->discountAmount)) {
    throw new DomainException('Số tiền giảm giá không được lớn hơn tạm tính.');
}
...
$phanTram = $this->phanTramLamTron($data->discountAmount, $tamTinh);  // % = discountAmount / SUBTOTAL
```

Lý do không có lỗi "giảm 3 lần 20% ra gần 50%" như anh lo: `CalculateBillData::$discountAmount` (đọc trực tiếp từ `discount_amount` trong request, xem `CalculateBillData.php`) là **giá trị TUYỆT ĐỐI** client gửi lên mỗi lần — tức là "tổng số tiền giảm mong muốn có", không phải "giảm thêm bao nhiêu". Vì vậy, muốn giảm tổng cộng 25% thì lần gọi thứ hai BẮT BUỘC phải gửi `discount_amount` = 25% × subtotal, và `phanTramLamTron` sẽ tính đúng ra 25% > 20%, bị chặn ngay ở đúng lần gọi đó. Không có cách nào cộng dồn 3 lần 20% nhỏ để né ngưỡng, vì mỗi lần gọi luôn bị đo lại từ đầu trên subtotal với con số tuyệt đối.

Đã kiểm chứng bằng 2 test ở mục 2.4.

---

## 4. MỤC 🟡B — kỹ thuật mô phỏng tranh chấp, có tạo được tranh chấp THẬT không

**Kỹ thuật dùng: gửi HAI REQUEST HTTP LIÊN TIẾP (tuần tự) trong CÙNG một test, y hệt cách `tests/Feature/Ordering/TableConcurrencyTest.php` đã làm cho bàn (B1) — không phát minh cách mới.**

**Trả lời thẳng: KHÔNG tạo được tranh chấp thật.** Đây chỉ là gọi tuần tự, không phải hai tiến trình chạy song song thật. Lý do kỹ thuật (đã ghi thành comment ngay đầu file `PaymentConcurrencyTest.php`, mục 2.5):

- Pest chạy mỗi test trong **một transaction bọc ngoài, trên một kết nối database duy nhất, một tiến trình PHP đơn luồng** (`RefreshDatabase`). Không có hai kết nối THẬT chạm khoá `lockForUpdate()` cùng lúc trong một test — về mặt vật lý không thể xảy ra race condition thật trong khuôn khổ này mà không dựng thêm tiến trình PHP thứ hai (điều mà cả `TableConcurrencyTest.php` lẫn test mới này đều chủ động KHÔNG làm, vì nặng và không hợp quy mô quán 5-15 bàn).
- Cái test này THẬT SỰ kiểm được: request thứ hai gửi **sau khi** request thứ nhất đã `COMMIT` xong bên trong transaction chung của test, nên nó đọc đúng trạng thái MỚI NHẤT (paid_amount đã cập nhật) trước khi tự ghi — tức là kiểm được logic "đọc lại trước khi ghi" (read-then-write) bên trong `lockForUpdate()` cho ra đúng kết quả nghiệp vụ. Đây là điều kiện CẦN nhưng không phải điều kiện ĐỦ để khẳng định khoá chống được race condition thật ở production (nơi hai request đến đúng cùng một mili-giây trên hai kết nối MySQL khác nhau).
- Chốt chặn THẬT cho trường hợp hai request đến đúng cùng lúc vẫn là `lockForUpdate()` trong `DB::transaction()` ở tầng MySQL (InnoDB) — cái này KHÔNG được test này xác nhận trực tiếp, chỉ được suy luận từ việc code có gọi đúng `lockForUpdate()` (đọc lại `RecordPayment.php`/`CalculateBill.php` ở mục 1 để xác nhận bằng mắt).

Kết luận: test hữu ích để bắt lỗi logic (ví dụ quên gọi `lockForUpdate()`, quên đọc lại số dư trước khi ghi) nhưng **không phải bằng chứng chịu được tải thật khi hai máy POS bấm đúng cùng một khắc** — muốn kiểm điều đó cần công cụ khác (ví dụ 2 tiến trình PHP thật, hoặc test tải chuyên dụng), ngoài khả năng của Pest trong cấu hình hiện tại của dự án.

---

## 5. Số test trước và sau lượt sửa này

| Mốc | Tổng số test | Test mới thêm |
|---|---|---|
| Trước 🔴1 (ngay sau khi B2-exception round kết thúc) | **260** | — |
| Sau 🔴1 (uuid/T9) | 262 | +2 |
| Sau 🔴2 (chặn giảm giá dưới tiền đã trả) | 265 | +3 |
| Sau 🔴3 (hoàn tiền két ca cũ) | 269 | +4 |
| Sau 🟡A (ngưỡng 20% — chỉ thêm test, không sửa code) | 271 | +2 |
| Sau 🟡B (test tranh chấp) — **hiện tại** | **274** | +3 |

Tổng cộng 5 lượt sửa này thêm **14 test mới**. Đã chạy `./vendor/bin/pest` xác nhận **274 passed** ngay trước khi viết báo cáo này.

---

## Những chỗ KHÔNG CHẮC đã sửa đúng

1. **Làm tròn phần trăm giảm giá (`phanTramLamTron`, ceiling division).** Công thức `intdiv($giamGia * 100 + $tamTinh - 1, $tamTinh)` tôi tự suy ra để làm tròn LÊN bằng số nguyên. Đã thử đúng các giá trị tròn dùng trong test (15%, 20%, 25% trên subtotal 500.000 — chia hết). **Chưa thử với subtotal lẻ** (ví dụ subtotal không chia hết cho 100, hay các mức % biên sát 20,000% / 20,001%) để chắc chắn không có sai số làm tròn ở biên. Nên có thêm test riêng cho trường hợp này nếu anh thấy cần.

2. **Hành vi tự đóng lượt khách trong `CalculateBill`** (khi giảm giá làm `total_amount` bằng đúng `paid_amount`) **kết hợp với luồng duyệt PIN** — chưa test trường hợp một lần giảm giá VỪA vượt ngưỡng (cần PIN) VỪA khiến lượt khách đủ tiền để tự đóng trong cùng một lần gọi. Hai nhánh (duyệt PIN, và tự đóng) đã test riêng lẻ nhưng chưa test khi xảy ra CÙNG LÚC.

3. **`VoidPayment` tạo `CashMovement` — chưa test trường hợp huỷ HAI phiếu thu tiền mặt của hai ca cũ khác nhau, cùng một lúc muốn hoàn vào MỘT ca đang mở.** Về lý thuyết mỗi lần huỷ đều `lockForUpdate()` đúng ca đang mở nên phải an toàn, nhưng chưa có test thực nghiệm xác nhận (giống tinh thần mục 🟡B — chỉ mô phỏng tuần tự được, không phải race thật).

4. **Số liệu "260 test trước 🔴1"** ở mục 5 tôi lấy từ đối chiếu ngược lại các con số đã tự báo cáo ở mỗi lượt sửa trước đó (262 → 265 → 269 → 271 → 274, trừ lùi đúng số test mới mỗi lượt), không phải chạy lại `git checkout` từng mốc rồi đếm trực tiếp — về lý thuyết đáng tin (khớp cả hai chiều tính xuôi/ngược) nhưng không phải bằng chứng chạy thực tế ở đúng thời điểm đó.
