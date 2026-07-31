<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Billing\Enums\PaymentMethod;
use App\Domain\Billing\Enums\PaymentStatus;
use App\Domain\Billing\Models\Payment;
use App\Domain\Ordering\Models\TableSession;
use App\Domain\Staffing\Models\Shift;
use App\Domain\Staffing\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'table_session_id' => TableSession::factory(),
            'shift_id' => Shift::factory(),
            'method' => PaymentMethod::Cash,
            'amount' => fake()->numberBetween(50000, 500000),
            'tendered_amount' => fake()->numberBetween(50000, 500000),
            'change_amount' => 0,
            'reference' => null,
            'status' => PaymentStatus::Completed,
            'received_by_user_id' => User::factory(),
            'paid_at' => now(),
            'voided_by_user_id' => null,
            'voided_at' => null,
            'void_reason' => null,
        ];
    }

    public function cash(): static
    {
        return $this->state(function (array $attributes) {
            $amount = $attributes['amount'];
            $tendered = $attributes['tendered_amount'];

            return [
                'method' => PaymentMethod::Cash,
                'tendered_amount' => $tendered,
                'change_amount' => $tendered - $amount,
            ];
        });
    }

    public function transfer(): static
    {
        return $this->state(fn (array $attributes) => [
            'method' => PaymentMethod::Transfer,
            'tendered_amount' => null,
            'change_amount' => 0,
            'reference' => 'FT'.fake()->numerify('###############'),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentStatus::Completed,
            'voided_at' => null,
            'voided_by_user_id' => null,
            'void_reason' => null,
        ]);
    }

    public function voided(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentStatus::Voided,
            'voided_at' => now(),
            'voided_by_user_id' => User::factory(),
            'void_reason' => 'Thu nhầm',
        ]);
    }
}
