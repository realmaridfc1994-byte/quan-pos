<?php

declare(strict_types=1);

namespace App\Domain\Inventory\DTO;

use App\Domain\Inventory\Enums\WasteReasonCategory;

/**
 * Đầu vào của WriteOffStock — chưa có endpoint HTTP nào gọi thẳng vào Action
 * này (chỉ có màn hình Filament, WasteRecordResource tự dựng DTO này từ dữ
 * liệu form), giống cách RecordStockMovementData không có fromRequest().
 */
final readonly class WriteOffStockData
{
    public function __construct(
        public int $ingredientId,
        public WasteReasonCategory $category,
        /** Mô tả chi tiết, ghép sau tên loại thành reason đầy đủ. */
        public string $detail,
        /** Số lượng hao hụt, luôn dương — Action tự đổi dấu khi ghi sổ cái. */
        public int $qty,
        public int $createdByUserId,
        public ?int $shiftId,
    ) {}
}
