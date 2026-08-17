<?php

declare(strict_types=1);

namespace App\Domain\Reservations\Actions;

use App\Domain\Reservations\DTO\CancelReservationData;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Domain\Reservations\Models\Reservation;
use App\Exceptions\DomainException;
use App\Exceptions\InvalidReservationTransitionException;
use Illuminate\Support\Facades\DB;

/**
 * Huỷ một đặt bàn (M1: hợp lệ từ pending hoặc confirmed).
 *
 * Huỷ phải có lý do (M2, luật 13 CLAUDE.md — "huỷ = đổi trạng thái + ghi
 * ai/lúc nào/vì sao"), chốt cứng thêm ở DB (ck_reservations_status_reason).
 *
 * Lý do ghi vào `status_reason`. TRƯỚC ĐÂY GHI ĐÈ LÊN `note` — mà `note` là
 * ghi chú của KHÁCH ("bàn gần quạt", "có trẻ nhỏ", "sinh nhật"), nên huỷ một
 * cái là ghi chú của khách mất vĩnh viễn, không khôi phục được. Sửa 13/08.
 *
 * KHÔNG tự động hoàn tiền cọc (M8, cùng quyết định với MarkNoShow) — thu
 * ngân tự xử lý bằng VoidPayment rồi đánh dấu lại bằng MarkDepositHandled.
 */
final class CancelReservation
{
    public function handle(CancelReservationData $data): Reservation
    {
        $lyDo = trim($data->reason);
        if ($lyDo === '') {
            throw new DomainException('Phải ghi rõ lý do huỷ đặt bàn.');
        }

        return DB::connection('tenant')->transaction(function () use ($data, $lyDo): Reservation {
            $reservation = Reservation::query()->lockForUpdate()->findOrFail($data->reservationId);

            if (! in_array($reservation->status, [ReservationStatus::Pending, ReservationStatus::Confirmed], true)) {
                throw new InvalidReservationTransitionException(
                    "Không huỷ được — đặt bàn đang ở trạng thái '{$reservation->status->value}', chỉ huỷ được từ 'pending' hoặc 'confirmed'."
                );
            }

            $reservation->update([
                'status' => ReservationStatus::Cancelled,
                'status_reason' => $lyDo,
                'status_changed_by_user_id' => $data->cancelledByUserId,
                'status_changed_at' => now(),
            ]);

            return $reservation;
        });
    }
}
