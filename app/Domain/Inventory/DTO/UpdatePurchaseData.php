<?php

declare(strict_types=1);

namespace App\Domain\Inventory\DTO;

use Illuminate\Foundation\Http\FormRequest;

final readonly class UpdatePurchaseData
{
    /**
     * @param  list<PurchaseLineData>  $lines
     */
    public function __construct(
        public int $purchaseId,
        public int $supplierId,
        public ?string $note,
        public ?string $invoiceNo,
        public array $lines,
    ) {}

    public static function fromRequest(FormRequest $request): self
    {
        return new self(
            purchaseId: (int) $request->route('purchase')->id,
            supplierId: (int) $request->integer('supplier_id'),
            note: $request->input('note'),
            invoiceNo: $request->input('invoice_no'),
            lines: CreatePurchaseData::linesFromArray((array) $request->input('lines', [])),
        );
    }
}
