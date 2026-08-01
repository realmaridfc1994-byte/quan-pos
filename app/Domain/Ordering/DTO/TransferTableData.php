<?php

declare(strict_types=1);

namespace App\Domain\Ordering\DTO;

use Illuminate\Foundation\Http\FormRequest;

final readonly class TransferTableData
{
    public function __construct(
        public int $tableSessionId,
        public int $fromDiningTableId,
        public int $toDiningTableId,
        public int $actorUserId,
    ) {}

    public static function fromRequest(FormRequest $request): self
    {
        return new self(
            tableSessionId: (int) $request->route('tableSession')->id,
            fromDiningTableId: $request->integer('from_dining_table_id'),
            toDiningTableId: $request->integer('to_dining_table_id'),
            actorUserId: (int) $request->user()->id,
        );
    }
}
