<?php

declare(strict_types=1);

namespace App\Domain\Staffing\DTO;

use App\Domain\Staffing\Models\User;

final readonly class AuthenticatedSession
{
    public function __construct(
        public User $user,
        public string $token,
    ) {}
}
