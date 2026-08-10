<?php

declare(strict_types=1);

namespace App\Domain\Inventory\DTO;

final readonly class OpenStockTakeData
{
    public function __construct(
        public ?string $note,
        public int $openedByUserId,
    ) {}
}
