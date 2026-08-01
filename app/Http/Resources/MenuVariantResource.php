<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Catalog\Models\ProductVariant;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ProductVariant */
final class MenuVariantResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'price' => $this->price,
            'price_text' => Money::fromInt($this->price)->format(),
            'is_default' => $this->is_default,
        ];
    }
}
