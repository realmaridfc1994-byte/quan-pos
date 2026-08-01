<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Actions;

use App\Domain\Ordering\Enums\OrderItemStatus;
use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Models\OrderItem;
use App\Domain\Ordering\Models\TableSession;
use App\Support\Money;

/**
 * T2: subtotal_amount của một lượt khách = tổng thành tiền mọi dòng món
 * CHƯA HUỶ thuộc mọi phiếu CHƯA HUỶ. Đọc thẳng line_amount (cột database tự
 * tính, xem M5) — không tính lại từ bảng products.
 *
 * Gọi lại Action này sau MỌI thay đổi order_items của một lượt khách. Discount
 * (Bước 7) chưa tồn tại ở bước này nên total_amount luôn bằng subtotal_amount,
 * vẫn phải ghi cả hai cột để thoả ck_table_sessions_total.
 */
final class RecalculateSessionSubtotal
{
    public function handle(TableSession $tableSession): TableSession
    {
        $tongTien = OrderItem::query()
            ->whereHas('order', fn ($q) => $q
                ->where('table_session_id', $tableSession->id)
                ->where('status', '!=', OrderStatus::Cancelled))
            ->where('status', '!=', OrderItemStatus::Cancelled)
            ->get()
            ->reduce(
                fn (Money $tong, OrderItem $item) => $tong->plus(Money::fromInt($item->line_amount)),
                Money::zero()
            );

        $giamGia = Money::fromInt($tableSession->discount_amount);

        $tableSession->update([
            'subtotal_amount' => $tongTien->amount,
            'total_amount' => $tongTien->minus($giamGia)->amount,
        ]);

        return $tableSession;
    }
}
