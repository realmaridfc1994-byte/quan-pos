<?php

declare(strict_types=1);

namespace App\Domain\Reservations\Enums;

enum ReservationStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Seated = 'seated';
    case NoShow = 'no_show';
    case Cancelled = 'cancelled';
}
