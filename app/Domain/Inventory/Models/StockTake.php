<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Models;

use App\Domain\Inventory\Enums\StockTakeStatus;
use App\Domain\Staffing\Models\User;
use Closure;
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
    /**
     * Cờ báo "đang trong lúc chốt phiếu kiểm kê".
     *
     * Vì sao cần: từ 12/08, RecordStockMovement từ chối ghi sổ cái khi còn một
     * phiếu kiểm kê đang mở (K18 — kho phải đứng yên trong lúc kiểm kê). Nhưng
     * CloseStockTake ghi các dòng điều chỉnh TRONG LÚC phiếu vẫn còn "open" —
     * nó chỉ đổi trạng thái sang "closed" ở cuối, sau khi đã ghi xong. Không có
     * cửa này thì phiếu kiểm kê tự chặn chính nó.
     *
     * Cùng khuôn với StockBalance::choPhepGhi(): mặc định TẮT, bật trong đúng
     * một closure, và LUÔN tắt lại kể cả khi closure ném lỗi.
     */
    private static bool $dangChotPhieu = false;

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

    /**
     * Bật cờ "đang chốt phiếu", chạy $closure, rồi LUÔN tắt cờ lại (kể cả khi
     * $closure ném lỗi). Chỉ CloseStockTake được gọi hàm này.
     */
    public static function choPhepGhiKhiChotPhieu(Closure $closure): mixed
    {
        self::$dangChotPhieu = true;

        try {
            return $closure();
        } finally {
            self::$dangChotPhieu = false;
        }
    }

    public static function dangChotPhieu(): bool
    {
        return self::$dangChotPhieu;
    }

    /** Còn phiếu kiểm kê nào đang mở không — câu hỏi RecordStockMovement hỏi mỗi lần ghi. */
    public static function dangCoPhieuMo(): bool
    {
        return self::query()->where('status', StockTakeStatus::Open)->exists();
    }
}
