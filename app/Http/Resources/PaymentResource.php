<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Billing\Models\Payment;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Payment */
final class PaymentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'method' => $this->method->value,
            'method_label' => $this->method->label(),
            'amount' => $this->amount,
            'amount_text' => Money::fromInt($this->amount)->format(),
            'tendered_amount' => $this->tendered_amount,
            'tendered_amount_text' => $this->tendered_amount !== null
                ? Money::fromInt($this->tendered_amount)->format()
                : null,
            'change_amount' => $this->change_amount,
            'change_amount_text' => Money::fromInt($this->change_amount)->format(),
            'reference' => $this->reference,
            'status' => $this->status->value,
            'paid_at' => $this->paid_at->toIso8601String(),
            'voided_at' => $this->voided_at?->toIso8601String(),
            'void_reason' => $this->void_reason,
            'received_by' => $this->whenLoaded('receivedBy', fn () => [
                'id' => $this->receivedBy->id,
                'name' => $this->receivedBy->name,
            ]),
            'voided_by' => $this->whenLoaded('voidedBy', fn () => $this->voidedBy === null ? null : [
                'id' => $this->voidedBy->id,
                'name' => $this->voidedBy->name,
            ]),
        ];
    }
}
