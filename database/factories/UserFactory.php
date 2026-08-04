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
     * Bộ đếm tăng dần, KHÔNG dùng số ngẫu nhiên — xem luật CLAUDE.md mục 22.
     * Tiền tố cố ý khác mọi username/phone cố định trong seeder và test
     * đăng nhập (owner/cashier/staff/kitchen, 09000000xx, 0912345678,
     * 0999999999...) để không bao giờ đụng nhau.
     */
    private static int $sequence = 0;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'username' => 'user'.str_pad((string) ++self::$sequence, 6, '0', STR_PAD_LEFT),
            'phone' => '07'.str_pad((string) ++self::$sequence, 8, '0', STR_PAD_LEFT),
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

    public function cashier(): static
    {
        return $this->state(fn (array $attributes) => ['role' => UserRole::Cashier]);
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
