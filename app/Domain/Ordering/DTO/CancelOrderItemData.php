<?php

declare(strict_types=1);

namespace App\Domain\Ordering\DTO;

use Illuminate\Foundation\Http\FormRequest;

final readonly class CancelOrderItemData
{
    public function __construct(
        public int $orderId,
        public int $orderItemId,
        public int $quantity,
        public string $reason,
        public int $cancelledByUserId,
        public ?int $approverUserId,
        public ?string $approverPin,
    ) {}

    public static function fromRequest(FormRequest $request): self
    {
        return new self(
            orderId: (int) $request->route('order')->id,
            orderItemId: (int) $request->route('orderItem')->id,
            quantity: $request->integer('quantity'),
            reason: $request->string('reason')->toString(),
            cancelledByUserId: (int) $request->user()->id,
            approverUserId: $request->filled('approver_user_id') ? $request->integer('approver_user_id') : null,
            approverPin: $request->input('approver_pin'),
        );
    }
}
