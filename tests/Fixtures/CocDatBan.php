<?php

declare(strict_types=1);

/**
 * Hai việc lặp đi lặp lại trong các test về tiền cọc đặt bàn — Bước P4-4A.3.
 *
 * Đặt ở `tests/Fixtures/` (ngoài hai bộ test mà phpunit.xml quét) và được
 * `require_once` từ các file test cần dùng, để chạy lẻ MỘT file test vẫn có đủ
 * hàm — không phụ thuộc việc file test khác tình cờ được nạp trước.
 */

use App\Domain\Billing\Enums\PaymentMethod;
use App\Domain\Billing\Enums\PaymentStatus;
use App\Domain\Billing\Models\Payment;
use App\Domain\Reservations\Actions\MarkDepositHandled;
use App\Domain\Reservations\DTO\MarkDepositHandledData;
use App\Domain\Reservations\Enums\DepositStatus;
use App\Domain\Reservations\Models\Reservation;
use App\Domain\Staffing\Models\Shift;
use App\Domain\Staffing\Models\User;
use Carbon\CarbonImmutable;

/** Một phiếu cọc còn hiệu lực cho một đặt bàn — table_session_id để trống (M5). */
function thuCocCho(Reservation $reservation, int $soTien = 200_000): Payment
{
    return Payment::factory()->create([
        'table_session_id' => null,
        'reservation_id' => $reservation->id,
        'shift_id' => Shift::factory()->closed(),
        'method' => PaymentMethod::Cash,
        'amount' => $soTien,
        'tendered_amount' => $soTien,
        'change_amount' => 0,
        'status' => PaymentStatus::Completed,
    ]);
}

/** @param  array<string, mixed>  $ghiDe */
function danhDauCoc(Reservation $reservation, array $ghiDe = []): Reservation
{
    return app(MarkDepositHandled::class)->handle(new MarkDepositHandledData(
        reservationId: $ghiDe['reservationId'] ?? $reservation->id,
        depositStatus: $ghiDe['depositStatus'] ?? DepositStatus::Refunded,
        note: $ghiDe['note'] ?? 'Khách gọi điện xin lại, quán đồng ý hoàn',
        handledByUserId: $ghiDe['handledByUserId'] ?? User::factory()->cashier()->create()->id,
        occurredAt: $ghiDe['occurredAt'] ?? CarbonImmutable::parse('2026-08-13 10:00:00'),
    ));
}
