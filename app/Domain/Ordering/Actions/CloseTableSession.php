<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Actions;

use App\Domain\Ordering\DTO\CloseTableSessionData;
use App\Domain\Ordering\Enums\TableSessionStatus;
use App\Domain\Ordering\Models\TableSession;
use App\Domain\Ordering\Models\TableSessionTable;
use App\Exceptions\DomainException;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Đóng một lượt khách và nhả toàn bộ bàn của nó ra ngay (B4).
 *
 * T6: chỉ đóng được khi đã thu đủ tiền (paid_amount >= total_amount) — đúng
 * ràng buộc ck_table_sessions_closed. Lượt khách trống (0 món, chưa gọi gì)
 * có paid_amount = total_amount = 0 nên vẫn đóng được bình thường.
 */
final class CloseTableSession
{
    public function handle(CloseTableSessionData $data): TableSession
    {
        return DB::transaction(function () use ($data): TableSession {
            $tableSession = TableSession::query()->lockForUpdate()->findOrFail($data->tableSessionId);

            if (! in_array($tableSession->status, [TableSessionStatus::Open, TableSessionStatus::Billing], true)) {
                throw new DomainException('Lượt khách này đã đóng hoặc đã huỷ rồi.');
            }

            $daThu = Money::fromInt($tableSession->paid_amount);
            $tong = Money::fromInt($tableSession->total_amount);

            if (! $daThu->isAtLeast($tong)) {
                throw new DomainException('Bàn chưa thu đủ tiền, không đóng được.');
            }

            TableSessionTable::query()
                ->where('table_session_id', $tableSession->id)
                ->whereNull('detached_at')
                ->update(['detached_at' => now()]);

            $tableSession->update([
                'status' => TableSessionStatus::Closed,
                'closed_by_user_id' => $data->closedByUserId,
                'closed_at' => now(),
            ]);

            return $tableSession;
        });
    }
}
