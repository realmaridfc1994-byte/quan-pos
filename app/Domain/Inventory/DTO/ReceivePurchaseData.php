<?php

declare(strict_types=1);

namespace App\Domain\Inventory\DTO;

use Illuminate\Foundation\Http\FormRequest;

final readonly class ReceivePurchaseData
{
    public function __construct(
        public int $purchaseId,
        public int $receivedByUserId,
    ) {}

    public static function fromRequest(FormRequest $request): self
    {
        return new self(
            purchaseId: (int) $request->route('purchase')->id,
            receivedByUserId: (int) $request->user()->id,
        );
    }
}
