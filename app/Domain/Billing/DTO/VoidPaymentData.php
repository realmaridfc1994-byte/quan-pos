<?php

declare(strict_types=1);

namespace App\Domain\Billing\DTO;

use Illuminate\Foundation\Http\FormRequest;

final readonly class VoidPaymentData
{
    public function __construct(
        public int $paymentId,
        public string $reason,
        public int $voidedByUserId,
    ) {}

    public static function fromRequest(FormRequest $request): self
    {
        return new self(
            paymentId: (int) $request->route('payment')->id,
            reason: $request->string('reason')->toString(),
            voidedByUserId: (int) $request->user()->id,
        );
    }
}
