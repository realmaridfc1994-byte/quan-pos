<?php

declare(strict_types=1);

namespace App\Domain\Sync\Enums;

enum ConflictStatus: string
{
    case Pending = 'pending';
    case Resolved = 'resolved';
    case Dismissed = 'dismissed';
}
