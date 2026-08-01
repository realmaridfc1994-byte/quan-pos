<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Catalog\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Category */
final class MenuCategoryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'station' => $this->station->value,
            'updated_at' => $this->updated_at->toIso8601String(),
            'products' => MenuProductResource::collection($this->whenLoaded('products')),
        ];
    }
}
