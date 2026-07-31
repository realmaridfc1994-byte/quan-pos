<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Catalog\Models\OptionGroup;
use App\Domain\Catalog\Models\Option;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Option>
 */
class OptionFactory extends Factory
{
    protected $model = Option::class;

    public function definition(): array
    {
        return [
            'option_group_id' => OptionGroup::factory(),
            'name' => fake()->word(),
            'price_delta' => 0,
            'is_default' => false,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    public function withPriceDelta(int $delta): static
    {
        return $this->state(fn (array $attributes) => ['price_delta' => $delta]);
    }

    public function default(): static
    {
        return $this->state(fn (array $attributes) => ['is_default' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
