<?php

declare(strict_types=1);

namespace App\Domain\Printing\DTO;

use App\Support\Money;

/**
 * Dữ liệu chung cho tạm tính và bill cuối.
 */
final readonly class BillData
{
    /** @param array<array{product_name: string, variant_name: string, quantity: int, unit_price: int, line_amount: int}> $items */
    public function __construct(
        public string $tableName,
        public string $sessionCode,
        public array $items,
        public Money $subtotal,
        public Money $discountAmount,
        public ?string $discountReason,
        public Money $total,
        public ?Money $tenderedAmount,  // Tiền khách đưa (NULL nếu chưa thanh toán hoặc chuyển khoản)
        public ?Money $changeAmount,    // Tiền thối (NULL nếu chưa thanh toán)
        public ?string $paymentMethod,  // 'cash' hoặc 'transfer', NULL nếu tạm tính
        public ?string $paymentReference, // Mã chuyển khoản, NULL nếu tiền mặt
    ) {}
}
