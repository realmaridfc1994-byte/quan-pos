<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Models;

use App\Domain\Staffing\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class TableSessionTable extends Model
{
    protected $fillable = [
        'table_session_id',
        'dining_table_id',
        'is_primary',
        'attached_at',
        'detached_at',
        'attached_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'attached_at' => 'datetime',
            'detached_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<TableSession, $this> */
    public function tableSession(): BelongsTo
    {
        return $this->belongsTo(TableSession::class);
    }

    /** @return BelongsTo<DiningTable, $this> */
    public function diningTable(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class);
    }

    /** @return BelongsTo<User, $this> */
    public function attachedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'attached_by_user_id');
    }
}
