<?php

declare(strict_types=1);

namespace App\Domain\Ordering\DTO;

use Illuminate\Foundation\Http\FormRequest;

final readonly class SendToKitchenData
{
    public function __construct(
        public int $orderId,
    ) {}

    public static function fromRequest(FormRequest $request): self
    {
        return new self(
            orderId: (int) $request->route('order')->id,
        );
    }
}
