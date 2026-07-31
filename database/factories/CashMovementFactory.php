<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Staffing\Enums\CashDirection;
use App\Domain\Staffing\Models\CashMovement;
use App\Domain\Staffing\Models\Shift;
use App\Domain\Staffing\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashMovement>
 */
class CashMovementFactory extends Factory
{
    protected $model = CashMovement::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shift_id' => Shift::factory(),
            'direction' => CashDirection::In,
            'amount' => fake()->numberBetween(10_000, 500_000),
            'reason' => fake()->sentence(3),
            'created_by_user_id' => User::factory(),
            'occurred_at' => now(),
        ];
    }
}
