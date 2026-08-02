<?php

declare(strict_types=1);

namespace App\Domain\Ordering\DTO;

use Illuminate\Foundation\Http\FormRequest;

final readonly class PlaceOrderData
{
    /** @param list<PlaceOrderItemData> $items */
    public function __construct(
        public string $uuid,
        public int $tableSessionId,
        public array $items,
        public ?string $note,
        public int $createdByUserId,
    ) {}

    public static function fromRequest(FormRequest $request): self
    {
        $items = array_map(
            fn (array $item): PlaceOrderItemData => new PlaceOrderItemData(
                uuid: (string) $item['uuid'],
                productId: (int) $item['product_id'],
                productVariantId: (int) $item['product_variant_id'],
                quantity: (int) $item['quantity'],
                note: $item['note'] ?? null,
                options: array_map(
                    fn (array $option): PlaceOrderItemOptionData => new PlaceOrderItemOptionData(
                        uuid: (string) $option['uuid'],
                        optionId: (int) $option['option_id'],
                    ),
                    $item['options'] ?? []
                ),
            ),
            $request->input('items', [])
        );

        return new self(
            uuid: $request->string('uuid')->toString(),
            tableSessionId: (int) $request->route('tableSession')->id,
            items: $items,
            note: $request->input('note'),
            createdByUserId: (int) $request->user()->id,
        );
    }
}
