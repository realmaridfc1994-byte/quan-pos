<?php

declare(strict_types=1);

namespace App\Domain\Inventory\DTO;

use Illuminate\Foundation\Http\FormRequest;

final readonly class CreatePurchaseData
{
    /**
     * @param  list<PurchaseLineData>  $lines
     */
    public function __construct(
        public int $supplierId,
        public ?string $note,
        public ?string $invoiceNo,
        public array $lines,
        public int $createdByUserId,
    ) {}

    public static function fromRequest(FormRequest $request): self
    {
        return new self(
            supplierId: (int) $request->integer('supplier_id'),
            note: $request->input('note'),
            invoiceNo: $request->input('invoice_no'),
            lines: self::linesFromArray((array) $request->input('lines', [])),
            createdByUserId: (int) $request->user()->id,
        );
    }

    /**
     * @param  array<int, array{ingredient_id: int, unit_name: string, qty_input: int, unit_cost: int}>  $lines
     * @return list<PurchaseLineData>
     */
    public static function linesFromArray(array $lines): array
    {
        return array_values(array_map(
            fn (array $line): PurchaseLineData => new PurchaseLineData(
                ingredientId: (int) $line['ingredient_id'],
                unitName: (string) $line['unit_name'],
                qtyInput: (int) $line['qty_input'],
                unitCost: (int) $line['unit_cost'],
            ),
            $lines,
        ));
    }
}
