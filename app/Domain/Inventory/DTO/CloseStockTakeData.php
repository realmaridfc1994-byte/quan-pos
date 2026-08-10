<?php

declare(strict_types=1);

namespace App\Domain\Inventory\DTO;

final readonly class CloseStockTakeData
{
    public function __construct(
        public int $stockTakeId,
        public int $closedByUserId,
    ) {}
}
