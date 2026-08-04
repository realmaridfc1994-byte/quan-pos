<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Reporting\Models\ProductSaleDaily;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductSaleDaily>
 */
class ProductSaleDailyFactory extends Factory
{
    protected $model = ProductSaleDaily::class;

    public function definition(): array
    {
        $variant = ProductVariant::factory()->create();

        return [
            'date' => now()->toDateString(),
            'product_id' => $variant->product_id,
            'product_variant_id' => $variant->id,
            'quantity_sold' => 1,
            'revenue_amount' => $variant->price,
        ];
    }
}
