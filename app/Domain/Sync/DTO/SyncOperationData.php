<?php

declare(strict_types=1);

namespace App\Domain\Sync\DTO;

use App\Domain\Sync\Enums\OperationType;
use Carbon\CarbonImmutable;

/** Một thao tác trong gói đồng bộ — docs/thiet-ke-dong-bo.md mục 3.1. */
final readonly class SyncOperationData
{
    /**
     * @param  list<string>  $dependsOn
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $opUuid,
        public OperationType $type,
        public CarbonImmutable $occurredAt,
        public array $dependsOn,
        public array $payload,
        /** Vị trí gốc trong mảng `operations` — dùng để xếp thứ tự khi trùng occurred_at (mục 4.1 #3). */
        public int $viTriGoc,
    ) {}
}
