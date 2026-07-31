<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Enums;

enum TableSessionStatus: string
{
    case Open = 'open';
    case Billing = 'billing';
    case Closed = 'closed';
    case Void = 'void';
}
