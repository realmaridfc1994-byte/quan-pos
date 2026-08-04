<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Sync\Enums\ConflictKind;
use App\Domain\Sync\Enums\ConflictStatus;
use App\Domain\Sync\Models\SyncConflict;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SyncConflict>
 */
class SyncConflictFactory extends Factory
{
    protected $model = SyncConflict::class;

    public function definition(): array
    {
        return [
            'op_uuid' => (string) Str::uuid(),
            'batch_uuid' => (string) Str::uuid(),
            'device_id' => 'pos-test',
            'op_type' => 'open_session',
            'conflict_kind' => ConflictKind::HaiMayMoBan->value,
            'is_urgent' => false,
            'occurred_at' => now(),
            'received_at' => now(),
            'payload' => ['uuid' => (string) Str::uuid()],
            'server_state' => [],
            'auto_action' => null,
            'message_vi' => 'Xung đột diễn tập.',
            'options' => [],
            'table_session_id' => null,
            'status' => ConflictStatus::Pending,
            'resolution' => null,
            'resolution_note' => null,
            'resolved_by_user_id' => null,
            'resolved_at' => null,
        ];
    }

    public function resolved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ConflictStatus::Resolved,
            'resolution' => 'gop',
            'resolution_note' => 'Xử lý diễn tập.',
            'resolved_at' => now(),
        ]);
    }
}
