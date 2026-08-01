<?php

declare(strict_types=1);

namespace App\Domain\Ordering\DTO;

use Illuminate\Foundation\Http\FormRequest;

final readonly class UpdateOrderItemStatusData
{
    public function __construct(
        public int $orderItemId,
    ) {}

    public static function fromRequest(FormRequest $request): self
    {
        return new self(
            orderItemId: (int) $request->route('orderItem')->id,
        );
    }
}
