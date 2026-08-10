<?php

declare(strict_types=1);

namespace App\Domain\Inventory\DTO;

/**
 * Đầu vào của AdjustStock — chưa có endpoint HTTP hay màn hình Filament
 * nào gọi thẳng vào Action này ở Bước 6 (đề bài chỉ yêu cầu Action + test).
 */
final readonly class AdjustStockData
{
    public function __construct(
        public int $ingredientId,
        /** Dương = tăng tồn, âm = giảm tồn. Không bao giờ bằng 0. */
        public int $qtyDelta,
        public string $reason,
        /** Chủ quán đang đứng máy — phải là owner (kiểm ở Action). */
        public int $requestedByUserId,
        /** Người duyệt bằng PIN — cũng phải là owner. */
        public int $approverUserId,
        public string $approverPin,
        public ?int $shiftId,
    ) {}
}
