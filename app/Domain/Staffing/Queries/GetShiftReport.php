<?php

declare(strict_types=1);

namespace App\Domain\Staffing\Queries;

use App\Domain\Billing\Enums\PaymentMethod;
use App\Domain\Billing\Enums\PaymentStatus;
use App\Domain\Billing\Models\Payment;
use App\Domain\Ordering\Enums\OrderItemStatus;
use App\Domain\Ordering\Models\OrderItem;
use App\Domain\Ordering\Models\TableSession;
use App\Domain\Staffing\Enums\ShiftStatus;
use App\Domain\Staffing\Models\Shift;
use App\Support\CashVariance;
use App\Support\Money;

/**
 * Báo cáo cuối ca (Z-report) — đọc thuần, không ghi gì.
 *
 * "Người huỷ" trong danh sách món đã huỷ là `cancelled_by_user_id` — người
 * BẤM huỷ, không phải người CẦM PIN duyệt (H5): schema hiện không lưu riêng
 * danh tính người duyệt PIN trên `order_items`, chỉ lưu ai thực hiện thao
 * tác. Không tự thêm cột — đổi schema phải hỏi trước (xem CLAUDE.md mục 7.2).
 */
final class GetShiftReport
{
    /** @return array<string, mixed> */
    public function handle(int $shiftId): array
    {
        $shift = Shift::query()->with(['openedBy', 'closedBy'])->findOrFail($shiftId);

        $doanhThuTheoHinhThuc = Payment::query()
            ->where('shift_id', $shift->id)
            ->where('status', PaymentStatus::Completed)
            ->selectRaw('method, SUM(amount) as tong')
            ->groupBy('method')
            ->pluck('tong', 'method');

        $tienMat = Money::fromInt((int) ($doanhThuTheoHinhThuc[PaymentMethod::Cash->value] ?? 0));
        $chuyenKhoan = Money::fromInt((int) ($doanhThuTheoHinhThuc[PaymentMethod::Transfer->value] ?? 0));

        $monDaHuy = OrderItem::query()
            ->whereHas('order.tableSession', fn ($q) => $q->where('shift_id', $shift->id))
            ->where('status', OrderItemStatus::Cancelled)
            ->with('cancelledBy')
            ->get()
            ->map(fn (OrderItem $item) => [
                'product_name' => $item->product_name,
                'variant_name' => $item->variant_name,
                'quantity' => $item->quantity,
                'cancel_reason' => $item->cancel_reason,
                'cancelled_by' => $item->cancelledBy?->name,
                'cancelled_at' => $item->cancelled_at?->toIso8601String(),
            ])
            ->values();

        $giamGia = TableSession::query()
            ->where('shift_id', $shift->id)
            ->where('discount_amount', '>', 0)
            ->get()
            ->map(fn (TableSession $luot) => [
                'table_session_code' => $luot->code,
                'discount_amount' => $luot->discount_amount,
                'discount_amount_text' => Money::fromInt($luot->discount_amount)->format(),
                'discount_reason' => $luot->discount_reason,
            ])
            ->values();

        $daDong = $shift->status === ShiftStatus::Closed;

        return [
            'shift' => [
                'id' => $shift->id,
                'code' => $shift->code,
                'status' => $shift->status->value,
                'opened_by' => $shift->openedBy->name,
                'opened_at' => $shift->opened_at->toIso8601String(),
                'closed_by' => $shift->closedBy?->name,
                'closed_at' => $shift->closed_at?->toIso8601String(),
            ],
            'so_luot_khach' => TableSession::query()->where('shift_id', $shift->id)->count(),
            'doanh_thu' => [
                'tien_mat' => $tienMat->amount,
                'tien_mat_text' => $tienMat->format(),
                'chuyen_khoan' => $chuyenKhoan->amount,
                'chuyen_khoan_text' => $chuyenKhoan->format(),
                'tong' => $tienMat->plus($chuyenKhoan)->amount,
                'tong_text' => $tienMat->plus($chuyenKhoan)->format(),
            ],
            'mon_da_huy' => $monDaHuy,
            'giam_gia' => $giamGia,
            'doi_soat_ket' => $daDong ? [
                'opening_cash' => $shift->opening_cash,
                'expected_cash' => $shift->expected_cash,
                'counted_cash' => $shift->counted_cash,
                'variance' => CashVariance::between(
                    Money::fromInt($shift->counted_cash),
                    Money::fromInt($shift->expected_cash)
                )->amount,
                'variance_text' => CashVariance::between(
                    Money::fromInt($shift->counted_cash),
                    Money::fromInt($shift->expected_cash)
                )->format(),
            ] : null,
        ];
    }
}
