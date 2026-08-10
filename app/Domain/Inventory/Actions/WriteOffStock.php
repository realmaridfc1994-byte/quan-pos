<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\DTO\RecordStockMovementData;
use App\Domain\Inventory\DTO\WriteOffStockData;
use App\Domain\Inventory\Enums\StockMovementRefType;
use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Inventory\Enums\WasteReasonCategory;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Staffing\Enums\UserRole;
use App\Domain\Staffing\Models\User;
use App\Exceptions\DomainException;

/**
 * Ghi hao hụt: vỡ/hỏng, hết hạn, hao hụt tự nhiên, dùng nội bộ.
 *
 * Chỉ chủ quán và thu ngân được ghi (staff/kitchen không quản lý kho, giống
 * quy tắc ở PurchasePolicy). Loại hao hụt (WasteReasonCategory) chỉ tồn tại
 * ở tầng code — schema.md chốt stock_movements.type không có chỗ riêng cho
 * từng loại, nên ghép thành tiền tố của reason (xem ghepLyDo()).
 *
 * Luôn ghi type = 'waste' (K5/K7 không áp dụng ở đây vì không có ref tới
 * order_items — dùng ref_type = manual).
 */
final class WriteOffStock
{
    public function __construct(
        private readonly RecordStockMovement $recordStockMovement,
    ) {}

    public function handle(WriteOffStockData $data): StockMovement
    {
        $nguoiGhi = User::query()->findOrFail($data->createdByUserId);

        if (! in_array($nguoiGhi->role, [UserRole::Owner, UserRole::Cashier], true)) {
            throw new DomainException('Chỉ chủ quán hoặc thu ngân được ghi hao hụt.');
        }

        if ($data->qty < 1) {
            throw new DomainException('Số lượng hao hụt phải lớn hơn 0.');
        }

        $chiTiet = trim($data->detail);
        if ($chiTiet === '') {
            throw new DomainException('Phải ghi rõ lý do hao hụt.');
        }

        return $this->recordStockMovement->handle(new RecordStockMovementData(
            ingredientId: $data->ingredientId,
            type: StockMovementType::Waste,
            qtyDelta: -$data->qty,
            knownCost: null,
            refType: StockMovementRefType::Manual,
            refId: null,
            reason: $this->ghepLyDo($data->category, $chiTiet),
            approvedByUserId: null,
            createdByUserId: $data->createdByUserId,
            shiftId: $data->shiftId,
        ));
    }

    private function ghepLyDo(WasteReasonCategory $category, string $chiTiet): string
    {
        return "[{$category->label()}] {$chiTiet}";
    }
}
