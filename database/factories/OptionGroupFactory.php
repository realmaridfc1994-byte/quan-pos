<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\OptionGroup;
use App\Domain\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OptionGroup>
 */
class OptionGroupFactory extends Factory
{
    protected $model = OptionGroup::class;

    public function definition(): array
    {
        $isProduct = fake()->boolean();

        return [
            'name' => fake()->word(),
            'product_id' => $isProduct ? Product::factory() : null,
            'category_id' => ! $isProduct ? Category::factory() : null,
            'is_required' => false,
            'min_select' => 0,
            'max_select' => 1,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    public function forProduct(Product|int $product): static
    {
        $productId = is_int($product) ? $product : $product->id;

        return $this->state(fn (array $attributes) => [
            'product_id' => $productId,
            'category_id' => null,
        ]);
    }

    public function forCategory(Category|int $category): static
    {
        $categoryId = is_int($category) ? $category : $category->id;

        return $this->state(fn (array $attributes) => [
            'product_id' => null,
            'category_id' => $categoryId,
        ]);
    }

    public function required(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_required' => true,
            'min_select' => 1,
        ]);
    }

    public function multiSelect(): static
    {
        return $this->state(fn (array $attributes) => [
            'max_select' => fake()->numberBetween(2, 5),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
