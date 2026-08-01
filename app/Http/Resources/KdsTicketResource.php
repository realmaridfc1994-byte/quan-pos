<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Ordering\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Order */
final class KdsTicketResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $banChinh = $this->tableSession->tables->first()?->diningTable;

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'sequence_no' => $this->sequence_no,
            'station' => $this->station->value,
            'status' => $this->status->value,
            'sent_at' => $this->sent_at->toIso8601String(),
            'table_session_code' => $this->tableSession->code,
            'table_code' => $banChinh?->code,
            'note' => $this->note,
            'items' => KdsTicketItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
