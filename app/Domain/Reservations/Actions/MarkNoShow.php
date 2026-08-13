<?php

declare(strict_types=1);

namespace App\Domain\Reservations\Actions;

use App\Domain\Reservations\DTO\MarkNoShowData;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Domain\Reservations\Models\Reservation;
use App\Exceptions\InvalidReservationTransitionException;
use Illuminate\Support\Facades\DB;

/**
 * Đánh dấu khách không tới (M1: hợp lệ từ pending hoặc confirmed).
 *
 * KHÔNG tự động giữ/hoàn tiền cọc (M8) — chính sách cọc khi no_show chưa
 * chốt với chủ quán. Thu ngân tự xử lý bằng VoidPayment nếu cần hoàn.
 */
final class MarkNoShow
{
    public function handle(MarkNoShowData $data): Reservation
    {
        return DB::transaction(function () use ($data): Reservation {
            $reservation = Reservation::query()->lockForUpdate()->findOrFail($data->reservationId);

            if (! in_array($reservation->status, [ReservationStatus::Pending, ReservationStatus::Confirmed], true)) {
                throw new InvalidReservationTransitionException(
                    "Không đánh dấu no_show được — đặt bàn đang ở trạng thái '{$reservation->status->value}', chỉ đánh dấu được từ 'pending' hoặc 'confirmed'."
                );
            }

            $reservation->update([
                'status' => ReservationStatus::NoShow,
                'status_changed_by_user_id' => $data->markedByUserId,
                'status_changed_at' => now(),
            ]);

            return $reservation;
        });
    }
}
