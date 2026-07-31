<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Catalog\Enums\Station;
use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Models\Order;
use App\Domain\Ordering\Models\TableSession;
use App\Domain\Staffing\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'submit_batch_uuid' => null,
            'table_session_id' => TableSession::factory(),
            'sequence_no' => 1,
            'station' => fake()->randomElement([Station::Kitchen, Station::Bar]),
            'status' => OrderStatus::Sent,
            'created_by_user_id' => User::factory(),
            'sent_at' => now(),
            'printed_at' => null,
            'print_count' => 0,
            'served_at' => null,
            'cancelled_by_user_id' => null,
            'cancelled_at' => null,
            'cancel_reason' => null,
            'note' => null,
        ];
    }

    public function kitchen(): static
    {
        return $this->state(fn (array $attributes) => ['station' => Station::Kitchen]);
    }

    public function bar(): static
    {
        return $this->state(fn (array $attributes) => ['station' => Station::Bar]);
    }

    public function serving(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::Preparing,
        ]);
    }

    public function served(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::Served,
            'served_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::Cancelled,
            'cancelled_by_user_id' => User::factory(),
            'cancelled_at' => now(),
            'cancel_reason' => 'Bấm nhầm',
        ]);
    }

    public function printed(): static
    {
        return $this->state(fn (array $attributes) => [
            'printed_at' => now(),
            'print_count' => 1,
        ]);
    }
}
