<?php

declare(strict_types=1);

namespace App\Domain\Ordering\DTO;

use Illuminate\Foundation\Http\FormRequest;

final readonly class UpdateOrderItemData
{
    public function __construct(
        public int $orderId,
        public int $orderItemId,
        public ?int $quantity,
        public ?string $note,
        public bool $noteProvided,
    ) {}

    public static function fromRequest(FormRequest $request): self
    {
        return new self(
            orderId: (int) $request->route('order')->id,
            orderItemId: (int) $request->route('orderItem')->id,
            quantity: $request->filled('quantity') ? $request->integer('quantity') : null,
            note: $request->input('note'),
            noteProvided: $request->has('note'),
        );
    }
}
