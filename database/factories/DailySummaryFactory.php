<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Reporting\Models\DailySummary;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DailySummary>
 */
class DailySummaryFactory extends Factory
{
    protected $model = DailySummary::class;

    public function definition(): array
    {
        return [
            'date' => now()->toDateString(),
            'revenue_amount' => 0,
            'cash_amount' => 0,
            'transfer_amount' => 0,
            'discount_amount' => 0,
            'table_session_count' => 0,
            'guest_count' => 0,
            'cancelled_item_count' => 0,
            'cancelled_item_amount' => 0,
            'cash_variance_amount' => 0,
        ];
    }
}
