<?php

declare(strict_types=1);

namespace App\Domain\Ordering\DTO;

use Illuminate\Foundation\Http\FormRequest;

final readonly class DetachTableData
{
    public function __construct(
        public int $tableSessionId,
        public int $diningTableId,
    ) {}

    public static function fromRequest(FormRequest $request): self
    {
        return new self(
            tableSessionId: (int) $request->route('tableSession')->id,
            diningTableId: (int) $request->route('diningTable')->id,
        );
    }
}
