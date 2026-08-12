<?php

declare(strict_types=1);

namespace App\Domain\Inventory\DTO;

use App\Domain\Inventory\Enums\StockMovementRefType;
use App\Domain\Inventory\Enums\StockMovementType;
use Carbon\CarbonImmutable;

/**
 * Đầu vào của RecordStockMovement — đủ trường theo schema stock_movements.
 *
 * Không có fromRequest(): lượt này chưa có endpoint HTTP nào gọi thẳng vào
 * RecordStockMovement. Action khác (nhập hàng, trừ kho khi bán...) tự dựng
 * DTO này từ dữ liệu đã có, giống cách CloseTableSession được gọi nội bộ.
 */
final readonly class RecordStockMovementData
{
    public function __construct(
        /**
         * Mã vân tay của dòng sổ cái — chống ghi trùng khi bấm hai lần hoặc
         * gọi lại vì mạng lag. Hao hụt/điều chỉnh: do MÀN HÌNH sinh và gửi lên.
         * Bán món/nhập hàng/kiểm kê: server sinh TẤT ĐỊNH từ chứng từ gốc, xem
         * App\Support\StockMovementUuid.
         */
        public string $uuid,
        public int $ingredientId,
        public StockMovementType $type,
        /** Dương = vào kho, âm = ra kho. Không bao giờ bằng 0. */
        public int $qtyDelta,
        /** Tiền thật trả nhà cung cấp — bắt buộc khi type = purchase và qtyDelta > 0. */
        public ?int $knownCost,
        public StockMovementRefType $refType,
        public ?int $refId,
        public ?string $reason,
        /** Bắt buộc khi type = adjust (K12). */
        public ?int $approvedByUserId,
        public int $createdByUserId,
        public ?int $shiftId,
        public ?CarbonImmutable $occurredAt = null,
    ) {}
}
