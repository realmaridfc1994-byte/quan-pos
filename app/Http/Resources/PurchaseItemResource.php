<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Inventory\Models\PurchaseItem;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PurchaseItem */
final class PurchaseItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ingredient' => $this->whenLoaded('ingredient', fn () => [
                'id' => $this->ingredient->id,
                'name' => $this->ingredient->name,
            ]),
            'unit_name' => $this->unit_name,
            'qty_input' => $this->qty_input,
            'factor_snapshot' => $this->factor_snapshot,
            'qty_base' => $this->qty_base,
            'unit_cost' => $this->unit_cost,
            'unit_cost_text' => Money::fromInt($this->unit_cost)->format(),
            'line_cost' => $this->line_cost,
            'line_cost_text' => Money::fromInt($this->line_cost)->format(),
        ];
    }
}
