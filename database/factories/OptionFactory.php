<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Catalog\Models\Option;
use App\Domain\Catalog\Models\OptionGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Option>
 */
class OptionFactory extends Factory
{
    protected $model = Option::class;

    /**
     * Bộ đếm tăng dần, KHÔNG dùng số ngẫu nhiên — xem luật CLAUDE.md mục 22.
     * Chống đụng uq_options_group_name (option_group_id, name) khi nhiều
     * tuỳ chọn được tạo trong cùng một nhóm mà không tự đặt tên riêng.
     */
    private static int $sequence = 0;

    public function definition(): array
    {
        return [
            'option_group_id' => OptionGroup::factory(),
            'name' => 'Tuỳ chọn '.++self::$sequence,
            'price_delta' => 0,
            'is_default' => false,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    public function withPriceDelta(int $delta): static
    {
        return $this->state(fn (array $attributes) => ['price_delta' => $delta]);
    }

    public function default(): static
    {
        return $this->state(fn (array $attributes) => ['is_default' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
