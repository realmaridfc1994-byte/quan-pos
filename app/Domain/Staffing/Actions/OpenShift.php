<?php

declare(strict_types=1);

namespace App\Domain\Staffing\Actions;

use App\Domain\Staffing\DTO\OpenShiftData;
use App\Domain\Staffing\Enums\ShiftStatus;
use App\Domain\Staffing\Models\Shift;
use App\Exceptions\DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Mở một ca làm việc mới, ghi nhận tiền lẻ có sẵn trong két.
 *
 * Chặn C1 ở tầng ứng dụng để báo lỗi rõ ràng ngay; khoá `uq_shifts_only_one_open`
 * trong database là chốt chặn cuối cùng nếu có hai request đến gần như cùng lúc.
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

            return Shift::query()->create([
                'code' => $this->sinhMaCa(),
                'opened_by_user_id' => $data->openedByUserId,
                'opened_at' => now(),
                'opening_cash' => $data->openingCash->amount,
                'status' => ShiftStatus::Open,
            ]);
        });
    }

    private function sinhMaCa(): string
    {
        $homNay = now()->format('Ymd');
        $soThuTu = Shift::query()->whereDate('opened_at', now()->toDateString())->count() + 1;

        return "CA-{$homNay}-".str_pad((string) $soThuTu, 2, '0', STR_PAD_LEFT);
    }
}
