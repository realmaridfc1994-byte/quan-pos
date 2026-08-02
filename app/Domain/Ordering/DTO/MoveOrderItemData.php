<?php

declare(strict_types=1);

namespace App\Domain\Ordering\DTO;

use Illuminate\Foundation\Http\FormRequest;

final readonly class MoveOrderItemData
{
    /** @param list<int> $orderItemIds */
    public function __construct(
        public int $sourceTableSessionId,
        public int $targetTableSessionId,
        public array $orderItemIds,
        public int $actorUserId,
    ) {}

    public static function fromRequest(FormRequest $request): self
    {
        return new self(
            sourceTableSessionId: (int) $request->route('tableSession')->id,
            targetTableSessionId: $request->integer('target_table_session_id'),
            orderItemIds: array_map('intval', $request->input('order_item_ids', [])),
            actorUserId: (int) $request->user()->id,
        );
    }
}
