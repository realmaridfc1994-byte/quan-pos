<?php

declare(strict_types=1);

namespace App\Domain\Staffing\DTO;

use Illuminate\Foundation\Http\FormRequest;

final readonly class PinVerifyData
{
    public function __construct(
        public int $userId,
        public string $pin,
        public int $requestedByUserId,
    ) {}

    public static function fromRequest(FormRequest $request): self
    {
        return new self(
            userId: $request->integer('user_id'),
            pin: $request->string('pin')->toString(),
            requestedByUserId: (int) $request->user()->id,
        );
    }
}
