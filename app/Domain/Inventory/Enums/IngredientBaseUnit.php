<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Enums;

enum IngredientBaseUnit: string
{
    case Gram = 'g';
    case Mililit = 'ml';
    case Cai = 'cai';
    case Lon = 'lon';
    case Chai = 'chai';

    public function label(): string
    {
        return match ($this) {
            self::Gram => 'Gam',
            self::Mililit => 'Mililít',
            self::Cai => 'Cái',
            self::Lon => 'Lon',
            self::Chai => 'Chai',
        };
    }
}
