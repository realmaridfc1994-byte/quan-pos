<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Enums;

enum StockMovementRefType: string
{
    case OrderItem = 'order_item';
    case PurchaseItem = 'purchase_item';
    case StockTakeItem = 'stock_take_item';
    case Manual = 'manual';
}
