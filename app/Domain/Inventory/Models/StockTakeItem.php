<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Models;

use App\Models\BaseModel;
use Database\Factories\StockTakeItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Một dòng kiểm kê. diff_qty do MySQL tự tính (GENERATED ALWAYS ... STORED)
 * — cố tình KHÔNG có trong $fillable, xem tests/Feature/Database/GeneratedColumnsTest.php
 * cho các cột sinh tự động khác trong dự án.
 */
final class StockTakeItem extends BaseModel
{
    /** @use HasFactory<StockTakeItemFactory> */
    use HasFactory;

    protected static function newFactory(): StockTakeItemFactory
    {
        return StockTakeItemFactory::new();
    }

    protected $fillable = [
        'stock_take_id',
        'ingredient_id',
        'system_qty',
        'counted_qty',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'system_qty' => 'integer',
            'counted_qty' => 'integer',
            'diff_qty' => 'integer',
        ];
    }

    /** @return BelongsTo<StockTake, $this> */
    public function stockTake(): BelongsTo
    {
        return $this->belongsTo(StockTake::class);
    }

    /** @return BelongsTo<Ingredient, $this> */
    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }
}
