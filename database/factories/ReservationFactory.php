<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Reservations\Enums\DepositStatus;
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
            'status_reason' => null,
            'created_by_user_id' => User::factory(),
            'status_changed_by_user_id' => null,
            'status_changed_at' => null,
            'deposit_status' => DepositStatus::Unhandled,
            'deposit_handled_by_user_id' => null,
            'deposit_handled_at' => null,
            'deposit_handled_note' => null,
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
            'status_reason' => 'Khách báo huỷ',
            'status_changed_by_user_id' => User::factory(),
            'status_changed_at' => now(),
        ]);
    }

    /** Khách không tới — ck_reservations_status_reason đòi đủ ai/lúc nào/vì sao. */
    public function noShow(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ReservationStatus::NoShow,
            'status_reason' => 'Quá 30 phút không thấy khách, gọi không nghe máy',
            'status_changed_by_user_id' => User::factory(),
            'status_changed_at' => now(),
        ]);
    }
}
