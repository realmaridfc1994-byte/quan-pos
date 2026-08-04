<?php

declare(strict_types=1);

namespace App\Domain\Billing\Models;

use App\Domain\Billing\Enums\PromotionAppliesTo;
use App\Domain\Billing\Enums\PromotionType;
use Database\Factories\PromotionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class Promotion extends Model
{
    /** @use HasFactory<PromotionFactory> */
    use HasFactory;

    protected static function newFactory(): PromotionFactory
    {
        return PromotionFactory::new();
    }

    protected $fillable = [
        'code',
        'name',
        'type',
        'value',
        'min_order_amount',
        'max_discount_amount',
        'starts_at',
        'ends_at',
        'time_rules',
        'applies_to',
        'target_id',
        'max_usage',
        'used_count',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => PromotionType::class,
            'value' => 'integer',
            'min_order_amount' => 'integer',
            'max_discount_amount' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'time_rules' => 'array',
            'applies_to' => PromotionAppliesTo::class,
            'target_id' => 'integer',
            'max_usage' => 'integer',
            'used_count' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
