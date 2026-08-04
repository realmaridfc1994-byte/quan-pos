<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Actions;

use App\Domain\Billing\Enums\PaymentMethod;
use App\Domain\Billing\Enums\PaymentStatus;
use App\Domain\Billing\Models\Payment;
use App\Domain\Ordering\Enums\OrderItemStatus;
use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Models\OrderItem;
use App\Domain\Ordering\Models\TableSession;
use App\Domain\Reporting\Models\DailySummary;
use App\Domain\Reporting\Models\ProductSaleDaily;
use App\Domain\Staffing\Enums\ShiftStatus;
use App\Domain\Staffing\Models\Shift;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Tổng hợp MỘT NGÀY vào `daily_summaries`/`product_sales_daily` — Phase 2
 * Bước 8. Nguồn chân lý luôn là `orders`/`order_items`/`payments`/
 * `table_sessions`/`shifts`; hai bảng tổng hợp chỉ là bản chốt lại để màn
 * hình chủ quán đọc, KHÔNG BAO GIỜ đọc ngược từ hai bảng đó.
 *
 * Luôn TÍNH LẠI TỪ ĐẦU rồi ghi đè (updateOrCreate / xoá-rồi-chèn lại) — gọi
 * lại Action này nhiều lần cho CÙNG một ngày (đóng ca hai lần trong ngày,
 * hoặc chạy tay lại bằng `report:summarize`) luôn ra đúng MỘT kết quả, không
 * cộng dồn chồng lên số cũ.
 *
 * "Ngày kinh doanh" của một bản ghi lấy theo mốc THỜI ĐIỂM RIÊNG của chính
 * nó (table_sessions.opened_at, orders.sent_at, order_items.cancelled_at,
 * payments.paid_at, shifts.opened_at) — không đi vòng qua ca, nên ca kéo dài
 * qua nửa đêm không làm lệch ngày của việc xảy ra TRƯỚC nửa đêm.
 */
final class SummarizeDailyReport
{
    public function handle(string $date): DailySummary
    {
        $ngay = Carbon::parse($date)->startOfDay();

        return DB::transaction(function () use ($ngay): DailySummary {
            $tomTatNgay = $this->tongHopNgay($ngay);
            $tongHopMon = $this->tongHopTheoMon($ngay);

            ProductSaleDaily::query()->where('date', $ngay->toDateString())->delete();
            foreach ($tongHopMon as $dong) {
                ProductSaleDaily::query()->create([
                    'date' => $ngay->toDateString(),
                    'product_id' => $dong['product_id'],
                    'product_variant_id' => $dong['product_variant_id'],
                    'quantity_sold' => $dong['quantity_sold'],
                    'revenue_amount' => $dong['revenue_amount'],
                ]);
            }

            return DailySummary::query()->updateOrCreate(
                ['date' => $ngay->toDateString()],
                $tomTatNgay,
            );
        });
    }

    /** @return array<string, int> */
    private function tongHopNgay(Carbon $ngay): array
    {
        $doanhThuTheoHinhThuc = Payment::query()
            ->whereDate('paid_at', $ngay)
            ->where('status', PaymentStatus::Completed)
            ->selectRaw('method, SUM(amount) as tong')
            ->groupBy('method')
            ->pluck('tong', 'method');

        $tienMat = (int) ($doanhThuTheoHinhThuc[PaymentMethod::Cash->value] ?? 0);
        $chuyenKhoan = (int) ($doanhThuTheoHinhThuc[PaymentMethod::Transfer->value] ?? 0);

        $luotKhach = TableSession::query()->whereDate('opened_at', $ngay);

        $monHuy = OrderItem::query()
            ->where('status', OrderItemStatus::Cancelled)
            ->whereDate('cancelled_at', $ngay);

        $chenhLechKet = (int) Shift::query()
            ->where('status', ShiftStatus::Closed)
            ->whereDate('opened_at', $ngay)
            // CAST sang SIGNED trước khi trừ: cả hai cột là BIGINT UNSIGNED,
            // trừ trực tiếp khi kết quả âm (thiếu tiền) sẽ tràn số thay vì ra
            // giá trị âm đúng như PHP mong đợi.
            ->selectRaw('COALESCE(SUM(CAST(counted_cash AS SIGNED) - CAST(expected_cash AS SIGNED)), 0) as chenh_lech')
            ->value('chenh_lech');

        return [
            'revenue_amount' => $tienMat + $chuyenKhoan,
            'cash_amount' => $tienMat,
            'transfer_amount' => $chuyenKhoan,
            'discount_amount' => (int) (clone $luotKhach)->sum('discount_amount'),
            'table_session_count' => (clone $luotKhach)->count(),
            'guest_count' => (int) (clone $luotKhach)->sum('guest_count'),
            'cancelled_item_count' => (int) (clone $monHuy)->count(),
            'cancelled_item_amount' => (int) (clone $monHuy)->sum('line_amount'),
            'cash_variance_amount' => $chenhLechKet,
        ];
    }

    /** @return list<array{product_id: int, product_variant_id: int, quantity_sold: int, revenue_amount: int}> */
    private function tongHopTheoMon(Carbon $ngay): array
    {
        return OrderItem::query()
            ->whereHas('order', fn ($q) => $q
                ->whereDate('sent_at', $ngay)
                ->where('status', '!=', OrderStatus::Cancelled))
            ->where('status', '!=', OrderItemStatus::Cancelled)
            ->selectRaw('product_id, product_variant_id, SUM(quantity) as so_luong, SUM(line_amount) as doanh_thu')
            ->groupBy('product_id', 'product_variant_id')
            ->get()
            ->map(fn ($dong) => [
                'product_id' => (int) $dong->product_id,
                'product_variant_id' => (int) $dong->product_variant_id,
                'quantity_sold' => (int) $dong->so_luong,
                'revenue_amount' => (int) $dong->doanh_thu,
            ])
            ->all();
    }
}
