<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Models;

use App\Domain\Staffing\Models\User;
use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ghi chú đối soát kho — tờ giấy dán bên cạnh một dòng sổ cái mồ côi, nói
 * "chỗ này đã xem, không phải lỗi". Xem migration cùng tên để biết vì sao cần.
 *
 * Không đụng một chữ nào vào sổ cái. Chỉ làm lệnh đối soát thôi đếm dòng đó
 * vào số lỗi — dòng vẫn được liệt kê đầy đủ trong bản đối soát.
 */
final class StockReconciliationNote extends BaseModel
{
    protected $fillable = [
        'stock_movement_id',
        'note',
        'reason',
        'acknowledged_by_user_id',
        'acknowledged_at',
    ];

    protected function casts(): array
    {
        return [
            'acknowledged_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<StockMovement, $this> */
    public function stockMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class);
    }

    /** @return BelongsTo<User, $this> */
    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by_user_id');
    }
}
