<?php

declare(strict_types=1);

namespace App\Domain\Staffing\Models;

use App\Domain\Billing\Models\Payment;
use App\Domain\Ordering\Models\Order;
use App\Domain\Ordering\Models\TableSession;
use App\Domain\Ordering\Models\TableSessionTable;
use App\Domain\Staffing\Enums\UserRole;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/** @property UserRole $role */
final class User extends Authenticatable implements FilamentUser, HasName
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Chỉ chủ quán/thu ngân vào được trang quản lý thực đơn — phục vụ/bếp
     * không có việc gì phải sửa giá món hay tắt/bật danh mục.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active && in_array($this->role, [UserRole::Owner, UserRole::Cashier], true);
    }

    /** Bảng users không có cột email — Filament hiển thị tên bằng cột name. */
    public function getFilamentName(): string
    {
        return $this->name;
    }

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    protected $fillable = [
        'name',
        'username',
        'phone',
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
