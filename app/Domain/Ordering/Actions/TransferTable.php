<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Actions;

use App\Domain\Ordering\DTO\TransferTableData;
use App\Domain\Ordering\Enums\TableSessionStatus;
use App\Domain\Ordering\Models\DiningTable;
use App\Domain\Ordering\Models\TableSession;
use App\Domain\Ordering\Models\TableSessionTable;
use App\Exceptions\DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Chuyển bàn: nhả bàn cũ + chiếm bàn mới trong CÙNG một giao dịch (B6).
 * Khoá cả hai dòng dining_tables theo id tăng dần, giống hệt nguyên tắc chống
 * kẹt chéo khi mở bàn ở docs/schema.md Phần 6.
 */
final class TransferTable
{
    public function handle(TransferTableData $data): TableSessionTable
    {
        if ($data->fromDiningTableId === $data->toDiningTableId) {
            throw new DomainException('Bàn cũ và bàn mới không được trùng nhau.');
        }

        return DB::transaction(function () use ($data): TableSessionTable {
            $tableSession = TableSession::query()->lockForUpdate()->findOrFail($data->tableSessionId);

            if ($tableSession->status !== TableSessionStatus::Open) {
                throw new DomainException('Lượt khách này không còn mở, không chuyển bàn được nữa.');
            }

            $idBanTheoThuTu = collect([$data->fromDiningTableId, $data->toDiningTableId])->sort()->values();

            $banTheoId = DiningTable::query()
                ->whereIn('id', $idBanTheoThuTu)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $banCu = $banTheoId->get($data->fromDiningTableId);
            $banMoi = $banTheoId->get($data->toDiningTableId);

            if ($banCu === null || $banMoi === null) {
                throw new DomainException('Có bàn không tồn tại trong yêu cầu chuyển bàn.');
            }

            if (! $banMoi->is_active) {
                throw new DomainException("Bàn {$banMoi->code} đã ngưng sử dụng.");
            }

            $phanChiemBanCu = TableSessionTable::query()
                ->where('table_session_id', $tableSession->id)
                ->where('dining_table_id', $banCu->id)
                ->whereNull('detached_at')
                ->first();

            if ($phanChiemBanCu === null) {
                throw new DomainException('Bàn cũ không thuộc lượt khách đang chọn.');
            }

            $banMoiDaCoKhach = TableSessionTable::query()
                ->where('dining_table_id', $banMoi->id)
                ->whereNull('detached_at')
                ->exists();

            if ($banMoiDaCoKhach) {
                throw new DomainException('Bàn này đang có khách.');
            }

            $phanChiemBanCu->update(['detached_at' => now()]);

            return TableSessionTable::query()->create([
                'table_session_id' => $tableSession->id,
                'dining_table_id' => $banMoi->id,
                'is_primary' => $phanChiemBanCu->is_primary,
                'attached_at' => now(),
                'attached_by_user_id' => $data->actorUserId,
            ]);
        });
    }
}
