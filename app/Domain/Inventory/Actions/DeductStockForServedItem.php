<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\DTO\RecordStockMovementData;
use App\Domain\Inventory\Enums\StockMovementRefType;
use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Ordering\Models\OrderItem;

/**
 * Trừ kho theo định lượng cho MỘT dòng món khi bếp báo đã phục vụ.
 *
 * Gọi từ App\Domain\Ordering\Actions\UpdateOrderItemStatus, TRONG CÙNG
 * transaction đặt served_at (docs/schema.md K.9) — trừ kho hỏng thì cả giao
 * dịch rollback, served_at không được đặt.
 *
 * Chỉ MỘT đường trừ kho: qua bảng recipes (docs/schema.md K.1 — kể cả bia lon
 * bán thẳng cũng là một dòng recipe với qty_base = số đơn vị kho tiêu thụ cho
 * một đơn vị bán ra, không có nhánh "trừ thẳng" riêng).
 *
 * Chống trừ hai lần — hai lớp (K5, K7):
 *   1. Lớp code: kiểm tra đã có dòng sổ cái cho order_item này chưa TRƯỚC khi
 *      chạm sổ cái. Gọi lại lần hai không tạo dòng nào, không ném lỗi.
 *   2. Lớp database: khoá uq_stock_movements_ref (ref_type, ref_id,
 *      ingredient_id) chặn đứng kể cả khi lớp code có lỗi.
 * Dòng tách ra khi huỷ một phần (split_from_item_id khác rỗng) KHÔNG BAO GIỜ
 * trừ kho — nó kế thừa served_at của dòng gốc nhưng nguyên liệu đã bị dòng
 * gốc trừ rồi.
 *
 * Nguyên liệu không đủ tồn vẫn cho trừ, để tồn về âm (quyết định đã chốt ở
 * K.9) — bếp không bao giờ bị chặn báo món xong vì lý do tồn kho.
 */
final class DeductStockForServedItem
{
    public function __construct(
        private readonly RecordStockMovement $recordStockMovement,
    ) {}

    public function handle(OrderItem $item, int $performedByUserId, ?int $shiftId): void
    {
        if ($item->split_from_item_id !== null) {
            return;
        }

        $daTruRoi = StockMovement::query()
            ->where('ref_type', StockMovementRefType::OrderItem)
            ->where('ref_id', $item->id)
            ->exists();

        if ($daTruRoi) {
            return;
        }

        $bienThe = $item->productVariant;

        if (! $bienThe->deducts_stock) {
            return;
        }

        $dinhLuong = $bienThe->recipes()->orderBy('ingredient_id')->get();

        foreach ($dinhLuong as $dong) {
            $this->recordStockMovement->handle(new RecordStockMovementData(
                ingredientId: $dong->ingredient_id,
                type: StockMovementType::Sale,
                qtyDelta: -($dong->qty_base * $item->quantity),
                knownCost: null,
                refType: StockMovementRefType::OrderItem,
                refId: $item->id,
                reason: null,
                approvedByUserId: null,
                createdByUserId: $performedByUserId,
                shiftId: $shiftId,
            ));
        }
    }
}
