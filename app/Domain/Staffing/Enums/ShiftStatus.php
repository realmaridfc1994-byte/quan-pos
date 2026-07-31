<?php

declare(strict_types=1);

namespace App\Domain\Staffing\Enums;

enum ShiftStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
}
