<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Models;

use App\Domain\Catalog\Enums\Station;
use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Staffing\Models\User;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

final class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory, LogsActivity;

    protected static function newFactory(): OrderFactory
    {
        return OrderFactory::new();
    }

    protected $fillable = [
        'uuid',
        'submit_batch_uuid',
        'table_session_id',
        'sequence_no',
        'station',
        'status',
        'created_by_user_id',
        'sent_at',
        'printed_at',
        'print_count',
        'served_at',
        'cancelled_by_user_id',
        'cancelled_at',
        'cancel_reason',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'station' => Station::class,
            'status' => OrderStatus::class,
            'sequence_no' => 'integer',
            'sent_at' => 'datetime',
            'printed_at' => 'datetime',
            'print_count' => 'integer',
            'served_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<TableSession, $this> */
    public function tableSession(): BelongsTo
    {
        return $this->belongsTo(TableSession::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->fillable)
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
