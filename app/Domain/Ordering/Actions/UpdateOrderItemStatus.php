<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Actions;

use App\Domain\Inventory\Actions\DeductStockForServedItem;
use App\Domain\Ordering\DTO\UpdateOrderItemStatusData;
use App\Domain\Ordering\Enums\OrderItemStatus;
use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Models\Order;
use App\Domain\Ordering\Models\OrderItem;
use App\Domain\Staffing\Enums\ShiftStatus;
use App\Domain\Staffing\Models\Shift;
use App\Support\StatusTransition;
use Illuminate\Support\Facades\DB;

/**
 * Bếp/quầy đánh dấu một dòng món đã làm xong.
 *
 * order_items chỉ có đúng một bước tới hợp lệ: ordered → served (huỷ món đi
 * nhánh riêng ở Bước 6, không đụng ở đây).
 *
 * orders.status (sent → preparing → served) không có endpoint riêng — tự suy
 * ra từ tiến độ các dòng món: dòng đầu tiên xong thì phiếu sang "đang làm",
 * dòng cuối cùng xong thì phiếu sang "đã xong".
 *
 * Phase 3 Bước 5: món xong thì trừ kho theo định lượng, CÙNG transaction với
 * việc đặt served_at (docs/schema.md K.9) — trừ kho hỏng thì served_at không
 * được đặt, không dùng Event/Listener (CLAUDE.md mục 4.5 cấm nghiệp vụ ngầm).
 */
final class UpdateOrderItemStatus
{
    /** @var list<string> */
    private const CHUOI_MON = ['ordered', 'served'];

    /** @var list<string> */
    private const CHUOI_PHIEU = ['sent', 'preparing', 'served'];

    public function __construct(
        private readonly DeductStockForServedItem $deductStockForServedItem,
    ) {}

    public function handle(UpdateOrderItemStatusData $data): OrderItem
    {
        return DB::transaction(function () use ($data): OrderItem {
            $item = OrderItem::query()->lockForUpdate()->findOrFail($data->orderItemId);

            StatusTransition::kiemTra(self::CHUOI_MON, $item->status->value, OrderItemStatus::Served->value);

            $item->update([
                'status' => OrderItemStatus::Served,
                'served_at' => now(),
            ]);

            $caDangMo = Shift::query()->where('status', ShiftStatus::Open)->value('id');

            $this->deductStockForServedItem->handle($item, $data->updatedByUserId, $caDangMo);

            $order = Order::query()->lockForUpdate()->findOrFail($item->order_id);
            $this->capNhatTrangThaiPhieu($order);

            return $item->refresh();
        });
    }

    private function capNhatTrangThaiPhieu(Order $order): void
    {
        if ($order->status === OrderStatus::Sent) {
            StatusTransition::kiemTra(self::CHUOI_PHIEU, $order->status->value, OrderStatus::Preparing->value);
            $order->update(['status' => OrderStatus::Preparing]);
        }

        $conMonChuaXong = OrderItem::query()
            ->where('order_id', $order->id)
            ->where('status', '!=', OrderItemStatus::Served)
            ->where('status', '!=', OrderItemStatus::Cancelled)
            ->exists();

        if (! $conMonChuaXong) {
            $order->refresh();
            StatusTransition::kiemTra(self::CHUOI_PHIEU, $order->status->value, OrderStatus::Served->value);
            $order->update(['status' => OrderStatus::Served, 'served_at' => now()]);
        }
    }
}
