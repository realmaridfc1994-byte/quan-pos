<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Catalog\Enums\Station;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'code' => fake()->unique()->bothify('????##'),
            'name' => fake()->word(),
            'description' => fake()->optional()->sentence(),
            'station_override' => null,
            'is_active' => true,
            'sort_order' => fake()->numberBetween(0, 100),
            'image_path' => null,
        ];
    }

    public function withStation(Station $station): static
    {
        return $this->state(fn (array $attributes) => ['station_override' => $station]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
