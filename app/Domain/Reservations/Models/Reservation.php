<?php

declare(strict_types=1);

namespace App\Domain\Reservations\Models;

use App\Domain\Billing\Models\Payment;
use App\Domain\Loyalty\Models\Customer;
use App\Domain\Ordering\Models\DiningTable;
use App\Domain\Ordering\Models\TableSession;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Domain\Staffing\Models\User;
use Database\Factories\ReservationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Sổ đặt bàn trước — docs/schema.md PHẦN M.
 *
 * dining_table_id chỉ là gợi ý, KHÔNG giữ chỗ độc quyền (M3) — quán vẫn bán
 * được cho khách vãng lai ngồi đúng bàn đó.
 */
final class Reservation extends Model
{
    /** @use HasFactory<ReservationFactory> */
    use HasFactory;

    protected static function newFactory(): ReservationFactory
    {
        return ReservationFactory::new();
    }

    protected $fillable = [
        'customer_id',
        'dining_table_id',
        'table_session_id',
        'guest_count',
        'reserved_at',
        'status',
        'note',
        'created_by_user_id',
        'status_changed_by_user_id',
        'status_changed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ReservationStatus::class,
            'guest_count' => 'integer',
            'reserved_at' => 'datetime',
            'status_changed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<DiningTable, $this> */
    public function diningTable(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class);
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
    public function statusChangedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'status_changed_by_user_id');
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
