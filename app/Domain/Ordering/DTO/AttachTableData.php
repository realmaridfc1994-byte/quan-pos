<?php

declare(strict_types=1);

namespace App\Domain\Ordering\DTO;

use Illuminate\Foundation\Http\FormRequest;

final readonly class AttachTableData
{
    public function __construct(
        public int $tableSessionId,
        public int $diningTableId,
        public int $attachedByUserId,
    ) {}

    public static function fromRequest(FormRequest $request): self
    {
        return new self(
            tableSessionId: (int) $request->route('tableSession')->id,
            diningTableId: $request->integer('dining_table_id'),
            attachedByUserId: (int) $request->user()->id,
        );
    }
}
