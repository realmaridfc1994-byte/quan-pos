<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Loyalty\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    /** Bộ đếm tăng dần cho phone (UNIQUE) — CLAUDE.md mục 4.23. */
    private static int $sequence = 0;

    public function definition(): array
    {
        $so = str_pad((string) ++self::$sequence, 6, '0', STR_PAD_LEFT);

        return [
            'phone' => "09{$so}",
            'name' => 'Khách Test '.$so,
            'note' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
