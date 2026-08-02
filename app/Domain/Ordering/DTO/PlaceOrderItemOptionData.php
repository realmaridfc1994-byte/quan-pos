<?php

declare(strict_types=1);

namespace App\Domain\Ordering\DTO;

final readonly class PlaceOrderItemOptionData
{
    public function __construct(
        public string $uuid,
        public int $optionId,
    ) {}
}
