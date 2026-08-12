<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Inventory\Enums\StockMovementRefType;
use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Inventory\Models\Ingredient;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Staffing\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<StockMovement>
 */
class StockMovementFactory extends Factory
{
    protected $model = StockMovement::class;

    public function definition(): array
    {
        $qtyDelta = fake()->numberBetween(1, 100);

        return [
            // Giữ Str::uuid() theo ngoại lệ ở CLAUDE.md mục 4.23 — không gian
            // giá trị của uuid lớn tới mức trùng không phải rủi ro thực tế.
            'uuid' => (string) Str::uuid(),
            'ingredient_id' => Ingredient::factory(),
            'type' => StockMovementType::Purchase,
            'qty_delta' => $qtyDelta,
            'cost_delta' => $qtyDelta * 1000,
            'qty_after' => $qtyDelta,
            'cost_after' => $qtyDelta * 1000,
            'has_cost' => true,
            'ref_type' => StockMovementRefType::Manual,
            'ref_id' => null,
            'reason' => null,
            'approved_by_user_id' => null,
            'created_by_user_id' => User::factory(),
            'shift_id' => null,
            'occurred_at' => now(),
        ];
    }
}
