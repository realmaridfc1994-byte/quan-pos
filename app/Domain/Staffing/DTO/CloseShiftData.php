<?php

declare(strict_types=1);

namespace App\Domain\Staffing\DTO;

use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;

final readonly class CloseShiftData
{
    public function __construct(
        public int $shiftId,
        public Money $countedCash,
        public ?string $note,
        public int $closedByUserId,
    ) {}

    public static function fromRequest(FormRequest $request): self
    {
        return new self(
            shiftId: (int) $request->route('shift')->id,
            countedCash: Money::fromInt($request->integer('counted_cash')),
            note: $request->input('note'),
            closedByUserId: (int) $request->user()->id,
        );
    }
}
