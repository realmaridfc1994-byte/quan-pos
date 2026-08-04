<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Ordering\Models\TableSession;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TableSession */
final class TableSessionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            // Phase 2 Bước 3 mục 4: máy POS cần uuid của MỌI lượt khách (không
            // chỉ lượt mở lúc offline) để khoá thu tiền offline theo thiết bị —
            // xem resources/js/lib/queue.js coThuTienOfflineTuMayKhac().
            'uuid' => $this->uuid,
            'code' => $this->code,
            'status' => $this->status->value,
            'guest_count' => $this->guest_count,
            'opened_by' => $this->whenLoaded('openedBy', fn () => [
                'id' => $this->openedBy->id,
                'name' => $this->openedBy->name,
            ]),
            'opened_at' => $this->opened_at->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'voided_at' => $this->voided_at?->toIso8601String(),
            'void_reason' => $this->void_reason,
            'subtotal_amount' => $this->subtotal_amount,
            'subtotal_amount_text' => Money::fromInt($this->subtotal_amount)->format(),
            'total_amount' => $this->total_amount,
            'total_amount_text' => Money::fromInt($this->total_amount)->format(),
            'orders' => OrderResource::collection($this->whenLoaded('orders')),
            'tables' => $this->whenLoaded('tables', fn () => $this->tables
                ->whereNull('detached_at')
                ->map(fn ($banChiem) => [
                    'id' => $banChiem->diningTable->id,
                    'code' => $banChiem->diningTable->code,
                    'name' => $banChiem->diningTable->name,
                    'is_primary' => $banChiem->is_primary,
                ])
                ->values()),
        ];
    }
}
