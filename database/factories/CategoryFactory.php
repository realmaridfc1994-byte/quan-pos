<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Catalog\Enums\Station;
use App\Domain\Catalog\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    /**
     * Bộ đếm tăng dần, KHÔNG dùng số ngẫu nhiên — xem luật CLAUDE.md mục 22.
     * Tiền tố cố ý khác mọi tên nhóm món cố định trong seeder (Bia & nước,
     * Đồ nhắm, Nướng, Lẩu, Hải sản...) để không bao giờ đụng nhau.
     */
    private static int $sequence = 0;

    public function definition(): array
    {
        return [
            'name' => 'Nhóm test '.++self::$sequence,
            'station' => Station::Kitchen,
            'sort_order' => fake()->numberBetween(0, 100),
            'is_active' => true,
        ];
    }

    public function kitchenCategory(): static
    {
        return $this->state(fn (array $attributes) => ['station' => Station::Kitchen]);
    }

    public function barCategory(): static
    {
        return $this->state(fn (array $attributes) => ['station' => Station::Bar]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
