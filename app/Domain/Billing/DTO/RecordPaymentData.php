<?php

declare(strict_types=1);

namespace App\Domain\Billing\DTO;

use App\Domain\Billing\Enums\PaymentMethod;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;

final readonly class RecordPaymentData
{
    public function __construct(
        /** Vân tay do máy POS sinh trước khi gửi — T9, chống thu trùng nếu máy khởi động lại mất Idempotency-Key cũ. */
        public string $uuid,
        public int $tableSessionId,
        public PaymentMethod $method,
        public Money $amount,
        /** Tiền mặt khách đưa ra. NULL khi chuyển khoản (T8). */
        public ?Money $tenderedAmount,
        public ?string $reference,
        public int $receivedByUserId,
    ) {}

    public static function fromRequest(FormRequest $request): self
    {
        return new self(
            uuid: $request->string('uuid')->toString(),
            tableSessionId: (int) $request->route('tableSession')->id,
            method: PaymentMethod::from($request->string('method')->toString()),
            amount: Money::fromInt($request->integer('amount')),
            tenderedAmount: $request->filled('tendered_amount')
                ? Money::fromInt($request->integer('tendered_amount'))
                : null,
            reference: $request->input('reference'),
            receivedByUserId: (int) $request->user()->id,
        );
    }
}
