<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Inventory\Models\Ingredient;
use App\Domain\Reporting\Models\IngredientWasteMonthly;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IngredientWasteMonthly>
 */
class IngredientWasteMonthlyFactory extends Factory
{
    protected $model = IngredientWasteMonthly::class;

    public function definition(): array
    {
        return [
            'month' => now()->startOfMonth()->toDateString(),
            'ingredient_id' => Ingredient::factory(),
            'waste_qty' => 0,
            'waste_cost' => 0,
        ];
    }
}
