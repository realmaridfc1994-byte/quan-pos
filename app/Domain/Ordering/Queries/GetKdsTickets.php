<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Queries;

use App\Domain\Catalog\Enums\Station;
use App\Domain\Ordering\Enums\OrderItemStatus;
use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Models\Order;
use Illuminate\Support\Collection;

/**
 * Danh sách phiếu cho màn hình bếp/quầy — chỉ phiếu còn việc phải làm
 * (sent, preparing), phiếu đã served không hiện nữa. Dùng cho hỏi lại mỗi
 * vài giây (polling) nên chỉ nạp đúng thứ cần hiển thị, không N+1.
 */
final class GetKdsTickets
{
    /** @return Collection<int, Order> */
    public function handle(Station $station): Collection
    {
        return Order::query()
            ->where('station', $station)
            ->whereIn('status', [OrderStatus::Sent, OrderStatus::Preparing])
            ->with([
                'tableSession' => fn ($q) => $q->with(['tables' => fn ($t) => $t
                    ->whereNull('detached_at')
                    ->where('is_primary', true)
                    ->with('diningTable'),
                ]),
                'items' => fn ($q) => $q->where('status', '!=', OrderItemStatus::Cancelled)->orderBy('id'),
            ])
            ->orderBy('sent_at')
            ->get();
    }
}
