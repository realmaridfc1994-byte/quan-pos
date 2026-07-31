<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Ordering\Enums\TableSessionStatus;
use App\Domain\Ordering\Models\TableSession;
use App\Domain\Ordering\Models\TableSessionTable;
use App\Domain\Staffing\Models\Shift;
use App\Domain\Staffing\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TableSession>
 */
class TableSessionFactory extends Factory
{
    protected $model = TableSession::class;

    public function definition(): array
    {
        return [
            'code' => 'PH-'.fake()->unique()->numerify('##########'),
            'shift_id' => Shift::factory(),
            'guest_count' => fake()->numberBetween(1, 10),
            'status' => TableSessionStatus::Open,
            'opened_by_user_id' => User::factory(),
            'opened_at' => now(),
            'subtotal_amount' => 0,
            'discount_amount' => 0,
            'discount_reason' => null,
            'total_amount' => 0,
            'paid_amount' => 0,
            'bill_no' => null,
            'bill_printed_at' => null,
            'provisional_printed_at' => null,
            'provisional_print_count' => 0,
            'closed_by_user_id' => null,
            'closed_at' => null,
            'voided_by_user_id' => null,
            'voided_at' => null,
            'void_reason' => null,
            'note' => null,
        ];
    }

    public function open(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TableSessionStatus::Open,
            'closed_at' => null,
            'closed_by_user_id' => null,
        ]);
    }

    public function billing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TableSessionStatus::Billing,
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TableSessionStatus::Closed,
            'closed_at' => now(),
            'closed_by_user_id' => User::factory(),
        ]);
    }

    public function withAmount(int $amount): static
    {
        return $this->state(fn (array $attributes) => [
            'subtotal_amount' => $amount,
            'total_amount' => $amount,
        ]);
    }

    public function withTable(): static
    {
        return $this->afterCreating(function (TableSession $session) {
            TableSessionTable::factory()
                ->for($session)
                ->create();
        });
    }
}
