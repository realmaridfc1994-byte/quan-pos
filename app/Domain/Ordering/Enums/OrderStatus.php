<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Enums;

enum OrderStatus: string
{
    case Sent = 'sent';
    case Preparing = 'preparing';
    case Served = 'served';
    case Cancelled = 'cancelled';
}
