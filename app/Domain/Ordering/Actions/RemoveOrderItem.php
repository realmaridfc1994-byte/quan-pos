<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Actions;

use App\Domain\Ordering\DTO\RemoveOrderItemData;
use App\Domain\Ordering\Enums\OrderItemStatus;
use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Models\Order;
use App\Domain\Ordering\Models\OrderItem;
use App\Exceptions\DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Bỏ một dòng món khỏi phiếu khi CHƯA gửi bếp.
 *
 * Không xoá cứng dòng (luật CLAUDE.md mục 7.6/7.7) — đổi status sang
 * cancelled, ghi đủ ai/lúc nào/vì sao (H2). Khác CancelOrderItem của Bước 6
 * ở chỗ không cần duyệt PIN, vì món chưa từng ra khỏi màn hình phục vụ.
 */
final class RemoveOrderItem
{
    public function handle(RemoveOrderItemData $data): OrderItem
    {
        return DB::transaction(function () use ($data): OrderItem {
            $order = Order::query()->lockForUpdate()->findOrFail($data->orderId);
            $orderItem = OrderItem::query()->where('order_id', $order->id)->findOrFail($data->orderItemId);

            if ($order->status !== OrderStatus::Sent || $orderItem->status !== OrderItemStatus::Ordered) {
                throw new DomainException('Món này đã bếp xử lý hoặc đã huỷ, không bỏ được nữa.');
            }

            $orderItem->update([
                'status' => OrderItemStatus::Cancelled,
                'cancelled_by_user_id' => $data->removedByUserId,
                'cancelled_at' => now(),
                'cancel_reason' => $data->reason ?? 'Bỏ trước khi gửi bếp',
            ]);

            app(RecalculateSessionSubtotal::class)->handle($order->tableSession);

            return $orderItem;
        });
    }
}
