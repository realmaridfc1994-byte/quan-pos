<?php

declare(strict_types=1);

namespace App\Domain\Inventory\DTO;

use Illuminate\Foundation\Http\FormRequest;

final readonly class CancelPurchaseData
{
    public function __construct(
        public int $purchaseId,
        public string $reason,
    ) {}

    public static function fromRequest(FormRequest $request): self
    {
        return new self(
            purchaseId: (int) $request->route('purchase')->id,
            reason: (string) $request->input('reason'),
        );
    }
}
