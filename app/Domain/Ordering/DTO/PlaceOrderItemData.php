<?php

declare(strict_types=1);

namespace App\Domain\Ordering\DTO;

final readonly class PlaceOrderItemData
{
    /** @param list<PlaceOrderItemOptionData> $options */
    public function __construct(
        public int $productId,
        public int $productVariantId,
        public int $quantity,
        public ?string $note,
        public array $options,
    ) {}
}
