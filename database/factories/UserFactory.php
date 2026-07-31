<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Staffing\Enums\UserRole;
use App\Domain\Staffing\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'username' => fake()->unique()->userName(),
            'password' => static::$password ??= Hash::make('password'),
            'pin_code' => null,
            'role' => UserRole::Waiter,
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    public function owner(): static
    {
        return $this->state(fn (array $attributes) => ['role' => UserRole::Owner]);
    }

    public function cashier(): static
    {
        return $this->state(fn (array $attributes) => ['role' => UserRole::Cashier]);
    }

    public function waiter(): static
    {
        return $this->state(fn (array $attributes) => ['role' => UserRole::Waiter]);
    }

    public function kitchen(): static
    {
        return $this->state(fn (array $attributes) => ['role' => UserRole::Kitchen]);
    }
}
