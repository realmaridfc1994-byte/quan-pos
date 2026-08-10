<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Models;

use App\Exceptions\StockBalanceWriteNotAllowedException;
use Closure;
use Database\Factories\StockBalanceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tồn hiện tại của một nguyên liệu — đúng một dòng, khoá chính là ingredient_id.
 *
 * qty và total_cost là số có dấu (âm được phép — xem docs/schema.md K.3),
 * khác quy ước BIGINT UNSIGNED chung của dự án cho tiền.
 *
 * KHOÁ GHI Ở TẦNG MODEL — luật một cửa (docs/thiet-ke-gia-von.md mục 1):
 * mọi lệnh ghi (save/delete qua instance, và update/insert/increment/decrement
 * qua query builder — ví dụ $ingredient->stockBalance()->update(...), đường
 * KHÔNG đi qua save()) đều bị chặn trừ khi đang chạy bên trong choPhepGhi().
 * Chỉ App\Domain\Inventory\Actions\RecordStockMovement được gọi choPhepGhi().
 */
final class StockBalance extends Model
{
    /** @use HasFactory<StockBalanceFactory> */
    use HasFactory;

    protected $primaryKey = 'ingredient_id';

    public $incrementing = false;

    public $timestamps = false;

    /** Mặc định TẮT — không ai ghi được cho tới khi chạy trong choPhepGhi(). */
    private static bool $ghiDuocPhep = false;

    protected static function newFactory(): StockBalanceFactory
    {
        return StockBalanceFactory::new();
    }

    protected $fillable = [
        'ingredient_id',
        'qty',
        'total_cost',
        'last_movement_id',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'total_cost' => 'integer',
            'updated_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Ingredient, $this> */
    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    /**
     * Bật cờ cho phép ghi, chạy $closure, rồi LUÔN tắt cờ lại (kể cả khi
     * $closure ném lỗi) — không bao giờ để cờ kẹt ở trạng thái bật.
     */
    public static function choPhepGhi(Closure $closure): mixed
    {
        self::$ghiDuocPhep = true;

        try {
            return $closure();
        } finally {
            self::$ghiDuocPhep = false;
        }
    }

    /**
     * public vì lớp Builder tuỳ biến trong newEloquentBuilder() (một lớp
     * anonymous KHÁC, không kế thừa StockBalance) cũng phải gọi được hàm
     * này — PHP không cho lớp không liên quan gọi method private của lớp
     * khác dù được khai báo lồng bên trong, kể cả cùng file.
     *
     * @throws StockBalanceWriteNotAllowedException
     */
    public static function chanNeuChuaChoPhep(): void
    {
        if (! self::$ghiDuocPhep) {
            throw new StockBalanceWriteNotAllowedException(
                'Chỉ RecordStockMovement được ghi vào stock_balances. Xem CLAUDE.md luật một cửa.'
            );
        }
    }

    /** @param  array<string, mixed>  $options */
    public function save(array $options = []): bool
    {
        self::chanNeuChuaChoPhep();

        return parent::save($options);
    }

    public function delete(): ?bool
    {
        self::chanNeuChuaChoPhep();

        return parent::delete();
    }

    /**
     * Chặn ở tầng QUERY BUILDER — update()/insert()/increment()/decrement()
     * gọi thẳng SQL, không đi qua save() và không bắn sự kiện Eloquent. Đây
     * là đường mà $ingredient->stockBalance()->update([...]) đi qua.
     */
    public function newEloquentBuilder($query): Builder
    {
        return new class($query) extends Builder
        {
            /** @param  array<string, mixed>  $values */
            public function update(array $values): int
            {
                StockBalance::chanNeuChuaChoPhep();

                return parent::update($values);
            }

            /** @param  array<string, mixed>  $values */
            public function insert(array $values): bool
            {
                StockBalance::chanNeuChuaChoPhep();

                return parent::insert($values);
            }

            /** @param  array<string, mixed>  $extra */
            public function increment($column, $amount = 1, array $extra = []): int
            {
                StockBalance::chanNeuChuaChoPhep();

                return parent::increment($column, $amount, $extra);
            }

            /** @param  array<string, mixed>  $extra */
            public function decrement($column, $amount = 1, array $extra = []): int
            {
                StockBalance::chanNeuChuaChoPhep();

                return parent::decrement($column, $amount, $extra);
            }

            public function delete(): mixed
            {
                StockBalance::chanNeuChuaChoPhep();

                return parent::delete();
            }
        };
    }
}
