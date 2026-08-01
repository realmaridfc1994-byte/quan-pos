<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Actions;

use App\Domain\Ordering\DTO\SendToKitchenData;
use App\Domain\Ordering\Events\OrderSentToKitchen;
use App\Domain\Ordering\Models\Order;
use App\Exceptions\DomainException;

/**
 * Gửi một phiếu đã tạo (PlaceOrder, Bước 4) xuống bếp/quầy.
 *
 * PlaceOrder đã đảm bảo một phiếu chỉ chứa món của một nơi làm (M7) và đã ghi
 * sent_at ngay lúc gọi món — Action này không sửa lại giờ đó, chỉ kiểm tra
 * phòng thủ rồi bắn event cho hàng đợi in (Bước 9) lắng nghe.
 *
 * M3 (chống bấm "Gửi bếp" hai lần): dựa vào middleware `idempotent` sẵn có
 * của route (header Idempotency-Key) — giống mọi endpoint ghi khác trong dự
 * án — không tự chế thêm cột hay uuid riêng cho việc này.
 */
final class SendToKitchen
{
    public function handle(SendToKitchenData $data): Order
    {
        $order = Order::query()->with('items.product.category')->findOrFail($data->orderId);

        foreach ($order->items as $item) {
            if ($item->product->effectiveStation() !== $order->station) {
                throw new DomainException('Phiếu có món không khớp nơi làm đã khai báo.');
            }
        }

        OrderSentToKitchen::dispatch($order);

        return $order;
    }
}
