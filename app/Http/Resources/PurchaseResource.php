<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Inventory\Models\Purchase;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Purchase */
final class PurchaseResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),

            'supplier' => $this->whenLoaded('supplier', fn () => [
                'id' => $this->supplier->id,
                'name' => $this->supplier->name,
            ]),

            'total_cost' => $this->total_cost,
            'total_cost_text' => Money::fromInt($this->total_cost)->format(),

            'note' => $this->note,
            'invoice_no' => $this->invoice_no,

            'received_at' => $this->received_at?->toIso8601String(),
            'received_by' => $this->whenLoaded('receivedBy', fn () => $this->receivedBy !== null ? [
                'id' => $this->receivedBy->id,
                'name' => $this->receivedBy->name,
            ] : null),

            'cancel_reason' => $this->cancel_reason,

            'created_by' => $this->whenLoaded('createdBy', fn () => [
                'id' => $this->createdBy->id,
                'name' => $this->createdBy->name,
            ]),
            'created_at' => $this->created_at->toIso8601String(),

            'items' => PurchaseItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
