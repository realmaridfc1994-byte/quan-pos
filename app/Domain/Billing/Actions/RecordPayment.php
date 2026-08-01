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
