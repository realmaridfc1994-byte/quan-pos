<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Models;

use App\Domain\Inventory\Enums\StockTakeStatus;
use App\Domain\Staffing\Models\User;
use Database\Factories\StockTakeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Phiếu kiểm kê. open_guard là cột sinh tự động (K13) — cố tình KHÔNG có
 * trong $fillable, giống purchase_items.qty_base.
 */
final class StockTake extends Model
{
    /** @use HasFactory<StockTakeFactory> */
    use HasFactory;

    protected static function newFactory(): StockTakeFactory
    {
        return StockTakeFactory::new();
    }

    protected $fillable = [
        'code',
        'status',
        'total_diff_cost',
        'note',
        'opened_at',
        'opened_by_user_id',
        'closed_at',
        'closed_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => StockTakeStatus::class,
            'total_diff_cost' => 'integer',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /** @return HasMany<StockTakeItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(StockTakeItem::class);
    }

    /** @return BelongsTo<User, $this> */
    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }
}
