<?php

declare(strict_types=1);

namespace App\Domain\Billing\DTO;

use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;

final readonly class CalculateBillData
{
    public function __construct(
        public int $tableSessionId,
        public Money $discountAmount,
        public ?string $discountReason,
        public int $requestedByUserId,
        public ?int $approverUserId,
        public ?string $approverPin,
    ) {}

    public static function fromRequest(FormRequest $request): self
    {
        return new self(
            tableSessionId: (int) $request->route('tableSession')->id,
            discountAmount: Money::fromInt($request->integer('discount_amount')),
            discountReason: $request->input('discount_reason'),
            requestedByUserId: (int) $request->user()->id,
            approverUserId: $request->filled('approver_user_id') ? $request->integer('approver_user_id') : null,
            approverPin: $request->input('approver_pin'),
        );
    }
}
