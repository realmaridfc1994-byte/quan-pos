<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Ordering\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Order */
final class OrderResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'sequence_no' => $this->sequence_no,
            'station' => $this->station->value,
            'status' => $this->status->value,
            'created_by' => $this->whenLoaded('createdBy', fn () => [
                'id' => $this->createdBy->id,
                'name' => $this->createdBy->name,
            ]),
            'sent_at' => $this->sent_at->toIso8601String(),
            'note' => $this->note,
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
