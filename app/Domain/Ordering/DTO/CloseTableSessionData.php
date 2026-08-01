<?php

declare(strict_types=1);

namespace App\Domain\Ordering\DTO;

use Illuminate\Foundation\Http\FormRequest;

final readonly class CloseTableSessionData
{
    public function __construct(
        public int $tableSessionId,
        public int $closedByUserId,
    ) {}

    public static function fromRequest(FormRequest $request): self
    {
        return new self(
            tableSessionId: (int) $request->route('tableSession')->id,
            closedByUserId: (int) $request->user()->id,
        );
    }
}
