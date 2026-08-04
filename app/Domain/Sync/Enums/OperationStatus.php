<?php

declare(strict_types=1);

namespace App\Domain\Sync\Enums;

/** Năm trạng thái trả về cho từng thao tác — docs/thiet-ke-dong-bo.md mục 3.3. */
enum OperationStatus: string
{
    case Applied = 'applied';
    case Duplicate = 'duplicate';
    case Conflict = 'conflict';
    case Deferred = 'deferred';
    case Rejected = 'rejected';
}
