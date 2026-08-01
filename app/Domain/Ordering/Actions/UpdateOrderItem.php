<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Actions;

use App\Domain\Ordering\DTO\UpdateOrderItemData;
use App\Domain\Ordering\Enums\OrderItemStatus;
use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Models\Order;
use App\Domain\Ordering\Models\OrderItem;
use App\Exceptions\DomainException;
use Illuminate\Support\Facades\DB;

/** Sửa số lượng/ghi chú một dòng món — chỉ khi món chưa gửi bếp (order còn "sent", dòng món còn "ordered"). */
final class UpdateOrderItem
{
    public function handle(UpdateOrderItemData $data): OrderItem
    {
        return DB::transaction(function () use ($data): OrderItem {
            $order = Order::query()->lockForUpdate()->findOrFail($data->orderId);
            $orderItem = OrderItem::query()->where('order_id', $order->id)->findOrFail($data->orderItemId);

            $this->kiemTraChuaGuiBep($order, $orderItem);

            $duLieuSua = [];
            if ($data->quantity !== null) {
                $duLieuSua['quantity'] = $data->quantity;
            }
            if ($data->noteProvided) {
                $duLieuSua['note'] = $data->note;
            }

            if ($duLieuSua !== []) {
                $orderItem->update($duLieuSua);
            }

            app(RecalculateSessionSubtotal::class)->handle($order->tableSession);

            return $orderItem->refresh();
        });
    }

    private function kiemTraChuaGuiBep(Order $order, OrderItem $orderItem): void
    {
        if ($order->status !== OrderStatus::Sent || $orderItem->status !== OrderItemStatus::Ordered) {
            throw new DomainException('Món này đã bếp xử lý hoặc đã huỷ, không sửa được nữa.');
        }
    }
}
