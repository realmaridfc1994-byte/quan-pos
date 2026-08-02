<?php

declare(strict_types=1);

namespace App\Domain\Ordering\DTO;

use Illuminate\Foundation\Http\FormRequest;

final readonly class SplitTableSessionData
{
    /**
     * @param  list<int>  $orderItemIds  Các dòng món chuyển sang lượt khách mới
     * @param  list<int>  $diningTableIds  Các bàn ĐANG THUỘC lượt gốc, nhả ra để gán cho lượt mới
     */
    public function __construct(
        public int $sourceTableSessionId,
        public array $orderItemIds,
        public array $diningTableIds,
        public int $guestCount,
        public int $actorUserId,
    ) {}

    public static function fromRequest(FormRequest $request): self
    {
        return new self(
            sourceTableSessionId: (int) $request->route('tableSession')->id,
            orderItemIds: array_map('intval', $request->input('order_item_ids', [])),
            diningTableIds: array_map('intval', $request->input('dining_table_ids', [])),
            guestCount: $request->integer('guest_count'),
            actorUserId: (int) $request->user()->id,
        );
    }
}
