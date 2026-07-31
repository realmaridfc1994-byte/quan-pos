<?php

declare(strict_types=1);

namespace App\Domain\Staffing\Models;

use App\Domain\Billing\Models\Payment;
use App\Domain\Ordering\Models\Order;
use App\Domain\Ordering\Models\TableSession;
use App\Domain\Ordering\Models\TableSessionTable;
use App\Domain\Staffing\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

final class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'username',
        'password',
        'pin_code',
        'role',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'pin_code',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'role' => UserRole::class,
            'is_active' => 'boolean',
            'password' => 'hashed',
            'pin_code' => 'hashed',
        ];
    }

    /** @return HasMany<Shift, $this> */
    public function openedShifts(): HasMany
    {
        return $this->hasMany(Shift::class, 'opened_by_user_id');
    }

    /** @return HasMany<Shift, $this> */
    public function closedShifts(): HasMany
    {
        return $this->hasMany(Shift::class, 'closed_by_user_id');
    }

    /** @return HasMany<CashMovement, $this> */
    public function cashMovements(): HasMany
    {
        return $this->hasMany(CashMovement::class, 'created_by_user_id');
    }

    /** @return HasMany<TableSession, $this> */
    public function openedTableSessions(): HasMany
    {
        return $this->hasMany(TableSession::class, 'opened_by_user_id');
    }

    /** @return HasMany<TableSessionTable, $this> */
    public function attachedTableSessionTables(): HasMany
    {
        return $this->hasMany(TableSessionTable::class, 'attached_by_user_id');
    }

    /** @return HasMany<Order, $this> */
    public function createdOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'created_by_user_id');
    }

    /** @return HasMany<Payment, $this> */
    public function receivedPayments(): HasMany
    {
        return $this->hasMany(Payment::class, 'received_by_user_id');
    }
}
