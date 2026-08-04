<?php

declare(strict_types=1);

namespace App\Domain\Billing\DTO;

use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;

final readonly class CalculateBillData
{
    /**
     * @param  bool  $skipApprovalThreshold  Bỏ qua hoàn toàn bước hỏi PIN vượt
     *                                       ngưỡng vai trò (TableSessionPolicy::discount()).
     *                                       CHỈ dùng cho App\Domain\Billing\Actions\ApplyPromotion
     *                                       (Phase 2 Bước 6) — khuyến mãi đã được chủ quán duyệt
     *                                       ngay lúc tạo chương trình, không cần duyệt lại từng lần
     *                                       áp dụng. Mọi lời gọi khác (giảm giá tay của thu ngân/
     *                                       chủ quán qua API) PHẢI để mặc định false.
     */
    public function __construct(
        public int $tableSessionId,
        public Money $discountAmount,
        public ?string $discountReason,
        public int $requestedByUserId,
        public ?int $approverUserId,
        public ?string $approverPin,
        public bool $skipApprovalThreshold = false,
    ) {}

    public static function fromRequest(FormRequest $request): self
    {
        return new self(
            tableSessionId: (int) $request->route('tableSession')->id,
            discountAmount: Money::fromInt($request->integer('discount_amount')),
            discountReason: $request->input('discount_reason'),
            requestedByUserId: (int) $request->user()->id,
            approverUserId: $request->filled('approver_user_id') ? $request->integer('approver_user_id') : null,
            approverPin: $request->input('approver_pin'),
        );
    }
}
