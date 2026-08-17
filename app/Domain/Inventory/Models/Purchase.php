<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Models;

use App\Domain\Inventory\Enums\PurchaseStatus;
use App\Domain\Staffing\Models\User;
use App\Models\BaseModel;
use Database\Factories\PurchaseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Purchase extends BaseModel
{
    /** @use HasFactory<PurchaseFactory> */
    use HasFactory;

    protected static function newFactory(): PurchaseFactory
    {
        return PurchaseFactory::new();
    }

    protected $fillable = [
        'code',
        'supplier_id',
        'status',
        'total_cost',
        'note',
        'invoice_no',
        'received_at',
        'received_by_user_id',
        'cancel_reason',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => PurchaseStatus::class,
            'total_cost' => 'integer',
            'received_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** @return HasMany<PurchaseItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by_user_id');
    }
}
