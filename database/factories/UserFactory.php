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
    protected $model = User::class;

    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'username' => fake()->unique()->userName(),
            'phone' => fake()->unique()->numerify('09########'),
            'password' => static::$password ??= Hash::make('password'),
            'pin_code' => null,
            'role' => UserRole::Staff,
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    public function owner(): static
    {
        return $this->state(fn (array $attributes) => ['role' => UserRole::Owner]);
    }

    public function manager(): static
    {
        return $this->state(fn (array $attributes) => ['role' => UserRole::Manager]);
    }

    public function staff(): static
    {
        return $this->state(fn (array $attributes) => ['role' => UserRole::Staff]);
    }

    public function kitchen(): static
    {
        return $this->state(fn (array $attributes) => ['role' => UserRole::Kitchen]);
    }

    public function withPin(string $pin): static
    {
        return $this->state(fn (array $attributes) => ['pin_code' => Hash::make($pin)]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
