<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Reservations\Enums\ReservationStatus;
use App\Domain\Reservations\Models\Reservation;
use App\Domain\Staffing\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reservation>
 */
class ReservationFactory extends Factory
{
    protected $model = Reservation::class;

    public function definition(): array
    {
        return [
            'customer_id' => null,
            'dining_table_id' => null,
            'table_session_id' => null,
            'guest_count' => fake()->numberBetween(1, 10),
            'reserved_at' => now()->addHours(fake()->numberBetween(1, 48)),
            'status' => ReservationStatus::Pending,
            'note' => null,
            'created_by_user_id' => User::factory(),
            'status_changed_by_user_id' => null,
            'status_changed_at' => null,
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ReservationStatus::Confirmed,
            'status_changed_by_user_id' => User::factory(),
            'status_changed_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ReservationStatus::Cancelled,
            'note' => 'Khách báo huỷ',
            'status_changed_by_user_id' => User::factory(),
            'status_changed_at' => now(),
        ]);
    }
}
