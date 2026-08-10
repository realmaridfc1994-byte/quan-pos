<?php

declare(strict_types=1);

namespace App\Domain\Inventory\DTO;

/** Một dòng nguyên liệu trong phiếu nhập, theo đơn vị người nhập gõ (chưa quy đổi). */
final readonly class PurchaseLineData
{
    public function __construct(
        public int $ingredientId,
        public string $unitName,
        public int $qtyInput,
        /** Giá một đơn vị nhập (đồng/thùng). */
        public int $unitCost,
    ) {}
}
