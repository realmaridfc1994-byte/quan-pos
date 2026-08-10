<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Inventory\Models\Ingredient;
use App\Domain\Inventory\Models\StockTake;
use App\Domain\Inventory\Models\StockTakeItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockTakeItem>
 */
class StockTakeItemFactory extends Factory
{
    protected $model = StockTakeItem::class;

    public function definition(): array
    {
        return [
            'stock_take_id' => StockTake::factory(),
            'ingredient_id' => Ingredient::factory(),
            'system_qty' => 100,
            'counted_qty' => null,
            'note' => null,
        ];
    }

    public function counted(int $qty): static
    {
        return $this->state(fn (array $attributes) => ['counted_qty' => $qty]);
    }
}
