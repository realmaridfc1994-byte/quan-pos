<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Inventory\Models\Ingredient;
use App\Domain\Inventory\Models\IngredientUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IngredientUnit>
 */
class IngredientUnitFactory extends Factory
{
    protected $model = IngredientUnit::class;

    /** Bộ đếm tăng dần, không dùng số ngẫu nhiên — CLAUDE.md mục 4.23. */
    private static int $sequence = 0;

    public function definition(): array
    {
        return [
            'ingredient_id' => Ingredient::factory(),
            'unit_name' => 'Đơn vị test '.str_pad((string) ++self::$sequence, 4, '0', STR_PAD_LEFT),
            'factor' => fake()->numberBetween(2, 100),
            'is_purchase_default' => false,
        ];
    }

    public function purchaseDefault(): static
    {
        return $this->state(fn (array $attributes) => ['is_purchase_default' => true]);
    }
}
