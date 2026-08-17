<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\DTO\CloseStockTakeData;
use App\Domain\Inventory\DTO\RecordStockMovementData;
use App\Domain\Inventory\Enums\StockMovementRefType;
use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Inventory\Enums\StockTakeStatus;
use App\Domain\Inventory\Models\StockTake;
use App\Exceptions\DomainException;
use App\Support\StockMovementUuid;
use Illuminate\Support\Facades\DB;

/**
 * Chốt phiếu kiểm kê: sinh dòng sổ cái điều chỉnh (type=stocktake) cho từng
 * nguyên liệu LỆCH (diff_qty <> 0), rồi khoá phiếu lại — không sửa được nữa.
 *
 * Dòng chưa đếm (counted_qty NULL) bị bỏ qua, không sinh gì cả. Dòng khớp
 * (diff_qty = 0) cũng không sinh gì cả — sổ cái chỉ ghi khi có thật một
 * chênh lệch cần điều chỉnh.
 *
 * Khoá theo ingredient_id TĂNG DẦN trước khi gọi RecordStockMovement (mỗi
 * lần khoá đúng một dòng stock_balances) — chống kẹt chéo, giống quy tắc
 * khoá nhiều bàn ở CLAUDE.md mục 18 và chuỗi khoá StockTake → StockBalance
 * ở docs/schema.md K.9.
 */
final class CloseStockTake
{
    public function __construct(
        private readonly RecordStockMovement $recordStockMovement,
    ) {}

    public function handle(CloseStockTakeData $data): StockTake
    {
        return DB::connection('tenant')->transaction(function () use ($data): StockTake {
            $phieu = StockTake::query()->lockForUpdate()->findOrFail($data->stockTakeId);

            if ($phieu->status !== StockTakeStatus::Open) {
                throw new DomainException('Phiếu kiểm kê này đã chốt hoặc đã huỷ rồi, không chốt lại được nữa.');
            }

            $dongLech = $phieu->items()
                ->whereNotNull('counted_qty')
                ->where('diff_qty', '<>', 0)
                ->orderBy('ingredient_id')
                ->get();

            // Cộng dồn bằng số nguyên trần, KHÔNG qua App\Support\Money — đây
            // là ngoại lệ duy nhất của CLAUDE.md mục 8, đã ghi thành luật ở đó
            // (12/08). Lý do: cost_delta là chênh lệch CÓ DẤU (kiểm kê thiếu ra
            // số âm), còn Money chặn số âm theo đúng thiết kế của nó. Ép qua
            // Money sẽ nổ lỗi ở đúng trường hợp bình thường nhất.
            // Mở cửa cho chính mình: từ 12/08 RecordStockMovement từ chối ghi
            // khi còn phiếu kiểm kê đang mở (K18 — kho phải đứng yên trong lúc
            // kiểm kê). Phiếu này vẫn đang "open" ngay lúc các dòng điều chỉnh
            // được ghi, chỉ đóng lại ở cuối — không có cửa này thì phiếu kiểm kê
            // tự chặn chính nó. Cùng khuôn StockBalance::choPhepGhi(): cờ luôn
            // được tắt lại kể cả khi bên trong ném lỗi.
            $tongChenhLech = StockTake::choPhepGhiKhiChotPhieu(function () use ($dongLech, $data): int {
                $tong = 0;

                foreach ($dongLech as $dong) {
                    $movement = $this->recordStockMovement->handle(new RecordStockMovementData(
                        uuid: StockMovementUuid::tuChungTu(StockMovementRefType::StockTakeItem, $dong->id, $dong->ingredient_id),
                        ingredientId: $dong->ingredient_id,
                        type: StockMovementType::Stocktake,
                        qtyDelta: $dong->diff_qty,
                        knownCost: null,
                        refType: StockMovementRefType::StockTakeItem,
                        refId: $dong->id,
                        reason: null,
                        approvedByUserId: null,
                        createdByUserId: $data->closedByUserId,
                        shiftId: null,
                    ));

                    $tong += $movement->cost_delta;
                }

                return $tong;
            });

            $phieu->update([
                'status' => StockTakeStatus::Closed,
                'closed_at' => now(),
                'closed_by_user_id' => $data->closedByUserId,
                'total_diff_cost' => $tongChenhLech,
            ]);

            return $phieu->refresh();
        });
    }
}
