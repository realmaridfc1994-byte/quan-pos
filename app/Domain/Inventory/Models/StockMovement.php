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
 * XOÁ và SỬA đều bị chặn cứng ở tầng Model — không chỉ dựa vào việc Filament
 * không có nút Xoá, giống tinh thần khoá ghi của StockBalance::choPhepGhi(),
 * nhưng ở đây không có "cửa được phép" nào cả: sổ cái ghi sai thì ghi thêm
 * dòng bù trừ, không bao giờ đụng vào dòng cũ.
 *
 * Chặn đủ CẢ HAI đường, vì chúng không đi chung một lối:
 *   - Một dòng: delete(), forceDelete(), performUpdate() (chụp mọi lối sửa
 *     một instance — update(), save(), touch()).
 *   - Hàng loạt qua query builder: delete(), forceDelete(), update(),
 *     increment(), decrement(), upsert().
 * Tạo dòng mới vẫn chạy bình thường — đó là việc của RecordStockMovement.
 *
 * (Chặn xoá có từ Bước 6; chặn sửa thêm ở Bước 10 sau review mục 8.2-H —
 * K1 nói "không bao giờ sửa" nhưng chỉ có nửa xoá được khoá.)
 *
 * CÒN MỘT LỚP NỮA BÊN DƯỚI. Chốt ở đây chỉ bắt được code đi qua Eloquent;
 * `DB::table()->update()` hay SQL gõ tay thì đi vòng qua nó. Nên từ 12/08 có
 * thêm trigger `trg_stock_movements_no_update` ở tầng database (migration
 * 2026_08_12_000002) chặn MỌI câu UPDATE, không ai đi vòng được. Hai lớp
 * không thừa nhau: lớp này báo lỗi sớm và rõ nghĩa cho người viết code, lớp
 * kia là chốt cuối cùng cho dữ liệu.
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
        'uuid',
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

    private const LOI_XOA = 'Sổ cái kho không bao giờ được xoá — ghi sai thì ghi thêm dòng bù trừ.';

    private const LOI_SUA = 'Sổ cái kho không bao giờ được sửa — ghi sai thì ghi thêm dòng bù trừ.';

    public function delete(): ?bool
    {
        throw new StockMovementImmutableException(self::LOI_XOA);
    }

    public function forceDelete(): bool
    {
        throw new StockMovementImmutableException(self::LOI_XOA);
    }

    /**
     * Chặn SỬA một dòng đã ghi (K1). Chặn ở performUpdate() chứ không ở
     * update(): mọi đường sửa một instance — update(), save() sau khi đổi
     * thuộc tính, touch() — đều chụm về đây, còn chặn ở update() thì
     * $movement->qty_delta = 999; $movement->save() vẫn lọt.
     *
     * TẠO MỚI vẫn chạy bình thường: dòng mới đi qua performInsert(), không
     * qua hàm này.
     */
    protected function performUpdate(Builder $query): bool
    {
        throw new StockMovementImmutableException(self::LOI_SUA);
    }

    /**
     * Chặn cả đường xoá/sửa HÀNG LOẠT qua query builder (VD:
     * StockMovement::query()->where(...)->update([...])) — đường này không đi
     * qua performUpdate()/delete() của từng instance ở trên.
     */
    public function newEloquentBuilder($query): Builder
    {
        return new class($query) extends Builder
        {
            public function delete(): mixed
            {
                throw new StockMovementImmutableException(StockMovement::loiXoa());
            }

            public function forceDelete(): mixed
            {
                throw new StockMovementImmutableException(StockMovement::loiXoa());
            }

            /** @param  array<string, mixed>  $values */
            public function update(array $values): int
            {
                throw new StockMovementImmutableException(StockMovement::loiSua());
            }

            public function increment($column, $amount = 1, array $extra = []): int
            {
                throw new StockMovementImmutableException(StockMovement::loiSua());
            }

            public function decrement($column, $amount = 1, array $extra = []): int
            {
                throw new StockMovementImmutableException(StockMovement::loiSua());
            }

            /**
             * upsert() là đường sửa trá hình: "chèn, nếu trùng khoá thì cập
             * nhật". Trùng uuid là chuyện có thật (ghi lại vì mạng lag), nên
             * đường này phải đóng luôn.
             *
             * @param  array<int|string, mixed>  $values
             * @param  array<int, string>|string  $uniqueBy
             * @param  array<int, string>|null  $update
             */
            public function upsert(array $values, $uniqueBy, $update = null): int
            {
                throw new StockMovementImmutableException(StockMovement::loiSua());
            }
        };
    }

    public static function loiXoa(): string
    {
        return self::LOI_XOA;
    }

    public static function loiSua(): string
    {
        return self::LOI_SUA;
    }
}
