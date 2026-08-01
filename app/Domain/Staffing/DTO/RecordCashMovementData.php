<?php

declare(strict_types=1);

namespace App\Domain\Staffing\DTO;

use App\Domain\Staffing\Enums\CashDirection;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;

final readonly class RecordCashMovementData
{
    public function __construct(
        public int $shiftId,
        public CashDirection $direction,
        public Money $amount,
        public string $reason,
        public int $createdByUserId,
    ) {}

    public static function fromRequest(FormRequest $request): self
    {
        return new self(
            shiftId: (int) $request->route('shift')->id,
            direction: CashDirection::from($request->string('direction')->toString()),
            amount: Money::fromInt($request->integer('amount')),
            reason: $request->string('reason')->toString(),
            createdByUserId: (int) $request->user()->id,
        );
    }
}
