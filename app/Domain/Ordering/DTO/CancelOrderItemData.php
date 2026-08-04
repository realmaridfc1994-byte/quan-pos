<?php

declare(strict_types=1);

namespace App\Domain\Ordering\DTO;

use Illuminate\Foundation\Http\FormRequest;

final readonly class CancelOrderItemData
{
    /**
     * @param  ?string  $newItemUuid  Vân tay do máy POS sinh cho DÒNG MÓN MỚI tách ra khi huỷ
     *                                một phần (H4) — bắt buộc khi $quantity < số lượng dòng gốc,
     *                                không dùng khi huỷ toàn bộ (không tạo dòng mới).
     * @param  array<int, string>  $optionUuids  Vân tay do máy POS sinh cho các dòng tuỳ chọn
     *                                           được sao chép sang dòng mới, khoá là id của
     *                                           dòng order_item_options GỐC (client đã thấy id
     *                                           này qua API), giá trị là uuid mới. Chỉ dùng khi
     *                                           huỷ một phần.
     */
    public function __construct(
        public int $orderId,
        public int $orderItemId,
        public int $quantity,
        public string $reason,
        public int $cancelledByUserId,
        public ?int $approverUserId,
        public ?string $approverPin,
        public ?string $newItemUuid,
        public array $optionUuids,
    ) {}

    public static function fromRequest(FormRequest $request): self
    {
        return new self(
            orderId: (int) $request->route('order')->id,
            orderItemId: (int) $request->route('orderItem')->id,
            quantity: $request->integer('quantity'),
            reason: $request->string('reason')->toString(),
            cancelledByUserId: (int) $request->user()->id,
            approverUserId: $request->filled('approver_user_id') ? $request->integer('approver_user_id') : null,
            approverPin: $request->input('approver_pin'),
            newItemUuid: $request->input('new_item_uuid'),
            optionUuids: array_map('strval', $request->input('option_uuids', [])),
        );
    }
}
