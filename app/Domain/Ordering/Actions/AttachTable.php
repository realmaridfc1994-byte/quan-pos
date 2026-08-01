<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Actions;

use App\Domain\Ordering\DTO\AttachTableData;
use App\Domain\Ordering\Enums\TableSessionStatus;
use App\Domain\Ordering\Models\DiningTable;
use App\Domain\Ordering\Models\TableSession;
use App\Domain\Ordering\Models\TableSessionTable;
use App\Exceptions\DomainException;
use Illuminate\Support\Facades\DB;

/** Ghép thêm một bàn vào lượt khách đang mở. */
final class AttachTable
{
    public function handle(AttachTableData $data): TableSessionTable
    {
        return DB::transaction(function () use ($data): TableSessionTable {
            $tableSession = TableSession::query()->lockForUpdate()->findOrFail($data->tableSessionId);

            if ($tableSession->status !== TableSessionStatus::Open) {
                throw new DomainException('Lượt khách này không còn mở, không ghép bàn được nữa.');
            }

            $ban = DiningTable::query()->lockForUpdate()->findOrFail($data->diningTableId);

            if (! $ban->is_active) {
                throw new DomainException("Bàn {$ban->code} đã ngưng sử dụng.");
            }

            $daCoKhach = TableSessionTable::query()
                ->where('dining_table_id', $ban->id)
                ->whereNull('detached_at')
                ->exists();

            if ($daCoKhach) {
                throw new DomainException('Bàn này đang có khách.');
            }

            return TableSessionTable::query()->create([
                'table_session_id' => $tableSession->id,
                'dining_table_id' => $ban->id,
                'is_primary' => false,
                'attached_at' => now(),
                'attached_by_user_id' => $data->attachedByUserId,
            ]);
        });
    }
}
