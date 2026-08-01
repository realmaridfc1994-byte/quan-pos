<?php

declare(strict_types=1);

namespace App\Domain\Ordering\DTO;

use Illuminate\Foundation\Http\FormRequest;

final readonly class VoidTableSessionData
{
    public function __construct(
        public int $tableSessionId,
        public string $reason,
        public int $voidedByUserId,
    ) {}

    public static function fromRequest(FormRequest $request): self
    {
        return new self(
            tableSessionId: (int) $request->route('tableSession')->id,
            reason: $request->string('reason')->toString(),
            voidedByUserId: (int) $request->user()->id,
        );
    }
}
