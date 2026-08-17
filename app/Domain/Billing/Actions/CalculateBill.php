<?php

declare(strict_types=1);

namespace App\Domain\Billing\Actions;

use App\Domain\Billing\DTO\CalculateBillData;
use App\Domain\Ordering\Actions\CloseTableSession;
use App\Domain\Ordering\Actions\RecalculateSessionSubtotal;
use App\Domain\Ordering\DTO\CloseTableSessionData;
use App\Domain\Ordering\Enums\TableSessionStatus;
use App\Domain\Ordering\Models\TableSession;
use App\Domain\Ordering\Policies\TableSessionPolicy;
use App\Domain\Staffing\Actions\VerifyApproverPin;
use App\Domain\Staffing\DTO\PinVerifyData;
use App\Domain\Staffing\Models\User;
use App\Exceptions\DomainException;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Tính lại tạm tính, áp giảm giá và chốt tổng phải thu cho một lượt khách.
 *
 * T2: tạm tính luôn tính lại từ dòng món hiện có (gọi RecalculateSessionSubtotal),
 * không tin số cũ trong cột — tránh lệch nếu có món vừa huỷ mà chưa ai gọi lại.
 * T3: tổng phải thu = tạm tính - giảm giá, không bao giờ âm (Money tự chặn).
 * T4: có giảm giá thì bắt buộc có lý do.
 * Ngưỡng giảm giá theo vai trò dùng TableSessionPolicy::discount() đã có ở Bước 3.
 * Vượt ngưỡng của người gọi thì bắt buộc có người duyệt (chủ quán/thu ngân) nhập
 * đúng PIN, và chính người duyệt đó cũng phải đủ thẩm quyền cho mức % này.
 * Không cho giảm giá xuống dưới số tiền khách ĐÃ TRẢ (paid_amount) — nếu không,
 * quán giữ thừa tiền khách mà hệ thống không biết. Không có CHECK nào ở DB chặn
 * việc này (xem docs/viec-ton.md), nên phải tự kiểm ở đây, trong cùng transaction
 * đã khoá lượt khách — tránh đọc paid_amount cũ trong lúc người khác đang thu tiền.
 * Giảm giá làm tổng phải thu bằng đúng số đã trả thì tự đóng lượt khách luôn
 * (giống RecordPayment khi thu đủ) — không có lý do gì bắt thu ngân bấm thêm
 * một bước đóng bàn riêng khi tiền đã đủ ngay tại lúc giảm giá.
 *
 * `$data->skipApprovalThreshold` (Phase 2 Bước 6): bỏ hẳn bước hỏi PIN vượt
 * ngưỡng — CHỈ App\Domain\Billing\Actions\ApplyPromotion được bật cờ này,
 * vì khuyến mãi đã được chủ quán duyệt ngay lúc tạo chương trình, không cần
 * duyệt lại mỗi lần áp. Luật "tổng không được xuống dưới đã trả" (T3) vẫn
 * luôn được kiểm dù có cờ này hay không.
 *
 * ── HAI LỚP CHẶN PIN (sửa 17/08, CLAUDE.md mục 12) ───────────────────────
 * Trước đây PIN được xác thực BÊN TRONG giao dịch. Hậu quả im lặng: nhập sai
 * PIN thì VerifyApproverPin cộng bộ đếm sai vào bảng `cache` rồi ném lỗi, lỗi
 * thoát ra khỏi giao dịch, Laravel quay lui — và quay lui luôn cả bộ đếm lẫn
 * dòng nhật ký "thử PIN sai". Nghĩa là cơ chế khoá 15 phút sau 5 lần sai
 * KHÔNG BAO GIỜ đếm tới, và không để lại dấu vết ai đang dò PIN.
 *
 * Lớp 1 — NGOÀI giao dịch: có gửi PIN thì xác thực ngay tại đây, không cần
 * biết mức giảm có vượt ngưỡng hay không. Xác thực thừa một lần không hại gì
 * (PIN đúng chỉ xoá bộ đếm sai), còn xác thực thiếu thì bộ đếm lại bốc hơi.
 *
 * Lớp 2 — TRONG giao dịch: % giảm giá CHÍNH THỨC chỉ tính được sau khi đã
 * khoá lượt khách và tính lại tạm tính. Tới đó mà thấy vượt ngưỡng nhưng
 * không có ai đã duyệt thì TỪ CHỐI — không hỏi PIN giữa giao dịch đang mở
 * (đúng cách WriteOffStock::kiemLaiBangSoThat làm với ngưỡng hao hụt).
 */
final class CalculateBill
{
    public function __construct(
        private readonly VerifyApproverPin $verifyApproverPin,
        private readonly CloseTableSession $closeTableSession,
    ) {}

    public function handle(CalculateBillData $data): TableSession
    {
        $nguoiDuyet = $this->duyetPinTruocGiaoDich($data);

        return DB::connection('tenant')->transaction(function () use ($data, $nguoiDuyet): TableSession {
            $tableSession = TableSession::query()->lockForUpdate()->findOrFail($data->tableSessionId);

            if (! in_array($tableSession->status, [TableSessionStatus::Open, TableSessionStatus::Billing], true)) {
                throw new DomainException('Lượt khách này đã đóng hoặc đã huỷ, không tính lại tiền được.');
            }

            app(RecalculateSessionSubtotal::class)->handle($tableSession);
            $tableSession->refresh();

            $tamTinh = Money::fromInt($tableSession->subtotal_amount);

            if (! $tamTinh->isAtLeast($data->discountAmount)) {
                throw new DomainException('Số tiền giảm giá không được lớn hơn tạm tính.');
            }

            $lyDo = $data->discountReason !== null ? trim($data->discountReason) : null;

            if (! $data->discountAmount->isZero() && ($lyDo === null || $lyDo === '')) {
                throw new DomainException('Giảm giá phải ghi rõ lý do.');
            }

            if (! $data->skipApprovalThreshold) {
                $nguoiYeuCau = User::query()->findOrFail($data->requestedByUserId);
                $phanTram = $this->phanTramLamTron($data->discountAmount, $tamTinh);
                $chinhSachGiam = new TableSessionPolicy;

                if (! $chinhSachGiam->discount($nguoiYeuCau, $tableSession, $phanTram)) {
                    // Lớp 2: từ chối, KHÔNG hỏi PIN ở đây — giao dịch đang giữ
                    // khoá trên lượt khách, treo nó chờ người nhập PIN là khoá
                    // luôn bàn đó với mọi thu ngân khác.
                    if ($nguoiDuyet === null) {
                        throw new DomainException('Giảm giá vượt mức cho phép, phải có người duyệt bằng mã PIN.');
                    }

                    if (! $chinhSachGiam->discount($nguoiDuyet, $tableSession, $phanTram)) {
                        throw new DomainException('Người duyệt cũng không đủ thẩm quyền giảm giá ở mức này.');
                    }
                }
            }

            $tongMoi = $tamTinh->minus($data->discountAmount);
            $daThu = Money::fromInt($tableSession->paid_amount);

            if ($tongMoi->isLessThan($daThu)) {
                throw new DomainException(
                    "Không giảm được xuống {$tongMoi->format()} vì khách đã trả {$daThu->format()}. Muốn giảm thêm thì phải huỷ bớt phiếu thu trước."
                );
            }

            $tableSession->update([
                'discount_amount' => $data->discountAmount->amount,
                'discount_reason' => $data->discountAmount->isZero() ? null : $lyDo,
                'total_amount' => $tongMoi->amount,
            ]);

            if ($daThu->isAtLeast($tongMoi)) {
                $this->closeTableSession->handle(new CloseTableSessionData(
                    tableSessionId: $tableSession->id,
                    closedByUserId: $data->requestedByUserId,
                ));
            }

            return $tableSession->refresh();
        });
    }

    /**
     * Lớp 1 — xác thực PIN NGOÀI mọi giao dịch (CLAUDE.md mục 12). Trả về
     * người duyệt, hoặc null khi lần bấm này không gửi PIN nào.
     *
     * Cố ý xác thực kể cả khi mức giảm chưa chắc đã vượt ngưỡng: ngưỡng thật
     * chỉ biết được sau khi khoá lượt khách và tính lại tạm tính, mà lúc đó
     * thì đã quá muộn để hỏi. Gửi PIN sai ở lần bấm không cần duyệt vì vậy
     * cũng bị từ chối — trước đây bị bỏ qua im lặng, và bỏ qua một mã PIN sai
     * là đúng cái việc không nên làm.
     */
    private function duyetPinTruocGiaoDich(CalculateBillData $data): ?User
    {
        if ($data->skipApprovalThreshold) {
            return null;
        }

        if ($data->approverUserId === null || $data->approverPin === null) {
            return null;
        }

        return $this->verifyApproverPin->handle(new PinVerifyData(
            userId: $data->approverUserId,
            pin: $data->approverPin,
            requestedByUserId: $data->requestedByUserId,
        ));
    }

    /**
     * Phần trăm giảm giá, làm tròn LÊN (ceiling) để không lách ngưỡng vai trò
     * bằng cách làm tròn xuống — ví dụ 20.001% phải bị tính là 21%, không phải 20%.
     * Toàn bộ phép tính là số nguyên, không dùng float.
     */
    private function phanTramLamTron(Money $giamGia, Money $tamTinh): int
    {
        if ($tamTinh->isZero()) {
            return 0;
        }

        return intdiv($giamGia->amount * 100 + $tamTinh->amount - 1, $tamTinh->amount);
    }
}
