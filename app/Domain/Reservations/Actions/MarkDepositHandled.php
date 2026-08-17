<?php

declare(strict_types=1);

namespace App\Domain\Reservations\Actions;

use App\Domain\Billing\Enums\PaymentStatus;
use App\Domain\Billing\Models\Payment;
use App\Domain\Reservations\DTO\MarkDepositHandledData;
use App\Domain\Reservations\Enums\DepositStatus;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Domain\Reservations\Models\Reservation;
use App\Exceptions\DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Ghi lại rằng tiền cọc của một đặt bàn ĐÃ được xử lý xong, theo hướng nào,
 * ai quyết và vì sao — Phase 4 Bước P4-4A.3, chính sách chốt 12/08.
 *
 * ── Action này KHÔNG TRẢ TIỀN CHO AI ──────────────────────────────────────
 * Nó chỉ ghi dấu vết. Việc trả tiền thật vẫn là `VoidPayment` do thu ngân bấm
 * riêng. Hai việc CỐ Ý không tự gọi nhau: nếu đánh dấu mà tự hoàn tiền luôn
 * thì một cú bấm nhầm vừa mất dấu vết vừa mất tiền, và không quay lui được.
 *
 * ── Vì sao tách khỏi MarkNoShow ───────────────────────────────────────────
 * Hai việc xảy ra ở hai thời điểm khác nhau: đánh dấu khách không tới là tối
 * nay, còn quyết hoàn hay giữ cọc có thể là ba hôm sau khi khách gọi điện xin
 * lại. Gộp chung sẽ ép thu ngân quyết ngay lúc chưa biết — đúng cái mà chính
 * sách "hệ thống KHÔNG tự suy diễn" muốn tránh.
 *
 * ── Thứ tự khoá (CLAUDE.md luật 11) ───────────────────────────────────────
 * CHỈ khoá `Reservation`. Bảng `payments` được ĐỌC KHÔNG KHOÁ: `Payment` đứng
 * TRƯỚC `Reservation` trong chuỗi khoá, nên khoá nó sau khi đã khoá
 * Reservation là đi ngược chuỗi và sinh kẹt chéo. Action này không ghi một
 * chữ nào vào `payments` nên đọc không khoá là đủ.
 *
 * `deposit_handled_at` lấy từ thời điểm nghiệp vụ người gọi truyền vào, KHÔNG
 * bao giờ tự `now()` bên trong (M7, bài học bug F Phase 3) — người ta có thể
 * ghi nhận hôm nay một việc đã xử lý từ hôm kia.
 */
final class MarkDepositHandled
{
    public function handle(MarkDepositHandledData $data): Reservation
    {
        if ($data->depositStatus === DepositStatus::Unhandled) {
            throw new DomainException('Phải chọn rõ đã hoàn khách hay quán giữ lại. "Chưa xử lý" không phải một cách xử lý.');
        }

        $lyDo = trim($data->note);
        if ($lyDo === '') {
            throw new DomainException('Phải ghi rõ vì sao xử lý tiền cọc như vậy.');
        }

        return DB::connection('tenant')->transaction(function () use ($data, $lyDo): Reservation {
            $reservation = Reservation::query()->lockForUpdate()->findOrFail($data->reservationId);

            // Chỉ đặt bàn khách KHÔNG TỚI hoặc đã huỷ mới có cọc treo cần quyết.
            // Khách đã ngồi ăn thì cọc là chuyện của cái bill, không phải ở đây.
            if (! in_array($reservation->status, [ReservationStatus::NoShow, ReservationStatus::Cancelled], true)) {
                throw new DomainException(
                    "Chỉ xử lý cọc cho đặt bàn 'no_show' hoặc 'cancelled' — đặt bàn này đang ở trạng thái '{$reservation->status->value}'."
                );
            }

            if ($reservation->deposit_status !== DepositStatus::Unhandled) {
                throw new DomainException(
                    "Cọc của đặt bàn này đã được đánh dấu '{$reservation->deposit_status->label()}' rồi. Muốn sửa thì phải hỏi chủ quán."
                );
            }

            // Đọc KHÔNG khoá — xem ghi chú thứ tự khoá ở đầu file.
            $coCoc = Payment::query()
                ->where('reservation_id', $reservation->id)
                ->where('status', PaymentStatus::Completed)
                ->exists();

            if (! $coCoc) {
                throw new DomainException('Đặt bàn này không có phiếu cọc nào còn hiệu lực — không có gì để xử lý.');
            }

            $reservation->update([
                'deposit_status' => $data->depositStatus,
                'deposit_handled_by_user_id' => $data->handledByUserId,
                'deposit_handled_at' => $data->occurredAt,
                'deposit_handled_note' => $lyDo,
            ]);

            activity('coc-dat-ban')
                ->performedOn($reservation)
                ->withProperties([
                    'reservation_id' => $reservation->id,
                    'deposit_status' => $data->depositStatus->value,
                    'handled_by_user_id' => $data->handledByUserId,
                    'occurred_at' => $data->occurredAt->toIso8601String(),
                    'note' => $lyDo,
                ])
                ->log("Cọc đặt bàn #{$reservation->id}: {$data->depositStatus->label()}.");

            return $reservation;
        });
    }
}
