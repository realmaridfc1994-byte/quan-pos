<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Staffing\Enums\ShiftStatus;
use App\Domain\Staffing\Models\Shift;
use App\Support\CashVariance;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Shift */
final class ShiftResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $daDong = $this->status === ShiftStatus::Closed;

        return [
            'id' => $this->id,
            'code' => $this->code,
            'status' => $this->status->value,

            'opened_by' => $this->whenLoaded('openedBy', fn () => [
                'id' => $this->openedBy->id,
                'name' => $this->openedBy->name,
            ]),
            'opened_at' => $this->opened_at->toIso8601String(),
            'opening_cash' => $this->opening_cash,
            'opening_cash_text' => Money::fromInt($this->opening_cash)->format(),

            'closed_by' => $this->whenLoaded('closedBy', fn () => $this->closedBy !== null ? [
                'id' => $this->closedBy->id,
                'name' => $this->closedBy->name,
            ] : null),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'counted_cash' => $this->counted_cash,
            'counted_cash_text' => $this->counted_cash !== null ? Money::fromInt($this->counted_cash)->format() : null,
            'expected_cash' => $this->expected_cash,
            'expected_cash_text' => $this->expected_cash !== null ? Money::fromInt($this->expected_cash)->format() : null,

            'variance_text' => $daDong
                ? CashVariance::between(Money::fromInt($this->counted_cash), Money::fromInt($this->expected_cash))->format()
                : null,

            'note' => $this->note,
        ];
    }
}
