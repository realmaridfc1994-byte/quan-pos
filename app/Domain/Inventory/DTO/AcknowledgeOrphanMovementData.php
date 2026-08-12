<?php

declare(strict_types=1);

namespace App\Domain\Inventory\DTO;

/**
 * Đầu vào của AcknowledgeOrphanMovement — chưa có endpoint HTTP nào gọi thẳng
 * vào Action này (chỉ có lệnh Artisan stock:ghi-chu-mo-coi), giống cách
 * RecordStockMovementData không có fromRequest().
 */
final readonly class AcknowledgeOrphanMovementData
{
    public function __construct(
        public int $stockMovementId,
        /** Người xác nhận thấy gì — hiện lại trong bản đối soát các lần sau. */
        public string $note,
        /** Vì sao dòng này không phải lỗi sổ sách. */
        public string $reason,
        public int $acknowledgedByUserId,
    ) {}
}
