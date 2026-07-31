<?php

declare(strict_types=1);

namespace App\Domain\Staffing\Models;

use App\Domain\Staffing\Enums\CashDirection;
use Database\Factories\CashMovementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CashMovement extends Model
{
    /** @use HasFactory<CashMovementFactory> */
    use HasFactory;

    protected static function newFactory(): CashMovementFactory
    {
        return CashMovementFactory::new();
    }

    protected $fillable = [
        'shift_id',
        'direction',
        'amount',
        'reason',
        'created_by_user_id',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'direction' => CashDirection::class,
            'amount' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Shift, $this> */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
