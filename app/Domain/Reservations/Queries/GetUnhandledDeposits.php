<?php

declare(strict_types=1);

namespace App\Domain\Reservations\Queries;

use App\Domain\Billing\Enums\PaymentStatus;
use App\Domain\Billing\Models\Payment;
use App\Domain\Reservations\Enums\DepositStatus;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Domain\Reservations\Models\Reservation;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Liệt kê các đặt bàn KHÔNG THÀNH (khách không tới hoặc đã huỷ) mà tiền cọc
 * còn treo, chưa ai đánh dấu xử lý — Phase 4 Bước P4-4A.3.
 *
 * Đây là câu trả lời cho "ba tháng sau giở sổ ra hỏi: cái cọc đó hoàn cho
 * khách chưa, hay mình giữ?". Chừng nào chưa ai bấm MarkDepositHandled thì
 * khoản đó còn nằm trong danh sách này — hệ thống KHÔNG tự suy diễn giùm.
 *
 * Tính CẢ `cancelled` chứ không chỉ `no_show` (chốt 13/08): khách tự huỷ mà
 * đã đặt cọc 200.000đ thì cũng là một khoản tiền thật đang treo y hệt. Bỏ sót
 * nó thì báo cáo mất tác dụng đúng ở chỗ nó sinh ra để làm.
 *
 * Chỉ ĐỌC, không ghi, không khoá dòng nào.
 */
final class GetUnhandledDeposits
{
    /**
     * @return Collection<int, array{
     *     reservation_id: int,
     *     status: string,
     *     khach: string,
     *     dien_thoai: string|null,
     *     dat_luc: string,
     *     so_khach: int,
     *     ly_do: string|null,
     *     tien_coc: int,
     *     tien_coc_text: string,
     *     so_ngay_treo: int
     * }>
     */
    public function handle(): Collection
    {
        $cocTheoDatBan = Payment::query()
            ->whereNotNull('reservation_id')
            ->where('status', PaymentStatus::Completed)
            ->selectRaw('reservation_id, SUM(amount) as tong_coc')
            ->groupBy('reservation_id')
            ->pluck('tong_coc', 'reservation_id');

        if ($cocTheoDatBan->isEmpty()) {
            return collect();
        }

        $homNay = Carbon::today();

        return Reservation::query()
            ->whereIn('id', $cocTheoDatBan->keys())
            ->whereIn('status', [ReservationStatus::NoShow, ReservationStatus::Cancelled])
            ->where('deposit_status', DepositStatus::Unhandled)
            ->with('customer:id,name,phone')
            ->orderBy('reserved_at')
            ->get()
            ->map(fn (Reservation $dat): array => [
                'reservation_id' => $dat->id,
                'status' => $dat->status->value,
                'khach' => $dat->customer?->name ?? 'Khách vãng lai',
                'dien_thoai' => $dat->customer?->phone,
                'dat_luc' => $dat->reserved_at->format('d/m/Y H:i'),
                'so_khach' => $dat->guest_count,
                'ly_do' => $dat->status_reason,
                'tien_coc' => (int) $cocTheoDatBan[$dat->id],
                'tien_coc_text' => Money::fromInt((int) $cocTheoDatBan[$dat->id])->format(),
                'so_ngay_treo' => (int) $dat->reserved_at->copy()->startOfDay()->diffInDays($homNay),
            ])
            ->values();
    }
}
