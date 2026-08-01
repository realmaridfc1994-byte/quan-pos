<?php

declare(strict_types=1);

namespace App\Domain\Billing\Actions;

use App\Domain\Billing\DTO\VoidPaymentData;
use App\Domain\Billing\Enums\PaymentMethod;
use App\Domain\Billing\Enums\PaymentStatus;
use App\Domain\Billing\Models\Payment;
use App\Domain\Ordering\Enums\TableSessionStatus;
use App\Domain\Ordering\Models\TableSession;
use App\Domain\Staffing\Enums\CashDirection;
use App\Domain\Staffing\Enums\ShiftStatus;
use App\Domain\Staffing\Models\CashMovement;
use App\Domain\Staffing\Models\Shift;
use App\Exceptions\DomainException;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Huỷ một phiếu thu — không xoá dòng, chỉ đổi trạng thái + ghi ai/lúc nào/vì sao.
 *
 * T5: paid_amount của lượt khách luôn tính lại bằng cách CỘNG LẠI từ đầu mọi
 * phiếu thu còn hiệu lực — không trừ trực tiếp, tránh lệch nếu có phiếu khác
 * đã bị huỷ trước đó mà chưa ai tính lại.
 *
 * Nếu lượt khách đã đóng (nhờ phiếu thu này) mà huỷ phiếu làm số tiền đã thu
 * không còn đủ (T6), phải mở lại lượt khách để thu ngân thu tiếp. Mở lại bằng
 * trạng thái "billing" ("đã in tạm tính đang chờ trả" — đúng nghĩa ở đây).
 *
 * NGOẠI LỆ CỦA BẤT BIẾN B2 ("lượt khách đang mở phải chiếm ít nhất một bàn"):
 * bàn vật lý đã nhả ra lúc đóng lượt khách (B4) thì KHÔNG được tự ghép lại ở
 * đây, vì giữa lúc đóng và lúc huỷ phiếu thu, bàn đó có thể đã có khách MỚI
 * ngồi vào — tự ghép lại sẽ đâm thẳng vào uq_tst_one_session_per_table (B1).
 * Vì vậy lượt khách "billing" sinh ra ở đây hợp lệ nhưng có thể KHÔNG chiếm
 * bàn nào. B2 chỉ là ràng buộc APP (xem docs/schema.md), không phải CHECK ở
 * DB, nên trường hợp này không vi phạm gì ở tầng cơ sở dữ liệu — chỉ là một
 * ngoại lệ cần biết khi đọc lại B2. Xem docs/viec-ton.md.
 *
 * TIỀN MẶT ĐÃ RA KHỎI KÉT CỦA CA CŨ: nếu phiếu thu tiền mặt thuộc một ca ĐÃ
 * ĐÓNG (C5 — ca đó đã chốt, không sửa lại được), huỷ phiếu này đồng nghĩa
 * quán phải trả lại tiền mặt cho khách NGAY BÂY GIỜ, ở ca ĐANG MỞ — nếu không
 * ghi gì, tối đóng ca hiện tại sẽ thấy két thiếu tiền không rõ lý do. Phải tạo
 * một khoản chi (CashMovement, direction=out) trong ca đang mở. Nếu ca của
 * phiếu thu vẫn đang mở, công thức C4 của CloseShift đã tự loại phiếu voided
 * ra (lọc status=Completed) — không cần tạo gì thêm. Phiếu chuyển khoản không
 * đụng tới két tiền mặt nên không tạo khoản chi dù ca đã đóng.
 *
 * LƯỢT KHÁCH MỞ LẠI PHẢI CHUYỂN SANG CA ĐANG MỞ: RecordPayment tra ca theo
 * table_sessions.shift_id và đòi ca đó đang mở (xem lại chính RecordPayment).
 * Nếu lượt khách mở lại ở đây (status → billing) mà vẫn giữ nguyên shift_id
 * của ca cũ đã đóng, RecordPayment sẽ từ chối vĩnh viễn — quán không thu lại
 * được số tiền vừa hoàn, hoá đơn treo mãi. Tiền thu lại hôm nay phải thuộc về
 * ca hôm nay, nên phải đổi shift_id sang ca đang mở cùng lúc mở lại lượt khách.
 */
final class VoidPayment
{
    public function handle(VoidPaymentData $data): Payment
    {
        return DB::transaction(function () use ($data): Payment {
            $payment = Payment::query()->lockForUpdate()->findOrFail($data->paymentId);

            if ($payment->status === PaymentStatus::Voided) {
                throw new DomainException('Phiếu thu này đã huỷ rồi.');
            }

            $lyDo = trim($data->reason);

            if ($lyDo === '') {
                throw new DomainException('Phải ghi rõ lý do huỷ phiếu thu.');
            }

            $tableSession = TableSession::query()->lockForUpdate()->findOrFail($payment->table_session_id);

            $payment->update([
                'status' => PaymentStatus::Voided,
                'voided_at' => now(),
                'voided_by_user_id' => $data->voidedByUserId,
                'void_reason' => $lyDo,
            ]);

            $caCuaPhieu = Shift::query()->lockForUpdate()->findOrFail($payment->shift_id);
            $caCuaLuotKhach = Shift::query()->lockForUpdate()->findOrFail($tableSession->shift_id);

            $tongDaThu = Payment::query()
                ->where('table_session_id', $tableSession->id)
                ->where('status', PaymentStatus::Completed)
                ->get()
                ->reduce(
                    fn (Money $tong, Payment $p) => $tong->plus(Money::fromInt($p->amount)),
                    Money::zero()
                );

            $seMoLaiLuotKhach = $tableSession->status === TableSessionStatus::Closed
                && $tongDaThu->isLessThan(Money::fromInt($tableSession->total_amount));

            $canHoanTienMat = $caCuaPhieu->status === ShiftStatus::Closed && $payment->method === PaymentMethod::Cash;
            $canChuyenCaLuotKhach = $seMoLaiLuotKhach && $caCuaLuotKhach->status === ShiftStatus::Closed;

            // Lấy ca đang mở MỘT LẦN, dùng chung cho cả khoản hoàn tiền lẫn việc
            // chuyển lượt khách sang ca hôm nay — không truy vấn hai lần.
            $caHienTai = null;

            if ($canHoanTienMat || $canChuyenCaLuotKhach) {
                $caHienTai = Shift::query()->where('status', ShiftStatus::Open)->lockForUpdate()->first();

                if ($caHienTai === null) {
                    // Thông báo phải đúng nguyên nhân: lượt khách CHƯA đóng lại thì
                    // câu "của lượt khách đã đóng" là sai, gây hiểu nhầm cho thu ngân.
                    throw new DomainException($canChuyenCaLuotKhach
                        ? 'Chưa mở ca. Phải mở ca trước khi huỷ phiếu thu của lượt khách đã đóng ở ca cũ.'
                        : 'Chưa mở ca. Phải mở ca trước khi huỷ phiếu thu tiền mặt của ca cũ.');
                }
            }

            if ($canHoanTienMat) {
                CashMovement::query()->create([
                    'shift_id' => $caHienTai->id,
                    'direction' => CashDirection::Out,
                    'amount' => $payment->amount,
                    'reason' => "Hoàn tiền phiếu thu #{$payment->id} của ca {$caCuaPhieu->code} — {$lyDo}",
                    'created_by_user_id' => $data->voidedByUserId,
                    'occurred_at' => now(),
                ]);
            }

            $capNhat = ['paid_amount' => $tongDaThu->amount];

            if ($seMoLaiLuotKhach) {
                $capNhat['status'] = TableSessionStatus::Billing;
                $capNhat['closed_at'] = null;
                $capNhat['closed_by_user_id'] = null;

                if ($canChuyenCaLuotKhach) {
                    $capNhat['shift_id'] = $caHienTai->id;
                }
            }

            $tableSession->update($capNhat);

            return $payment;
        });
    }
}
