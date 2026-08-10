<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Inventory\Enums\StockTakeStatus;
use App\Domain\Inventory\Models\StockTake;
use App\Domain\Staffing\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockTake>
 */
class StockTakeFactory extends Factory
{
    protected $model = StockTake::class;

    /** Bộ đếm tăng dần, không dùng số ngẫu nhiên — CLAUDE.md mục 4.23. */
    private static int $sequence = 0;

    public function definition(): array
    {
        return [
            'code' => 'KK-TEST-'.str_pad((string) ++self::$sequence, 6, '0', STR_PAD_LEFT),
            'status' => StockTakeStatus::Open,
            'total_diff_cost' => null,
            'note' => null,
            'opened_at' => now(),
            'opened_by_user_id' => User::factory(),
            'closed_at' => null,
            'closed_by_user_id' => null,
        ];
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => StockTakeStatus::Closed,
            'total_diff_cost' => 0,
            'closed_at' => now(),
            'closed_by_user_id' => User::factory(),
        ]);
    }
}
