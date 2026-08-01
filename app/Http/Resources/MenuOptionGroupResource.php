<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Catalog\Models\OptionGroup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin OptionGroup */
final class MenuOptionGroupResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'is_required' => $this->is_required,
            'min_select' => $this->min_select,
            'max_select' => $this->max_select,
            'options' => MenuOptionResource::collection($this->whenLoaded('options')),
        ];
    }
}
