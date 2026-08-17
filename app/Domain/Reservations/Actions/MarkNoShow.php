<?php

declare(strict_types=1);

namespace App\Domain\Reservations\Actions;

use App\Domain\Reservations\DTO\MarkNoShowData;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Domain\Reservations\Models\Reservation;
use App\Exceptions\DomainException;
use App\Exceptions\InvalidReservationTransitionException;
use Illuminate\Support\Facades\DB;

/**
 * Đánh dấu khách không tới (M1: hợp lệ từ pending hoặc confirmed).
 *
 * BẮT BUỘC CÓ LÝ DO (chính sách cọc chốt 12/08, luật 13 CLAUDE.md), chốt cứng
 * thêm ở DB bằng ck_reservations_status_reason. Lý do ghi vào `status_reason`,
 * KHÔNG đè lên `note` — `note` là ghi chú của khách ("bàn gần quạt"), mất là
 * mất luôn. Ghi thêm một dòng nhật ký để ba tháng sau còn tra được.
 *
 * KHÔNG tự động giữ hay hoàn tiền cọc — đó là quyết định của người, không phải
 * của máy. Thu ngân trả tiền bằng VoidPayment rồi đánh dấu lại bằng
 * MarkDepositHandled; chừng nào chưa đánh dấu thì đặt bàn này còn nằm trong
 * báo cáo "cọc chưa xử lý" (GetUnhandledDeposits).
 */
final class MarkNoShow
{
    public function handle(MarkNoShowData $data): Reservation
    {
        $lyDo = trim($data->reason);
        if ($lyDo === '') {
            throw new DomainException('Phải ghi rõ lý do đánh dấu khách không tới.');
        }

        return DB::connection('tenant')->transaction(function () use ($data, $lyDo): Reservation {
            $reservation = Reservation::query()->lockForUpdate()->findOrFail($data->reservationId);

            if (! in_array($reservation->status, [ReservationStatus::Pending, ReservationStatus::Confirmed], true)) {
                throw new InvalidReservationTransitionException(
                    "Không đánh dấu no_show được — đặt bàn đang ở trạng thái '{$reservation->status->value}', chỉ đánh dấu được từ 'pending' hoặc 'confirmed'."
                );
            }

            $reservation->update([
                'status' => ReservationStatus::NoShow,
                'status_reason' => $lyDo,
                'status_changed_by_user_id' => $data->markedByUserId,
                'status_changed_at' => now(),
            ]);

            activity('dat-ban')
                ->performedOn($reservation)
                ->withProperties([
                    'reservation_id' => $reservation->id,
                    'marked_by_user_id' => $data->markedByUserId,
                    'reason' => $lyDo,
                ])
                ->log("Đặt bàn #{$reservation->id}: khách không tới.");

            return $reservation;
        });
    }
}
