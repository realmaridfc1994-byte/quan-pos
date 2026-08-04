<?php

declare(strict_types=1);

namespace App\Domain\Sync\DTO;

use App\Domain\Sync\Enums\OperationType;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

/** Gói đồng bộ — docs/thiet-ke-dong-bo.md mục 3.1. */
final readonly class SyncBatchData
{
    /** @param list<SyncOperationData> $operations */
    public function __construct(
        public string $batchUuid,
        public string $deviceId,
        public CarbonImmutable $clientTime,
        public array $operations,
        /** Người đang đăng nhập trên máy POS lúc gửi gói — gán làm người thực hiện cho mọi thao tác trong gói. */
        public int $receivedByUserId,
    ) {}

    public static function fromRequest(FormRequest $request): self
    {
        $operations = [];
        foreach ($request->input('operations', []) as $viTri => $op) {
            $operations[] = new SyncOperationData(
                opUuid: (string) $op['op_uuid'],
                type: OperationType::from((string) $op['type']),
                occurredAt: CarbonImmutable::parse((string) $op['occurred_at']),
                dependsOn: array_map('strval', $op['depends_on'] ?? []),
                payload: (array) ($op['payload'] ?? []),
                viTriGoc: $viTri,
            );
        }

        return new self(
            batchUuid: $request->string('batch_uuid')->toString(),
            deviceId: $request->string('device_id')->toString(),
            clientTime: CarbonImmutable::parse($request->string('client_time')->toString()),
            operations: $operations,
            receivedByUserId: (int) $request->user()->id,
        );
    }
}
