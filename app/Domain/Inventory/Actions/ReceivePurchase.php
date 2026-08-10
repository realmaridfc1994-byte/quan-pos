<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\DTO\ReceivePurchaseData;
use App\Domain\Inventory\DTO\RecordStockMovementData;
use App\Domain\Inventory\Enums\PurchaseStatus;
use App\Domain\Inventory\Enums\StockMovementRefType;
use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Inventory\Models\Purchase;
use App\Exceptions\DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Nhận hàng vào kho — chuyển phiếu nhập từ draft sang received và đẩy từng
 * dòng vào sổ cái kho qua RecordStockMovement (một cửa duy nhất, xem
 * docs/thiet-ke-gia-von.md mục 1). KHÔNG BAO GIỜ tự ghi vào stock_balances.
 */
final class ReceivePurchase
{
    public function __construct(
        private readonly RecordStockMovement $recordStockMovement,
    ) {}

    public function handle(ReceivePurchaseData $data): Purchase
    {
        return DB::transaction(function () use ($data): Purchase {
            $purchase = Purchase::query()->lockForUpdate()->findOrFail($data->purchaseId);

            // Kiểm status TRƯỚC khi chạm sổ cái — nhận hai lần phải bị chặn ở
            // đây với thông báo tiếng Việt, không để lộ lỗi khoá duy nhất
            // uq_stock_movements_ref thô từ database.
            if ($purchase->status !== PurchaseStatus::Draft) {
                throw new DomainException('Phiếu nhập này đã nhận hàng hoặc đã huỷ, không nhận lại được nữa.');
            }

            // Khoá theo ingredient_id TĂNG DẦN — chống kẹt chéo khi nhiều phiếu
            // nhận cùng lúc đụng chung nguyên liệu, giống quy tắc khoá nhiều
            // bàn ở CLAUDE.md mục 4.18.
            $dongPhieu = $purchase->items()->orderBy('ingredient_id')->get();

            foreach ($dongPhieu as $dong) {
                $this->recordStockMovement->handle(new RecordStockMovementData(
                    ingredientId: $dong->ingredient_id,
                    type: StockMovementType::Purchase,
                    qtyDelta: $dong->qty_base,
                    knownCost: $dong->line_cost,
                    refType: StockMovementRefType::PurchaseItem,
                    refId: $dong->id,
                    reason: null,
                    approvedByUserId: null,
                    createdByUserId: $data->receivedByUserId,
                    shiftId: null,
                ));
            }

            $purchase->update([
                'status' => PurchaseStatus::Received,
                'received_at' => now(),
                'received_by_user_id' => $data->receivedByUserId,
            ]);

            return $purchase;
        });
    }
}
