<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Inventory\Models\Ingredient;
use App\Domain\Inventory\Models\Recipe;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Recipe>
 */
class RecipeFactory extends Factory
{
    protected $model = Recipe::class;

    public function definition(): array
    {
        return [
            'product_variant_id' => ProductVariant::factory()->state(['deducts_stock' => true]),
            'ingredient_id' => Ingredient::factory(),
            'qty_base' => fake()->numberBetween(1, 500),
            'note' => null,
        ];
    }
}
