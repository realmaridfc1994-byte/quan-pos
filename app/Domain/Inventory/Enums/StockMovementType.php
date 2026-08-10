<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Enums;

enum StockMovementType: string
{
    case Purchase = 'purchase';
    case Sale = 'sale';
    case Waste = 'waste';
    case Adjust = 'adjust';
    case Stocktake = 'stocktake';
    case Return = 'return';
    case CloseResidual = 'close_residual';
}
