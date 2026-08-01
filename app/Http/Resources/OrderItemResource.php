<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Ordering\Models\OrderItem;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin OrderItem */
final class OrderItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_name' => $this->product_name,
            'variant_name' => $this->variant_name,
            'unit_price' => $this->unit_price,
            'options_amount' => $this->options_amount,
            'quantity' => $this->quantity,
            'line_amount' => $this->line_amount,
            'line_amount_text' => Money::fromInt($this->line_amount)->format(),
            'status' => $this->status->value,
            'note' => $this->note,
            'cancel_reason' => $this->cancel_reason,
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'options' => OrderItemOptionResource::collection($this->whenLoaded('options')),
        ];
    }
}
