<?php

declare(strict_types=1);

namespace App\Domain\Staffing\Actions;

use App\Domain\Staffing\DTO\OpenShiftData;
use App\Domain\Staffing\Enums\ShiftStatus;
use App\Domain\Staffing\Models\Shift;
use App\Exceptions\DomainException;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Activitylog\Facades\Activity;

/**
 * Mở một ca làm việc mới, ghi nhận tiền lẻ có sẵn trong két.
 *
 * Chặn C1 ở tầng ứng dụng để báo lỗi rõ ràng ngay; khoá `uq_shifts_only_one_open`
 * trong database là chốt chặn cuối cùng nếu có hai request đến gần như cùng lúc.
 *
 * Lưu ý: khi KHÔNG có ca nào đang mở, `lockForUpdate()` ở trên không khoá được
 * gì (không có dòng nào để khoá) — hai request đến cùng lúc lúc đó đều đi tiếp
 * xuống dưới. Vì vậy `code` không được sinh bằng count()+1 (xem sinhMaCa()).
 */
final class OpenShift
{
    public function handle(OpenShiftData $data): Shift
    {
        return DB::transaction(function () use ($data): Shift {
            $dangCoCaMo = Shift::query()->where('status', ShiftStatus::Open)->lockForUpdate()->first();

            if ($dangCoCaMo !== null) {
                throw new DomainException("Đang có ca mở (mã {$dangCoCaMo->code}). Phải đóng ca hiện tại trước khi mở ca mới.");
            }

            // Mã hiển thị (code) cần ID tự tăng mới sinh đúng, nhưng cột code
            // NOT NULL + UNIQUE nên phải có giá trị ngay lúc tạo — ghi trước
            // bằng một uuid sinh riêng làm mã tạm (chắc chắn không trùng ai,
            // thoả UNIQUE ngay lúc chèn; `shifts` không có cột uuid riêng như
            // table_sessions nên phải sinh tạm ở đây, không dùng số ngẫu
            // nhiên ngắn vì đó chính là kiểu lỗi đang sửa). Cắt còn 30 ký tự
            // vì cột code là VARCHAR(30). Có id thật rồi mới gán mã thật ngay
            // sau đó, cùng một transaction. Xem sinhMaCa().
            $ca = Shift::query()->create([
                'code' => substr((string) Str::uuid(), 0, 30),
                'opened_by_user_id' => $data->openedByUserId,
                'opened_at' => now(),
                'opening_cash' => $data->openingCash->amount,
                'status' => ShiftStatus::Open,
            ]);
            // Chỉ bỏ qua nhật ký cho việc gán MÃ HIỂN THỊ (chi tiết kỹ thuật —
            // ghi tạm rồi sửa lại vì cần id thật trước), không phải một hành
            // vi nghiệp vụ đáng ghi sổ. Nhật ký "tạo ca" ở create() phía trên
            // vẫn ghi bình thường; đây chỉ tắt đúng dòng update() này.
            Activity::withoutLogs(function () use ($ca) {
                $ca->update(['code' => $this->sinhMaCa($ca->id, $ca->opened_at)]);
            });

            return $ca;
        });
    }

    /**
     * Dùng `id` tự tăng của chính ca vừa tạo — không dùng `count() + 1` và
     * không dùng số ngẫu nhiên, vì `uq_shifts_only_one_open` chỉ chặn được
     * HAI CA MỞ CÙNG LÚC, không chặn được việc hai request cùng đọc count()
     * rồi cùng cộng 1 khi bảng đang trống ca mở. `id` do MySQL tự tăng nên
     * không bao giờ trùng.
     *
     * Số cuối trong mã là `id` TOÀN CỤC của ca, KHÔNG PHẢI "ca thứ mấy trong
     * ngày" — chấp nhận đánh đổi này, giống hệt lý do ở
     * OpenTableSession::sinhMaLuotKhach(). Không cắt bớt nếu `id` vượt quá 2
     * chữ số — str_pad chỉ đệm thêm, không bao giờ cắt bớt.
     *
     * `$openedAt` PHẢI là thời điểm mở ca (`opened_at`), KHÔNG PHẢI `now()`
     * lúc câu lệnh này chạy — lý do giống hệt `OpenTableSession::sinhMaLuotKhach()`:
     * đồng bộ offline trễ qua nửa đêm sẽ gắn nhầm ca sang ngày hôm sau nếu
     * dùng `now()`.
     */
    private function sinhMaCa(int $id, Carbon $openedAt): string
    {
        $ngay = $openedAt->format('Ymd');

        return "CA-{$ngay}-".str_pad((string) $id, 2, '0', STR_PAD_LEFT);
    }
}
