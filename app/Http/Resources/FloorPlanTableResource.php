<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Ordering\Models\DiningTable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DiningTable */
final class FloorPlanTableResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $banChiem = $this->tableSessionTables->first();

        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'area' => $this->area,
            'seats' => $this->seats,
            'is_occupied' => $banChiem !== null,
            'session' => $banChiem !== null ? [
                'id' => $banChiem->tableSession->id,
                'code' => $banChiem->tableSession->code,
                'guest_count' => $banChiem->tableSession->guest_count,
                'opened_at' => $banChiem->tableSession->opened_at->toIso8601String(),
                'is_primary' => $banChiem->is_primary,
            ] : null,
        ];
    }
}
