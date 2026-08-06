<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Inventory\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    /** Bộ đếm tăng dần, không dùng số ngẫu nhiên — CLAUDE.md mục 4.23. */
    private static int $sequence = 0;

    public function definition(): array
    {
        return [
            'name' => 'NCC Test '.str_pad((string) ++self::$sequence, 4, '0', STR_PAD_LEFT),
            'phone' => fake()->optional()->numerify('09########'),
            'address' => fake()->optional()->address(),
            'note' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
