<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Inventory\Models\Ingredient;
use App\Domain\Inventory\Models\StockBalance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockBalance>
 */
class StockBalanceFactory extends Factory
{
    protected $model = StockBalance::class;

    public function definition(): array
    {
        return [
            'ingredient_id' => Ingredient::factory(),
            'qty' => 0,
            'total_cost' => 0,
            'last_movement_id' => null,
            'updated_at' => now(),
        ];
    }
}
