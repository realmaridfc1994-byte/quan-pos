<?php

declare(strict_types=1);

namespace App\Domain\Inventory\DTO;

final readonly class RecordStockTakeCountData
{
    public function __construct(
        public int $stockTakeItemId,
        public int $countedQty,
    ) {}
}
