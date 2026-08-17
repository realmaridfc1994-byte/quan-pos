<?php

declare(strict_types=1);

namespace App\Domain\Reservations\Actions;

use App\Domain\Reservations\DTO\ConfirmReservationData;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Domain\Reservations\Models\Reservation;
use App\Exceptions\InvalidReservationTransitionException;
use Illuminate\Support\Facades\DB;

/** Xác nhận một đặt bàn đang chờ (M1: chỉ hợp lệ từ pending). */
final class ConfirmReservation
{
    public function handle(ConfirmReservationData $data): Reservation
    {
        return DB::connection('tenant')->transaction(function () use ($data): Reservation {
            $reservation = Reservation::query()->lockForUpdate()->findOrFail($data->reservationId);

            if ($reservation->status !== ReservationStatus::Pending) {
                throw new InvalidReservationTransitionException(
                    "Không xác nhận được — đặt bàn đang ở trạng thái '{$reservation->status->value}', chỉ xác nhận được từ 'pending'."
                );
            }

            $reservation->update([
                'status' => ReservationStatus::Confirmed,
                'status_changed_by_user_id' => $data->confirmedByUserId,
                'status_changed_at' => now(),
            ]);

            return $reservation;
        });
    }
}
