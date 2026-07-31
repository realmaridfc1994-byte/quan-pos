<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Staffing\Enums\ShiftStatus;
use App\Domain\Staffing\Models\Shift;
use App\Domain\Staffing\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shift>
 */
class ShiftFactory extends Factory
{
    protected $model = Shift::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'CA-'.fake()->unique()->numerify('########-##'),
            'opened_by_user_id' => User::factory(),
            'opened_at' => now(),
            'opening_cash' => 0,
            'status' => ShiftStatus::Open,
        ];
    }

    public function open(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ShiftStatus::Open,
            'closed_at' => null,
            'closed_by_user_id' => null,
            'counted_cash' => null,
            'expected_cash' => null,
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ShiftStatus::Closed,
            'closed_at' => now(),
            'closed_by_user_id' => User::factory(),
            'counted_cash' => 0,
            'expected_cash' => 0,
        ]);
    }
}
