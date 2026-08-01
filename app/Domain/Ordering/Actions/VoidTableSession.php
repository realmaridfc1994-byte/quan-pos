<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Actions;

use App\Domain\Ordering\DTO\VoidTableSessionData;
use App\Domain\Ordering\Enums\TableSessionStatus;
use App\Domain\Ordering\Models\TableSession;
use App\Domain\Ordering\Models\TableSessionTable;
use App\Exceptions\DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Huỷ toàn bộ một lượt khách (khách bỏ về, mở nhầm bàn...).
 *
 * H1: không xoá dòng nào — chỉ đổi status sang void.
 * H2: bắt buộc ai/lúc nào/vì sao (ck_table_sessions_void).
 * H6: không huỷ được lượt khách đã thu tiền — muốn sửa thì phải huỷ phiếu
 * thu trước (việc của Bước 7, chưa có ở đây).
 * B4: chuyển sang void thì mọi bàn phải được nhả ngay, giống CloseTableSession.
 */
final class VoidTableSession
{
    public function handle(VoidTableSessionData $data): TableSession
    {
        return DB::transaction(function () use ($data): TableSession {
            $tableSession = TableSession::query()->lockForUpdate()->findOrFail($data->tableSessionId);

            if (! in_array($tableSession->status, [TableSessionStatus::Open, TableSessionStatus::Billing], true)) {
                throw new DomainException('Lượt khách này đã đóng hoặc đã huỷ rồi.');
            }

            if ($tableSession->paid_amount > 0) {
                throw new DomainException('Không huỷ được lượt khách đã thu tiền. Phải huỷ phiếu thu trước.');
            }

            $lyDo = trim($data->reason);
            if ($lyDo === '') {
                throw new DomainException('Phải ghi rõ lý do huỷ lượt khách.');
            }

            TableSessionTable::query()
                ->where('table_session_id', $tableSession->id)
                ->whereNull('detached_at')
                ->update(['detached_at' => now()]);

            $tableSession->update([
                'status' => TableSessionStatus::Void,
                'voided_by_user_id' => $data->voidedByUserId,
                'voided_at' => now(),
                'void_reason' => $lyDo,
            ]);

            return $tableSession;
        });
    }
}
