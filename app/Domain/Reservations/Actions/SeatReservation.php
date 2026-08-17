<?php

declare(strict_types=1);

namespace App\Domain\Reservations\Actions;

use App\Domain\Ordering\Enums\TableSessionStatus;
use App\Domain\Ordering\Models\TableSession;
use App\Domain\Reservations\DTO\SeatReservationData;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Domain\Reservations\Models\Reservation;
use App\Exceptions\DomainException;
use App\Exceptions\InvalidReservationTransitionException;
use Illuminate\Support\Facades\DB;

/**
 * Nối một đặt bàn vào lượt khách ĐÃ ĐANG MỞ (M4, docs/schema.md PHẦN M.2).
 *
 * KHÔNG tự mở phiên bàn mới — table_session phải có sẵn (mở bằng
 * OpenTableSession ở luồng bán hàng bình thường), truyền id vào đây. Không
 * kiểm tra dining_table_id của đặt bàn có khớp bàn thật của lượt khách không
 * — thu ngân có thể xếp khách vào bàn khác bàn đã đặt (M4).
 *
 * Khoá TableSession TRƯỚC rồi mới khoá Reservation — đúng CLAUDE.md mục 11.
 */
final class SeatReservation
{
    public function handle(SeatReservationData $data): Reservation
    {
        return DB::connection('tenant')->transaction(function () use ($data): Reservation {
            $tableSession = TableSession::query()->lockForUpdate()->findOrFail($data->tableSessionId);

            if ($tableSession->status !== TableSessionStatus::Open) {
                throw new DomainException('Chưa có lượt khách đang mở ở bàn này — mở bàn trước rồi mới xếp đặt bàn vào.');
            }

            $reservation = Reservation::query()->lockForUpdate()->findOrFail($data->reservationId);

            if (! in_array($reservation->status, [ReservationStatus::Pending, ReservationStatus::Confirmed], true)) {
                throw new InvalidReservationTransitionException(
                    "Không xếp bàn được — đặt bàn đang ở trạng thái '{$reservation->status->value}', chỉ xếp được từ 'pending' hoặc 'confirmed'."
                );
            }

            $reservation->update([
                'table_session_id' => $tableSession->id,
                'status' => ReservationStatus::Seated,
                'status_changed_by_user_id' => $data->seatedByUserId,
                'status_changed_at' => now(),
            ]);

            return $reservation;
        });
    }
}
