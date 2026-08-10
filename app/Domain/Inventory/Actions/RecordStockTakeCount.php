<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\DTO\RecordStockTakeCountData;
use App\Domain\Inventory\Enums\StockTakeStatus;
use App\Domain\Inventory\Models\StockTake;
use App\Domain\Inventory\Models\StockTakeItem;
use App\Exceptions\DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Ghi số đếm thực tế cho MỘT dòng kiểm kê. Có thể gọi lại nhiều lần cho cùng
 * một dòng để sửa số đếm — miễn phiếu còn đang mở (đếm nhầm, đếm lại).
 */
final class RecordStockTakeCount
{
    public function handle(RecordStockTakeCountData $data): StockTakeItem
    {
        return DB::transaction(function () use ($data): StockTakeItem {
            $dong = StockTakeItem::query()->lockForUpdate()->findOrFail($data->stockTakeItemId);
            $phieu = StockTake::query()->lockForUpdate()->findOrFail($dong->stock_take_id);

            if ($phieu->status !== StockTakeStatus::Open) {
                throw new DomainException('Phiếu kiểm kê này đã chốt hoặc đã huỷ, không sửa số đếm được nữa.');
            }

            if ($data->countedQty < 0) {
                throw new DomainException('Số đếm không được âm.');
            }

            $dong->update(['counted_qty' => $data->countedQty]);

            return $dong->refresh();
        });
    }
}
