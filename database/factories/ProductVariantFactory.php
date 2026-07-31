<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'name' => 'Mặc định',
            'price' => fake()->numberBetween(15000, 500000),
            'is_default' => true,
            'is_active' => true,
            'sort_order' => 0,
            'tracks_inventory' => false,
            'stock_unit' => null,
            'stock_factor' => 1,
        ];
    }

    public function withName(string $name): static
    {
        return $this->state(fn (array $attributes) => ['name' => $name]);
    }

    public function withPrice(int $price): static
    {
        return $this->state(fn (array $attributes) => ['price' => $price]);
    }

    public function notDefault(): static
    {
        return $this->state(fn (array $attributes) => ['is_default' => false]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
