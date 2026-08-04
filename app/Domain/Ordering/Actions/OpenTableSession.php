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
use Carbon\Carbon;
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
 *
 * `uuid` do máy POS sinh trước khi gửi (Phase 2 Bước 2) — gửi lại đúng uuid đó
 * trả về đúng lượt khách cũ, không mở trùng khi mạng lag/bấm hai lần. Máy POS
 * offline có thể tự sinh uuid ngay lúc mở bàn; `code` (mã hiển thị cho người
 * đọc) vẫn do server gán, xem sinhMaLuotKhach().
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
            $daCo = TableSession::query()->where('uuid', $data->uuid)->first();
            if ($daCo !== null) {
                return $daCo;
            }

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

            // Mã hiển thị (code) cần ID tự tăng mới sinh đúng, nhưng cột code
            // NOT NULL + UNIQUE nên phải có giá trị ngay lúc tạo — ghi trước
            // bằng chính uuid của bản ghi này làm mã tạm (chắc chắn không
            // trùng ai, thoả UNIQUE ngay lúc chèn; cắt còn 30 ký tự vì cột
            // code là VARCHAR(30), uuid đủ 36 ký tự không vừa), có id thật
            // rồi mới gán mã thật ngay sau đó, cùng một transaction. Xem
            // sinhMaLuotKhach().
            $tableSession = TableSession::query()->create([
                'uuid' => $data->uuid,
                'code' => substr($data->uuid, 0, 30),
                'shift_id' => $shift->id,
                'guest_count' => $data->guestCount,
                'status' => TableSessionStatus::Open,
                'opened_by_user_id' => $data->openedByUserId,
                'opened_at' => now(),
            ]);
            $tableSession->update(['code' => $this->sinhMaLuotKhach($tableSession->id, $tableSession->opened_at)]);

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

    /**
     * Dùng `id` tự tăng của chính lượt khách vừa tạo — không dùng `count() + 1`
     * và không dùng số ngẫu nhiên. Hai người mở bàn cùng một giây, mỗi người
     * vẫn nhận đúng một `id` khác nhau do MySQL tự đảm bảo, không bao giờ ra
     * cùng một mã.
     *
     * Số cuối trong mã là `id` TOÀN CỤC của bản ghi, KHÔNG PHẢI "lượt khách
     * thứ mấy trong ngày" — chấp nhận đánh đổi này: phần ngày tháng đã đủ cho
     * biết thứ tự theo ngày, phần số chỉ cần làm nhiệm vụ phân biệt hai lượt
     * khách mở cùng ngày, không cần đếm lại từ 0001 mỗi ngày.
     *
     * Không cắt bớt nếu `id` vượt quá 4 chữ số — str_pad chỉ đệm thêm, không
     * bao giờ cắt bớt chuỗi dài hơn độ dài yêu cầu.
     *
     * `$openedAt` PHẢI là thời điểm khách vào bàn (`opened_at`), KHÔNG PHẢI
     * `now()` lúc câu lệnh này chạy. Máy POS offline có thể ghi cục bộ lúc
     * 23:50 rồi chỉ đồng bộ lên server lúc 00:10 hôm sau; nếu lấy `now()` ở
     * đây, lượt khách đó bị gắn nhầm sang ngày hôm sau dù doanh thu thuộc
     * đêm hôm trước, làm đối soát sổ sách theo ngày bị lệch.
     */
    private function sinhMaLuotKhach(int $id, Carbon $openedAt): string
    {
        $ngay = $openedAt->format('Ymd');

        return "PH-{$ngay}-".str_pad((string) $id, 4, '0', STR_PAD_LEFT);
    }
}
