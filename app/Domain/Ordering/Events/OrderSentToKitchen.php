<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Events;

use App\Domain\Ordering\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Một phiếu vừa được gửi xuống bếp/quầy. Bước 9 sẽ lắng nghe event này để đẩy
 * việc in tem vào hàng đợi thật — bước này chỉ bắn event, chưa in gì cả.
 */
final class OrderSentToKitchen
{
    use Dispatchable;

    public function __construct(
        public readonly Order $order,
    ) {}
}
