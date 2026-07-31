<?php

declare(strict_types=1);

namespace App\Domain\Staffing\Models;

use App\Domain\Billing\Models\Payment;
use App\Domain\Ordering\Models\TableSession;
use App\Domain\Staffing\Enums\ShiftStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Shift extends Model
{
    protected $fillable = [
        'code',
        'opened_by_user_id',
        'opened_at',
        'opening_cash',
        'closed_by_user_id',
        'closed_at',
        'counted_cash',
        'expected_cash',
        'status',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'status' => ShiftStatus::class,
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'opening_cash' => 'integer',
            'counted_cash' => 'integer',
            'expected_cash' => 'integer',
        ];
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

    /** @return HasMany<CashMovement, $this> */
    public function cashMovements(): HasMany
    {
        return $this->hasMany(CashMovement::class);
    }

    /** @return HasMany<TableSession, $this> */
    public function tableSessions(): HasMany
    {
        return $this->hasMany(TableSession::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
