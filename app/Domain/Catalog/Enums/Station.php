<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Enums;

enum Station: string
{
    case Kitchen = 'kitchen';
    case Bar = 'bar';
}
