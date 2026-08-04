<?php

declare(strict_types=1);

namespace App\Domain\Sync\Models;

use App\Domain\Ordering\Models\TableSession;
use App\Domain\Staffing\Models\User;
use App\Domain\Sync\Enums\ConflictStatus;
use Database\Factories\SyncConflictFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SyncConflict extends Model
{
    /** @use HasFactory<SyncConflictFactory> */
    use HasFactory;

    protected static function newFactory(): SyncConflictFactory
    {
        return SyncConflictFactory::new();
    }

    protected $fillable = [
        'op_uuid',
        'batch_uuid',
        'device_id',
        'op_type',
        'conflict_kind',
        'is_urgent',
        'occurred_at',
        'received_at',
        'payload',
        'server_state',
        'auto_action',
        'message_vi',
        'options',
        'table_session_id',
        'status',
        'resolution',
        'resolution_note',
        'resolved_by_user_id',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'is_urgent' => 'boolean',
            'occurred_at' => 'datetime',
            'received_at' => 'datetime',
            'payload' => 'array',
            'server_state' => 'array',
            'options' => 'array',
            'status' => ConflictStatus::class,
            'resolved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<TableSession, $this> */
    public function tableSession(): BelongsTo
    {
        return $this->belongsTo(TableSession::class);
    }

    /** @return BelongsTo<User, $this> */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }
}
