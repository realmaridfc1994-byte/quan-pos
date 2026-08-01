<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Actions;

use App\Domain\Ordering\DTO\OpenTableSessionData;
use App\Domain\Ordering\Enums\TableSessionStatus;
use App\Domain\Ordering\Models\DiningTable;
use App\Domain\Ordering\Models\TableSession;
use App\Domain\Ordering\Models\TableSessionTable;
use App\Domain\Staffing\Enums\ShiftStatus;
use App\Domain\Staffing\Models\Shift;
use App\Exceptions\DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Mở một lượt khách mới, chiếm một hoặc nhiều bàn (ghép bàn).
 *
 * Ba lớp bảo vệ theo docs/schema.md Phần 6:
 *  1. Khoá `uq_tst_one_session_per_table` ở database — chốt cuối, không thể lách.
 *  2. Giữ chỗ (lockForUpdate) các dòng dining_tables TRƯỚC khi kiểm tra, luôn
 *     theo id tăng dần — hai nhân viên ghép bàn theo thứ tự khác nhau không
 *     bao giờ kẹt chéo chờ nhau.
 *  3. Toàn bộ nằm trong một transaction — thành công hết hoặc không gì cả.
 */
final class OpenTableSession
{
    public function handle(OpenTableSessionData $data): TableSession
    {
        if ($data->diningTableIds === []) {
            throw new DomainException('Phải chọn ít nhất một bàn.');
        }

        if (! in_array($data->primaryDiningTableId, $data->diningTableIds, true)) {
            throw new DomainException('Bàn chính phải nằm trong danh sách bàn được chọn.');
        }

        return DB::transaction(function () use ($data): TableSession {
            // Khoá dòng ca TRƯỚC khi tạo lượt khách (luật CLAUDE.md mục 11: Shift →
            // TableSession) — không khoá thì đọc theo snapshot REPEATABLE READ, có
            // thể thấy ca "open" đúng lúc CloseShift đang khoá và đóng ca đó, tạo ra
            // một lượt khách trỏ vào ca đã đóng mà RecordPayment vĩnh viễn từ chối.
            $shift = Shift::query()->where('status', ShiftStatus::Open)->lockForUpdate()->first();

            if ($shift === null) {
                throw new DomainException('Chưa mở ca. Phải mở ca trước khi mở bàn.');
            }

            // Giữ chỗ theo id tăng dần — chống kẹt chéo khi hai người ghép bàn ngược thứ tự nhau.
            $banDuocChon = DiningTable::query()
                ->whereIn('id', $data->diningTableIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($banDuocChon->count() !== count($data->diningTableIds)) {
                throw new DomainException('Có bàn không tồn tại trong danh sách đã chọn.');
            }

            foreach ($banDuocChon as $ban) {
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
            }

            $tableSession = TableSession::query()->create([
                'code' => $this->sinhMaLuotKhach(),
                'shift_id' => $shift->id,
                'guest_count' => $data->guestCount,
                'status' => TableSessionStatus::Open,
                'opened_by_user_id' => $data->openedByUserId,
                'opened_at' => now(),
            ]);

            foreach ($banDuocChon as $ban) {
                TableSessionTable::query()->create([
                    'table_session_id' => $tableSession->id,
                    'dining_table_id' => $ban->id,
                    'is_primary' => $ban->id === $data->primaryDiningTableId,
                    'attached_at' => now(),
                    'attached_by_user_id' => $data->openedByUserId,
                ]);
            }

            return $tableSession;
        });
    }

    private function sinhMaLuotKhach(): string
    {
        $homNay = now()->format('Ymd');
        $soThuTu = TableSession::query()->whereDate('opened_at', now()->toDateString())->count() + 1;

        return "PH-{$homNay}-".str_pad((string) $soThuTu, 4, '0', STR_PAD_LEFT);
    }
}
