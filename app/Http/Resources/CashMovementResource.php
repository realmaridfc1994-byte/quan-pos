<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Staffing\Models\CashMovement;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CashMovement */
final class CashMovementResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'shift_id' => $this->shift_id,
            'direction' => $this->direction->value,
            'amount' => $this->amount,
            'amount_text' => Money::fromInt($this->amount)->format(),
            'reason' => $this->reason,
            'created_by' => $this->whenLoaded('createdBy', fn () => [
                'id' => $this->createdBy->id,
                'name' => $this->createdBy->name,
            ]),
            'occurred_at' => $this->occurred_at->toIso8601String(),
        ];
    }
}
