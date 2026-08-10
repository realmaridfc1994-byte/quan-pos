<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Reporting\Models\ProductProfitDaily;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductProfitDaily>
 */
class ProductProfitDailyFactory extends Factory
{
    protected $model = ProductProfitDaily::class;

    public function definition(): array
    {
        $variant = ProductVariant::factory()->create();

        return [
            'date' => now()->toDateString(),
            'product_id' => $variant->product_id,
            'product_variant_id' => $variant->id,
            'quantity_sold' => 1,
            'revenue_amount' => $variant->price,
            'cost_amount' => 0,
        ];
    }
}
