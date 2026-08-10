<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Inventory\Enums\PurchaseStatus;
use App\Domain\Inventory\Models\Purchase;
use App\Domain\Inventory\Models\Supplier;
use App\Domain\Staffing\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Purchase>
 */
class PurchaseFactory extends Factory
{
    protected $model = Purchase::class;

    /** Bộ đếm tăng dần, không dùng số ngẫu nhiên — CLAUDE.md mục 4.23. */
    private static int $sequence = 0;

    public function definition(): array
    {
        return [
            'code' => 'NH-TEST-'.str_pad((string) ++self::$sequence, 6, '0', STR_PAD_LEFT),
            'supplier_id' => Supplier::factory(),
            'status' => PurchaseStatus::Draft,
            'total_cost' => 0,
            'note' => null,
            'invoice_no' => null,
            'received_at' => null,
            'received_by_user_id' => null,
            'cancel_reason' => null,
            'created_by_user_id' => User::factory(),
        ];
    }

    public function received(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PurchaseStatus::Received,
            'received_at' => now(),
            'received_by_user_id' => User::factory(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PurchaseStatus::Cancelled,
            'cancel_reason' => 'Huỷ để kiểm thử',
        ]);
    }
}
