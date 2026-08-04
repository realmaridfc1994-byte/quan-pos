<?php

declare(strict_types=1);

namespace App\Domain\Sync\Enums;

/** Tám loại thao tác được phép offline — docs/thiet-ke-dong-bo.md mục 1. */
enum OperationType: string
{
    case OpenSession = 'open_session';
    case AttachTable = 'attach_table';
    case DetachTable = 'detach_table';
    case PlaceOrder = 'place_order';
    case SendToKitchen = 'send_to_kitchen';
    case CancelOrderItem = 'cancel_order_item';
    case RecordPayment = 'record_payment';
    case CloseSession = 'close_session';
}
