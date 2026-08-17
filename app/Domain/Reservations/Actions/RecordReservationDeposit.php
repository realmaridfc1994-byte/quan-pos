<?php

declare(strict_types=1);

namespace App\Domain\Reservations\Actions;

use App\Domain\Billing\Enums\PaymentMethod;
use App\Domain\Billing\Enums\PaymentStatus;
use App\Domain\Billing\Models\Payment;
use App\Domain\Reservations\DTO\RecordReservationDepositData;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Domain\Reservations\Models\Reservation;
use App\Domain\Staffing\Enums\ShiftStatus;
use App\Domain\Staffing\Models\Shift;
use App\Exceptions\DomainException;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Ghi nhận một khoản tiền cọc giữ chỗ cho một đặt bàn — dùng lại `payments`
 * đã có (M3, docs/schema.md PHẦN M.3), KHÔNG tạo bảng tiền riêng.
 *
 * table_session_id để NULL, reservation_id set — ck_payments_target (M5) đòi
 * đúng một trong hai. Cọc KHÔNG cộng vào paid_amount của bàn nào (M6).
 *
 * occurred_at lấy từ $data->occurredAt do người gọi truyền vào — KHÔNG bao
 * giờ tự now() bên trong Action (M7, bài học bug F Phase 3).
 *
 * Khoá Reservation TRƯỚC rồi mới khoá Shift — đúng CLAUDE.md mục 11.
 */
final class RecordReservationDeposit
{
    public function handle(RecordReservationDepositData $data): Payment
    {
        return DB::connection('tenant')->transaction(function () use ($data): Payment {
            // Bấm gửi hai lần vì mạng lag: trả về đúng phiếu cọc cũ, không ghi lần hai.
            $daCo = Payment::query()->where('uuid', $data->uuid)->first();
            if ($daCo !== null) {
                return $daCo;
            }

            $reservation = Reservation::query()->lockForUpdate()->findOrFail($data->reservationId);

            if (! in_array($reservation->status, [ReservationStatus::Pending, ReservationStatus::Confirmed], true)) {
                throw new DomainException(
                    "Không thu cọc được — đặt bàn đang ở trạng thái '{$reservation->status->value}', chỉ thu cọc được từ 'pending' hoặc 'confirmed'."
                );
            }

            $shift = Shift::query()->where('status', ShiftStatus::Open)->lockForUpdate()->first();
            if ($shift === null) {
                throw new DomainException('Chưa mở ca. Phải mở ca trước khi thu tiền cọc.');
            }

            $change = Money::zero();
            if ($data->method === PaymentMethod::Cash) {
                if ($data->tenderedAmount === null) {
                    throw new DomainException('Thu cọc tiền mặt phải ghi số tiền khách đưa.');
                }
                // Ràng buộc ck_payments_cash: khách đưa = ghi nhận + thối lại
                $change = $data->tenderedAmount->minus($data->amount);
            }

            return Payment::query()->create([
                'uuid' => $data->uuid,
                'table_session_id' => null,
                'reservation_id' => $reservation->id,
                'shift_id' => $shift->id,
                'method' => $data->method,
                'amount' => $data->amount->amount,
                'tendered_amount' => $data->tenderedAmount?->amount,
                'change_amount' => $change->amount,
                'reference' => $data->reference,
                'status' => PaymentStatus::Completed,
                'received_by_user_id' => $data->receivedByUserId,
                'paid_at' => $data->occurredAt,
            ]);
        });
    }
}
