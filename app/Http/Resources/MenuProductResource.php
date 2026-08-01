<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Catalog\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Product */
final class MenuProductResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'image_path' => $this->image_path,
            'effective_station' => $this->effectiveStation()->value,
            'variants' => MenuVariantResource::collection($this->whenLoaded('variants')),
            'option_groups' => MenuOptionGroupResource::collection($this->whenLoaded('optionGroups')),
        ];
    }
}
