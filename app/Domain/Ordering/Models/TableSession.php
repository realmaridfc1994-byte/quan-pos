<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Models;

use App\Domain\Billing\Models\Payment;
use App\Domain\Billing\Models\Promotion;
use App\Domain\Ordering\Enums\TableSessionStatus;
use App\Domain\Staffing\Models\Shift;
use App\Domain\Staffing\Models\User;
use Database\Factories\TableSessionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class TableSession extends Model
{
    /** @use HasFactory<TableSessionFactory> */
    use HasFactory;

    protected static function newFactory(): TableSessionFactory
    {
        return TableSessionFactory::new();
    }

    protected $fillable = [
        'uuid',
        'code',
        'shift_id',
        'guest_count',
        'status',
        'opened_by_user_id',
        'opened_at',
        'subtotal_amount',
        'discount_amount',
        'discount_reason',
        'promotion_id',
        'total_amount',
        'paid_amount',
        'bill_no',
        'bill_printed_at',
        'provisional_printed_at',
        'provisional_print_count',
        'closed_by_user_id',
        'closed_at',
        'voided_by_user_id',
        'voided_at',
        'void_reason',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'status' => TableSessionStatus::class,
            'guest_count' => 'integer',
            'opened_at' => 'datetime',
            'subtotal_amount' => 'integer',
            'discount_amount' => 'integer',
            'total_amount' => 'integer',
            'paid_amount' => 'integer',
            'bill_printed_at' => 'datetime',
            'provisional_printed_at' => 'datetime',
            'provisional_print_count' => 'integer',
            'closed_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Shift, $this> */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
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

    /** @return BelongsTo<User, $this> */
    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by_user_id');
    }

    /** @return HasMany<TableSessionTable, $this> */
    public function tables(): HasMany
    {
        return $this->hasMany(TableSessionTable::class);
    }

    /** @return HasMany<Order, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** @return BelongsTo<Promotion, $this> */
    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }
}
