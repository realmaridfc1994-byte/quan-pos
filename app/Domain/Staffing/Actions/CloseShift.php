<?php

declare(strict_types=1);

namespace App\Domain\Staffing\Actions;

use App\Domain\Billing\Enums\PaymentMethod;
use App\Domain\Billing\Enums\PaymentStatus;
use App\Domain\Billing\Models\Payment;
use App\Domain\Ordering\Enums\TableSessionStatus;
use App\Domain\Ordering\Models\TableSession;
use App\Domain\Ordering\Models\TableSessionTable;
use App\Domain\Staffing\DTO\CloseShiftData;
use App\Domain\Staffing\Enums\CashDirection;
use App\Domain\Staffing\Enums\ShiftStatus;
use App\Domain\Staffing\Models\CashMovement;
use App\Domain\Staffing\Models\Shift;
use App\Exceptions\DomainException;
use App\Support\CashVariance;
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

            $luotChuaTinhTien = TableSession::query()
                ->where('shift_id', $shift->id)
                ->whereIn('status', [TableSessionStatus::Open, TableSessionStatus::Billing])
                ->with('tables.diningTable')
                ->get();

            if ($luotChuaTinhTien->isNotEmpty()) {
                // C3: nêu rõ tên bàn để thu ngân biết ngay bàn nào còn chưa tính
                // tiền — lượt khách không chiếm bàn nào (xem VoidPayment, ngoại lệ
                // B2) thì nêu mã lượt khách thay cho tên bàn.
                $noiConVuong = $luotChuaTinhTien
                    ->map(function (TableSession $luot): string {
                        $tenBan = $luot->tables
                            ->whereNull('detached_at')
                            ->map(fn (TableSessionTable $bi) => $bi->diningTable->code)
                            ->implode(', ');

                        return $tenBan !== '' ? $tenBan : "lượt khách {$luot->code} (chưa gán bàn)";
                    })
                    ->unique()
                    ->implode(', ');

                throw new DomainException("Còn bàn {$noiConVuong} đang mở hoặc đang tính tiền. Phải tính tiền hết bàn trước khi đóng ca.");
            }

            ['expectedCash' => $expectedCash, 'ghiChuChiVuotThu' => $ghiChuChiVuotThu] = $this->tinhTienMatLeRaPhaiCo($shift);

            $note = $data->note;
            if ($ghiChuChiVuotThu !== null) {
                $note = $note !== null && trim($note) !== '' ? trim($note)." — {$ghiChuChiVuotThu}" : $ghiChuChiVuotThu;
            }

            $shift->update([
                'counted_cash' => $data->countedCash->amount,
                'expected_cash' => $expectedCash->amount,
                'status' => ShiftStatus::Closed,
                'closed_by_user_id' => $data->closedByUserId,
                'closed_at' => now(),
                'note' => $note,
            ]);

            return $shift;
        });
    }

    /**
     * C4: tiền đầu ca + tiền mặt thu được − tiền thối + khoản bỏ vào két − khoản lấy ra.
     *
     * VoidPayment (Bước 7) có thể tạo khoản chi hoàn tiền mặt lớn hơn tổng tiền
     * mặt đã thu trong chính ca này (ví dụ: hoàn 800.000 của ca cũ, ca này mới
     * bán được 300.000) — khi đó công thức cộng trừ bình thường ra số ÂM, nhưng
     * `expected_cash` là BIGINT UNSIGNED và Money không cho phép số âm. Số âm ở
     * đây hợp lệ về nghiệp vụ (quán chi nhiều hơn thu, tiền bù từ ví chủ quán),
     * nên không ném lỗi: chốt `expected_cash = 0` và ghi rõ phần vượt vào `note`
     * để chủ quán biết cần bù bao nhiêu từ ngoài két.
     *
     * @return array{expectedCash: Money, ghiChuChiVuotThu: ?string}
     */
    private function tinhTienMatLeRaPhaiCo(Shift $shift): array
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

        $chenhLech = CashVariance::between($congVao, $truRa);

        if ($chenhLech->isShortage()) {
            return [
                'expectedCash' => Money::zero(),
                'ghiChuChiVuotThu' => "Chi ra vượt tiền thu {$chenhLech->absolute()->format()} — tiền bù từ ngoài két.",
            ];
        }

        return [
            'expectedCash' => $congVao->minus($truRa),
            'ghiChuChiVuotThu' => null,
        ];
    }
}
