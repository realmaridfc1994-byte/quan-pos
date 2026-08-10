<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Models;

use App\Domain\Inventory\Enums\StockMovementRefType;
use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Staffing\Models\Shift;
use App\Domain\Staffing\Models\User;
use App\Exceptions\StockMovementImmutableException;
use Database\Factories\StockMovementFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sổ cái kho — mỗi lần kho thay đổi một dòng, không bao giờ sửa hay xoá.
 * Chỉ App\Domain\Inventory\Actions\RecordStockMovement được ghi vào bảng này.
 *
 * delete()/forceDelete() bị chặn cứng ở tầng Model (Bước 6) — không chỉ dựa
 * vào việc Filament không có nút Xoá, giống tinh thần khoá ghi của
 * StockBalance::choPhepGhi(), nhưng ở đây không có "cửa được phép" nào cả:
 * sổ cái ghi sai thì ghi thêm dòng bù trừ, không bao giờ xoá dòng cũ.
 */
final class StockMovement extends Model
{
    /** @use HasFactory<StockMovementFactory> */
    use HasFactory;

    /** Bảng chỉ có created_at, không có updated_at — sổ cái không bao giờ sửa. */
    const UPDATED_AT = null;

    protected static function newFactory(): StockMovementFactory
    {
        return StockMovementFactory::new();
    }

    protected $fillable = [
        'ingredient_id',
        'type',
        'qty_delta',
        'cost_delta',
        'qty_after',
        'cost_after',
        'has_cost',
        'ref_type',
        'ref_id',
        'reason',
        'approved_by_user_id',
        'created_by_user_id',
        'shift_id',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => StockMovementType::class,
            'ref_type' => StockMovementRefType::class,
            'qty_delta' => 'integer',
            'cost_delta' => 'integer',
            'qty_after' => 'integer',
            'cost_after' => 'integer',
            'has_cost' => 'boolean',
            'occurred_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Ingredient, $this> */
    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    /** @return BelongsTo<User, $this> */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return BelongsTo<Shift, $this> */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function delete(): ?bool
    {
        throw new StockMovementImmutableException('Sổ cái kho không bao giờ được xoá — ghi sai thì ghi thêm dòng bù trừ.');
    }

    public function forceDelete(): bool
    {
        throw new StockMovementImmutableException('Sổ cái kho không bao giờ được xoá — ghi sai thì ghi thêm dòng bù trừ.');
    }

    /**
     * Chặn cả đường xoá HÀNG LOẠT qua query builder (VD:
     * StockMovement::query()->where(...)->delete()) — đường này không đi qua
     * delete()/forceDelete() của từng instance ở trên.
     */
    public function newEloquentBuilder($query): Builder
    {
        return new class($query) extends Builder
        {
            public function delete(): mixed
            {
                throw new StockMovementImmutableException('Sổ cái kho không bao giờ được xoá — ghi sai thì ghi thêm dòng bù trừ.');
            }

            public function forceDelete(): mixed
            {
                throw new StockMovementImmutableException('Sổ cái kho không bao giờ được xoá — ghi sai thì ghi thêm dòng bù trừ.');
            }
        };
    }
}
