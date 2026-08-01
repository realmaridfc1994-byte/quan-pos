<?php

declare(strict_types=1);

namespace App\Domain\Staffing\Actions;

use App\Domain\Billing\Enums\PaymentMethod;
use App\Domain\Billing\Enums\PaymentStatus;
use App\Domain\Billing\Models\Payment;
use App\Domain\Staffing\DTO\CloseShiftData;
use App\Domain\Staffing\Enums\CashDirection;
use App\Domain\Staffing\Enums\ShiftStatus;
use App\Domain\Staffing\Models\CashMovement;
use App\Domain\Staffing\Models\Shift;
use App\Exceptions\DomainException;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Đóng ca: nhận số tiền đếm thực tế trong két, tính tiền mặt lẽ ra phải có
 * (C4) và chốt lại — số đã chốt không đổi nữa (C5, giữ ở CHECK constraint
 * `ck_shifts_closed_fields`).
 */
final class CloseShift
{
    public function handle(CloseShiftData $data): Shift
    {
        return DB::transaction(function () use ($data): Shift {
            $shift = Shift::query()->lockForUpdate()->findOrFail($data->shiftId);

            if ($shift->status !== ShiftStatus::Open) {
                throw new DomainException('Ca này đã đóng rồi, không đóng lại được nữa.');
            }

            // TODO(buoc-8): thay truy vấn tạm bằng Action của Bước 3
            $conLuotKhachChuaTinhTien = DB::table('table_sessions')
                ->where('shift_id', $shift->id)
                ->whereIn('status', ['open', 'billing'])
                ->exists();

            if ($conLuotKhachChuaTinhTien) {
                throw new DomainException('Còn lượt khách đang mở hoặc đang tính tiền. Phải tính tiền hết bàn trước khi đóng ca.');
            }

            $expectedCash = $this->tinhTienMatLeRaPhaiCo($shift);

            $shift->update([
                'counted_cash' => $data->countedCash->amount,
                'expected_cash' => $expectedCash->amount,
                'status' => ShiftStatus::Closed,
                'closed_by_user_id' => $data->closedByUserId,
                'closed_at' => now(),
                'note' => $data->note,
            ]);

            return $shift;
        });
    }

    /**
     * C4: tiền đầu ca + tiền mặt thu được − tiền thối + khoản bỏ vào két − khoản lấy ra.
     *
     * "Tiền mặt thu được" và "tiền thối" đọc từ payments đã hoàn tất — hiện luôn
     * bằng 0 vì Bước 7 (thu tiền) chưa làm, nhưng công thức đã đủ, không cần sửa
     * lại khi Bước 7 có dữ liệu thật.
     */
    private function tinhTienMatLeRaPhaiCo(Shift $shift): Money
    {
        $tienDauCa = Money::fromInt($shift->opening_cash);

        $tienMatThuDuoc = Money::fromInt((int) Payment::query()
            ->where('shift_id', $shift->id)
            ->where('method', PaymentMethod::Cash)
            ->where('status', PaymentStatus::Completed)
            ->sum('amount'));

        $tienThoiLai = Money::fromInt((int) Payment::query()
            ->where('shift_id', $shift->id)
            ->where('method', PaymentMethod::Cash)
            ->where('status', PaymentStatus::Completed)
            ->sum('change_amount'));

        $tienBoVaoKet = Money::fromInt((int) CashMovement::query()
            ->where('shift_id', $shift->id)
            ->where('direction', CashDirection::In)
            ->sum('amount'));

        $tienLayRa = Money::fromInt((int) CashMovement::query()
            ->where('shift_id', $shift->id)
            ->where('direction', CashDirection::Out)
            ->sum('amount'));

        $congVao = $tienDauCa->plus($tienMatThuDuoc)->plus($tienBoVaoKet);
        $truRa = $tienThoiLai->plus($tienLayRa);

        return $congVao->minus($truRa);
    }
}
