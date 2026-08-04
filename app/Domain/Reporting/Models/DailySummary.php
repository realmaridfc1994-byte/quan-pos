<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Models;

use Database\Factories\DailySummaryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class DailySummary extends Model
{
    /** @use HasFactory<DailySummaryFactory> */
    use HasFactory;

    protected static function newFactory(): DailySummaryFactory
    {
        return DailySummaryFactory::new();
    }

    protected $fillable = [
        'date',
        'revenue_amount',
        'cash_amount',
        'transfer_amount',
        'discount_amount',
        'table_session_count',
        'guest_count',
        'cancelled_item_count',
        'cancelled_item_amount',
        'cash_variance_amount',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'revenue_amount' => 'integer',
            'cash_amount' => 'integer',
            'transfer_amount' => 'integer',
            'discount_amount' => 'integer',
            'table_session_count' => 'integer',
            'guest_count' => 'integer',
            'cancelled_item_count' => 'integer',
            'cancelled_item_amount' => 'integer',
            'cash_variance_amount' => 'integer',
        ];
    }
}
