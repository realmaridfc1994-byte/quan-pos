<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Inventory\Models\Ingredient;
use App\Domain\Inventory\Models\Purchase;
use App\Domain\Inventory\Models\PurchaseItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseItem>
 */
class PurchaseItemFactory extends Factory
{
    protected $model = PurchaseItem::class;

    public function definition(): array
    {
        return [
            'purchase_id' => Purchase::factory(),
            'ingredient_id' => Ingredient::factory(),
            'unit_name' => 'Thùng',
            'qty_input' => fake()->numberBetween(1, 20),
            'factor_snapshot' => 24,
            'unit_cost' => fake()->numberBetween(50_000, 500_000),
        ];
    }
}
