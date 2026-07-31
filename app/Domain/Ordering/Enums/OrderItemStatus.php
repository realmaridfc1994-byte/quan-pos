<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Enums;

enum OrderItemStatus: string
{
    case Ordered = 'ordered';
    case Served = 'served';
    case Cancelled = 'cancelled';
}
