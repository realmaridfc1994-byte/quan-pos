<?php

declare(strict_types=1);

namespace App\Domain\Staffing\DTO;

use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;

final readonly class OpenShiftData
{
    public function __construct(
        public Money $openingCash,
        public int $openedByUserId,
    ) {}

    public static function fromRequest(FormRequest $request): self
    {
        return new self(
            openingCash: Money::fromInt($request->integer('opening_cash')),
            openedByUserId: (int) $request->user()->id,
        );
    }
}
