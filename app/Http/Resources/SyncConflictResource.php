<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Sync\Models\SyncConflict;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SyncConflict */
final class SyncConflictResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'device_id' => $this->device_id,
            'conflict_kind' => $this->conflict_kind,
            'is_urgent' => $this->is_urgent,
            'message_vi' => $this->message_vi,
            'options' => $this->options,
            // Bước 5, mục 2 của yêu cầu 04/08: xung đột dính tiền phải duyệt
            // bằng PIN — máy POS cần biết trước để hỏi PIN ngay trong modal,
            // không để bị 422 rồi mới hỏi lại.
            'requires_pin' => in_array($this->conflict_kind, ['thu_tien_trung', 'thu_vuot_giam_gia'], true),
            'occurred_at' => $this->occurred_at->toIso8601String(),
            'received_at' => $this->received_at->toIso8601String(),
            'status' => $this->status->value,
            'resolution' => $this->resolution,
            'resolution_note' => $this->resolution_note,
            'resolved_at' => $this->resolved_at?->toIso8601String(),
        ];
    }
}
