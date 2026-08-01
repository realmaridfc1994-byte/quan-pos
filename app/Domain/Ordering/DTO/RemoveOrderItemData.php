<?php

declare(strict_types=1);

namespace App\Domain\Ordering\DTO;

use Illuminate\Foundation\Http\FormRequest;

final readonly class RemoveOrderItemData
{
    public function __construct(
        public int $orderId,
        public int $orderItemId,
        public ?string $reason,
        public int $removedByUserId,
    ) {}

    public static function fromRequest(FormRequest $request): self
    {
        return new self(
            orderId: (int) $request->route('order')->id,
            orderItemId: (int) $request->route('orderItem')->id,
            reason: $request->input('reason'),
            removedByUserId: (int) $request->user()->id,
        );
    }
}
