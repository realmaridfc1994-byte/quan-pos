<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Ordering\Models\TableSession;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TableSession */
final class BillResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $conLai = Money::fromInt($this->total_amount)->minus(Money::fromInt($this->paid_amount));

        return [
            'id' => $this->id,
            'code' => $this->code,
            'status' => $this->status->value,
            'subtotal_amount' => $this->subtotal_amount,
            'subtotal_amount_text' => Money::fromInt($this->subtotal_amount)->format(),
            'discount_amount' => $this->discount_amount,
            'discount_amount_text' => Money::fromInt($this->discount_amount)->format(),
            'discount_reason' => $this->discount_reason,
            'total_amount' => $this->total_amount,
            'total_amount_text' => Money::fromInt($this->total_amount)->format(),
            'paid_amount' => $this->paid_amount,
            'paid_amount_text' => Money::fromInt($this->paid_amount)->format(),
            'remaining_amount' => $conLai->amount,
            'remaining_amount_text' => $conLai->format(),
            'payments' => PaymentResource::collection($this->whenLoaded('payments')),
        ];
    }
}
