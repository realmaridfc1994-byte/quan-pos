<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Ordering\Enums\OrderItemStatus;
use App\Domain\Ordering\Models\Order;
use App\Domain\Ordering\Models\OrderItem;
use App\Domain\Staffing\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    public function definition(): array
    {
        $variant = ProductVariant::factory()->create();

        return [
            'uuid' => (string) Str::uuid(),
            'order_id' => Order::factory(),
            'product_id' => $variant->product_id,
            'product_variant_id' => $variant->id,
            'product_name' => $variant->product->name,
            'variant_name' => $variant->name,
            'unit_price' => $variant->price,
            'options_amount' => 0,
            'quantity' => fake()->numberBetween(1, 5),
            'status' => OrderItemStatus::Ordered,
            'served_at' => null,
            'cancelled_by_user_id' => null,
            'cancelled_at' => null,
            'cancel_reason' => null,
            'split_from_item_id' => null,
            'note' => null,
        ];
    }

    public function ordered(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderItemStatus::Ordered,
            'served_at' => null,
        ]);
    }

    public function served(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderItemStatus::Served,
            'served_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderItemStatus::Cancelled,
            'cancelled_by_user_id' => User::factory(),
            'cancelled_at' => now(),
            'cancel_reason' => 'Bấm nhầm',
        ]);
    }

    public function withOptions(int $amount = 10000): static
    {
        return $this->state(fn (array $attributes) => ['options_amount' => $amount]);
    }
}
