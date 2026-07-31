<?php

declare(strict_types=1);

namespace App\Domain\Staffing\Enums;

enum CashDirection: string
{
    case In = 'in';
    case Out = 'out';
}
