<?php

declare(strict_types=1);

namespace App\Domain\Reservations\Actions;

use App\Domain\Reservations\DTO\CreateReservationData;
use App\Domain\Reservations\DTO\CreateReservationResult;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Domain\Reservations\Models\Reservation;
use App\Exceptions\DomainException;

/**
 * Tạo một đặt bàn mới (status = pending).
 *
 * Đặt bàn KHÔNG khoá cứng dining_tables (M3, docs/schema.md PHẦN M.2) — quán
 * vẫn phải bán được cho khách vãng lai ngồi đúng bàn đó. Vì vậy hai đặt bàn
 * trùng giờ trùng bàn chỉ CẢNH BÁO (trả về danh sách ID trùng), không chặn
 * và không cần khoá gì — đây là quyết định có ý thức, không phải thiếu sót.
 */
final class CreateReservation
{
    /** Cửa sổ coi là "trùng giờ" — số tạm, xem docs/viec-ton.md để chỉnh khi có phản hồi thật. */
    private const TRUNG_GIO_PHUT = 120;

    public function handle(CreateReservationData $data): CreateReservationResult
    {
        if ($data->guestCount <= 0) {
            throw new DomainException('Số khách phải lớn hơn 0.');
        }

        $trungGio = $this->timDatBanTrungGio($data);

        $reservation = Reservation::query()->create([
            'customer_id' => $data->customerId,
            'dining_table_id' => $data->diningTableId,
            'guest_count' => $data->guestCount,
            'reserved_at' => $data->reservedAt,
            'status' => ReservationStatus::Pending,
            'note' => $data->note,
            'created_by_user_id' => $data->createdByUserId,
        ]);

        return new CreateReservationResult(
            reservation: $reservation,
            overlappingReservationIds: $trungGio,
        );
    }

    /** @return list<int> */
    private function timDatBanTrungGio(CreateReservationData $data): array
    {
        if ($data->diningTableId === null) {
            return [];
        }

        $tuGio = $data->reservedAt->subMinutes(self::TRUNG_GIO_PHUT);
        $denGio = $data->reservedAt->addMinutes(self::TRUNG_GIO_PHUT);

        return Reservation::query()
            ->where('dining_table_id', $data->diningTableId)
            ->whereIn('status', [ReservationStatus::Pending, ReservationStatus::Confirmed, ReservationStatus::Seated])
            ->whereBetween('reserved_at', [$tuGio, $denGio])
            ->pluck('id')
            ->all();
    }
}
