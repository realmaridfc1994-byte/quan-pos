<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Catalog\Models\Option;
use App\Domain\Ordering\Models\OrderItem;
use App\Domain\Ordering\Models\OrderItemOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItemOption>
 */
class OrderItemOptionFactory extends Factory
{
    protected $model = OrderItemOption::class;

    public function definition(): array
    {
        $option = Option::factory()->create();

        return [
            'order_item_id' => OrderItem::factory(),
            'option_id' => $option->id,
            'option_group_name' => $option->optionGroup->name,
            'option_name' => $option->name,
            'price_delta' => $option->price_delta,
        ];
    }
}
