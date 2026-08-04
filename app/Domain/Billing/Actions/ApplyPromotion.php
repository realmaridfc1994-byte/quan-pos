<?php

declare(strict_types=1);

namespace App\Domain\Billing\Actions;

use App\Domain\Billing\DTO\ApplyPromotionData;
use App\Domain\Billing\DTO\CalculateBillData;
use App\Domain\Billing\Enums\PromotionAppliesTo;
use App\Domain\Billing\Enums\PromotionType;
use App\Domain\Billing\Models\Promotion;
use App\Domain\Ordering\Actions\RecalculateSessionSubtotal;
use App\Domain\Ordering\Enums\OrderItemStatus;
use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Enums\TableSessionStatus;
use App\Domain\Ordering\Models\OrderItem;
use App\Domain\Ordering\Models\TableSession;
use App\Exceptions\DomainException;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Áp một khuyến mãi vào một lượt khách — Phase 2 Bước 6.
 *
 * KHÔNG tự tính lại tổng/ghi discount_amount trực tiếp: chỉ tính SỐ TIỀN GIẢM
 * từ luật của khuyến mãi rồi giao lại cho CalculateBill (đã có từ Phase 1) ghi
 * vào discount_amount/discount_reason — dùng lại nguyên luồng đó (T2 tính lại
 * tạm tính từ dòng món thật, T3 tổng không xuống dưới đã trả, tự đóng lượt
 * khách nếu giảm giá làm tổng bằng đúng số đã thu).
 *
 * Một lượt khách CHỈ áp được một khuyến mãi — chốt bằng cột
 * `table_sessions.promotion_id` (Bước 6), không suy luận qua discount_reason.
 *
 * Khuyến mãi không cần PIN duyệt dù giảm vượt ngưỡng 20% của vai trò — chủ
 * quán đã duyệt chương trình ngay lúc tạo, không cần duyệt lại từng lần áp
 * (xem CalculateBillData::$skipApprovalThreshold).
 */
final class ApplyPromotion
{
    public function __construct(
        private readonly CalculateBill $calculateBill,
    ) {}

    public function handle(ApplyPromotionData $data): TableSession
    {
        return DB::transaction(function () use ($data): TableSession {
            $khuyenMai = Promotion::query()
                ->where('code', $data->promotionCode)
                ->lockForUpdate()
                ->first();

            if ($khuyenMai === null) {
                throw new DomainException("Không tìm thấy khuyến mãi mã \"{$data->promotionCode}\".");
            }

            $this->kiemTraConHieuLuc($khuyenMai);

            $tableSession = TableSession::query()->lockForUpdate()->findOrFail($data->tableSessionId);

            if (! in_array($tableSession->status, [TableSessionStatus::Open, TableSessionStatus::Billing], true)) {
                throw new DomainException('Lượt khách này đã đóng hoặc đã huỷ, không áp khuyến mãi được.');
            }

            if ($tableSession->promotion_id !== null) {
                throw new DomainException('Lượt khách này đã áp một khuyến mãi rồi, không được áp thêm khuyến mãi thứ hai.');
            }

            app(RecalculateSessionSubtotal::class)->handle($tableSession);
            $tableSession->refresh();

            $tamTinhApDung = $this->tamTinhDuocApDung($tableSession, $khuyenMai);

            if ($khuyenMai->min_order_amount !== null && $tableSession->subtotal_amount < $khuyenMai->min_order_amount) {
                throw new DomainException("Đơn chưa đạt tối thiểu {$this->tien($khuyenMai->min_order_amount)} để áp khuyến mãi \"{$khuyenMai->name}\".");
            }

            $soTienGiam = $this->tinhSoTienGiam($khuyenMai, $tamTinhApDung);

            // Ghi trước khi gọi CalculateBill: nếu CalculateBill từ chối (VD T3 —
            // tổng xuống dưới đã trả), toàn bộ nằm chung một transaction nên
            // used_count/promotion_id cũng rollback theo, không lệch sổ.
            $khuyenMai->update(['used_count' => $khuyenMai->used_count + 1]);
            $tableSession->update(['promotion_id' => $khuyenMai->id]);

            $this->calculateBill->handle(new CalculateBillData(
                tableSessionId: $tableSession->id,
                discountAmount: $soTienGiam,
                discountReason: "Khuyến mãi {$khuyenMai->code} — {$khuyenMai->name}",
                requestedByUserId: $data->requestedByUserId,
                approverUserId: null,
                approverPin: null,
                skipApprovalThreshold: true,
            ));

            return $tableSession->refresh();
        });
    }

    private function kiemTraConHieuLuc(Promotion $khuyenMai): void
    {
        if (! $khuyenMai->is_active) {
            throw new DomainException("Khuyến mãi \"{$khuyenMai->name}\" đã ngưng, không áp dụng được.");
        }

        $bayGio = now();

        if ($khuyenMai->starts_at !== null && $bayGio->lt($khuyenMai->starts_at)) {
            throw new DomainException("Khuyến mãi \"{$khuyenMai->name}\" chưa bắt đầu.");
        }

        if ($khuyenMai->ends_at !== null && $bayGio->gt($khuyenMai->ends_at)) {
            throw new DomainException("Khuyến mãi \"{$khuyenMai->name}\" đã hết hạn.");
        }

        $this->kiemTraKhungGio($khuyenMai, $bayGio);

        if ($khuyenMai->max_usage !== null && $khuyenMai->used_count >= $khuyenMai->max_usage) {
            throw new DomainException("Khuyến mãi \"{$khuyenMai->name}\" đã dùng hết số lượt cho phép ({$khuyenMai->max_usage} lượt).");
        }
    }

    /** `time_rules` = {"days": [0..6], "from": "HH:mm", "to": "HH:mm"} — NULL = không giới hạn giờ/ngày. */
    private function kiemTraKhungGio(Promotion $khuyenMai, CarbonInterface $bayGio): void
    {
        $luat = $khuyenMai->time_rules;
        if ($luat === null) {
            return;
        }

        $cacNgay = $luat['days'] ?? null;
        if ($cacNgay !== null && ! in_array($bayGio->dayOfWeek, $cacNgay, true)) {
            throw new DomainException("Khuyến mãi \"{$khuyenMai->name}\" không áp dụng vào hôm nay.");
        }

        $tu = $luat['from'] ?? null;
        $den = $luat['to'] ?? null;
        if ($tu !== null && $den !== null) {
            $gioHienTai = $bayGio->format('H:i');
            if ($gioHienTai < $tu || $gioHienTai >= $den) {
                throw new DomainException("Khuyến mãi \"{$khuyenMai->name}\" chỉ áp dụng từ {$tu} đến {$den}.");
            }
        }
    }

    /** Tạm tính của PHẦN được khuyến mãi áp vào — toàn bộ hoá đơn, hoặc chỉ một nhóm món/một món. */
    private function tamTinhDuocApDung(TableSession $tableSession, Promotion $khuyenMai): int
    {
        if ($khuyenMai->applies_to === PromotionAppliesTo::All) {
            return $tableSession->subtotal_amount;
        }

        $truyVan = OrderItem::query()
            ->whereHas('order', fn ($q) => $q
                ->where('table_session_id', $tableSession->id)
                ->where('status', '!=', OrderStatus::Cancelled))
            ->where('status', '!=', OrderItemStatus::Cancelled);

        if ($khuyenMai->applies_to === PromotionAppliesTo::Product) {
            $truyVan->where('product_id', $khuyenMai->target_id);
        } else {
            $truyVan->whereHas('product', fn ($q) => $q->where('category_id', $khuyenMai->target_id));
        }

        return (int) $truyVan->sum('line_amount');
    }

    private function tinhSoTienGiam(Promotion $khuyenMai, int $tamTinhApDung): Money
    {
        $soTienGoc = match ($khuyenMai->type) {
            PromotionType::Amount => min($khuyenMai->value, $tamTinhApDung),
            PromotionType::Percent, PromotionType::HappyHour => intdiv($tamTinhApDung * $khuyenMai->value, 100),
        };

        if ($khuyenMai->max_discount_amount !== null && $soTienGoc > $khuyenMai->max_discount_amount) {
            $soTienGoc = $khuyenMai->max_discount_amount;
        }

        return Money::fromInt($soTienGoc);
    }

    private function tien(int $soTien): string
    {
        return Money::fromInt($soTien)->format();
    }
}
