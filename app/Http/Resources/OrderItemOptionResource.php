<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Ordering\Models\OrderItemOption;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin OrderItemOption */
final class OrderItemOptionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'option_group_name' => $this->option_group_name,
            'option_name' => $this->option_name,
            'price_delta' => $this->price_delta,
            'price_delta_text' => Money::fromInt($this->price_delta)->format(),
        ];
    }
}
