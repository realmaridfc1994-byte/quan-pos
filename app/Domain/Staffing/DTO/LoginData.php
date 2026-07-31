<?php

declare(strict_types=1);

namespace App\Domain\Staffing\DTO;

use Illuminate\Foundation\Http\FormRequest;

final readonly class LoginData
{
    public function __construct(
        public string $phone,
        public string $password,
    ) {}

    public static function fromRequest(FormRequest $request): self
    {
        return new self(
            phone: $request->string('phone')->toString(),
            password: $request->string('password')->toString(),
        );
    }
}
