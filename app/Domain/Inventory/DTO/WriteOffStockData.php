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
        /**
         * Mã vân tay do MÀN HÌNH sinh một lần lúc mở form và gửi kèm khi bấm
         * lưu — bấm hai lần vì mạng lag chỉ ghi một dòng hao hụt. Không sinh ở
         * server: server sinh thì mỗi lần bấm lại ra một mã mới, chẳng chống
         * được gì.
         */
        public string $uuid,
        public int $ingredientId,
        public WasteReasonCategory $category,
        /** Mô tả chi tiết, ghép sau tên loại thành reason đầy đủ. */
        public string $detail,
        /** Số lượng hao hụt, luôn dương — Action tự đổi dấu khi ghi sổ cái. */
        public int $qty,
        public int $createdByUserId,
        public ?int $shiftId,
        /**
         * Chủ quán duyệt, CHỈ bắt buộc khi lô hao hụt đáng giá từ ngưỡng
         * CauHinhQuan::nguongHaoHutCanPin() trở lên. Dưới ngưỡng thì để null —
         * thu ngân tự ghi, chỉ cần lý do.
         */
        public ?int $approverUserId = null,
        public ?string $approverPin = null,
    ) {}
}
