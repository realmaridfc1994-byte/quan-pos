<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Inventory\Enums\IngredientBaseUnit;
use App\Domain\Inventory\Models\Ingredient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ingredient>
 */
class IngredientFactory extends Factory
{
    protected $model = Ingredient::class;

    /**
     * Bộ đếm tăng dần, không dùng số ngẫu nhiên — CLAUDE.md mục 4.23.
     * Tiền tố TEST cố ý khác mọi mã nguyên liệu cố định trong InventorySeeder.
     */
    private static int $sequence = 0;

    public function definition(): array
    {
        $so = str_pad((string) ++self::$sequence, 6, '0', STR_PAD_LEFT);

        return [
            'code' => 'TEST-NL'.$so,
            'name' => 'Nguyên liệu test '.$so,
            'base_unit' => IngredientBaseUnit::Gram,
            'category' => null,
            'min_qty' => 0,
            'is_active' => true,
        ];
    }

    public function baseUnit(IngredientBaseUnit $unit): static
    {
        return $this->state(fn (array $attributes) => ['base_unit' => $unit]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
