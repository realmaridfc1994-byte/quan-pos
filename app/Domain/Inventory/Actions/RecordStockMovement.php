<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\DTO\RecordStockMovementData;
use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Inventory\Models\StockBalance;
use App\Domain\Inventory\Models\StockMovement;
use App\Exceptions\DomainException;
use App\Exceptions\StockLedgerInvariantViolatedException;
use App\Support\StockCost;
use Illuminate\Support\Facades\DB;

/**
 * MỘT CỬA DUY NHẤT ghi vào stock_balances — xem docs/thiet-ke-gia-von.md mục 1.
 *
 * Nhập hàng, bán món, hỏng vỡ, kiểm kê, điều chỉnh, trả hàng nhà cung cấp —
 * tất cả đi qua Action này. Không Action nào khác được UPDATE/INSERT vào
 * stock_balances hay StockMovement::create() — xem tests/Feature/Inventory/
 * OnlyOneStockWriterTest.php. Luật này được khoá cứng ở tầng Model (xem
 * StockBalance::choPhepGhi()), không chỉ dựa vào quy ước đọc trên giấy.
 *
 * Gọi cho nhiều nguyên liệu trong một giao dịch: người GỌI (Action cha) phải
 * tự khoá/gọi theo ingredient_id TĂNG DẦN, giống quy tắc khoá nhiều bàn ở
 * CLAUDE.md mục 4.18. Action này chỉ khoá đúng một dòng stock_balances mỗi
 * lần handle().
 */
final class RecordStockMovement
{
    public function handle(RecordStockMovementData $data): StockMovement
    {
        return DB::transaction(
            fn (): StockMovement => StockBalance::choPhepGhi(
                fn (): StockMovement => $this->ghiSoCaiVaCapNhatTon($data)
            )
        );
    }

    private function ghiSoCaiVaCapNhatTon(RecordStockMovementData $data): StockMovement
    {
        $balance = StockBalance::query()
            ->lockForUpdate()
            ->firstOrCreate(
                ['ingredient_id' => $data->ingredientId],
                ['qty' => 0, 'total_cost' => 0]
            );

        [$costDelta, $hasCost] = $this->tinhGiaVon($data, $balance);

        $qtyAfter = $balance->qty + $data->qtyDelta;
        $costAfter = $balance->total_cost + $costDelta;

        // Van an toàn K9 — nổ ra là lỗi lập trình trong tính giá vốn,
        // không phải lỗi dữ liệu người dùng nhập vào.
        if ($qtyAfter === 0 && $costAfter !== 0) {
            throw new StockLedgerInvariantViolatedException(
                "Lỗi lập trình (không phải lỗi dữ liệu): tồn nguyên liệu #{$data->ingredientId} về 0 mà trị giá còn {$costAfter} đồng."
            );
        }

        if ($qtyAfter > 0 && $costAfter < 0) {
            throw new StockLedgerInvariantViolatedException(
                "Lỗi lập trình (không phải lỗi dữ liệu): tồn nguyên liệu #{$data->ingredientId} dương ({$qtyAfter}) mà trị giá âm ({$costAfter} đồng)."
            );
        }

        // Ghi sổ cái TRƯỚC, cập nhật tồn SAU — sổ cái là sự thật, bảng
        // tồn là bản tóm tắt.
        $movement = StockMovement::query()->create([
            'ingredient_id' => $data->ingredientId,
            'type' => $data->type,
            'qty_delta' => $data->qtyDelta,
            'cost_delta' => $costDelta,
            'qty_after' => $qtyAfter,
            'cost_after' => $costAfter,
            'has_cost' => $hasCost,
            'ref_type' => $data->refType,
            'ref_id' => $data->refId,
            'reason' => $data->reason,
            'approved_by_user_id' => $data->approvedByUserId,
            'created_by_user_id' => $data->createdByUserId,
            'shift_id' => $data->shiftId,
            'occurred_at' => $data->occurredAt ?? now(),
        ]);

        $balance->update([
            'qty' => $qtyAfter,
            'total_cost' => $costAfter,
            'last_movement_id' => $movement->id,
            'updated_at' => now(),
        ]);

        return $movement;
    }

    /**
     * @return array{0: int, 1: bool} [costDelta, hasCost]
     */
    private function tinhGiaVon(RecordStockMovementData $data, StockBalance $balance): array
    {
        if ($data->qtyDelta > 0) {
            if ($data->type === StockMovementType::Purchase) {
                if ($data->knownCost === null) {
                    throw new DomainException('Nhập hàng phải ghi rõ số tiền thật trả nhà cung cấp.');
                }

                return [$data->knownCost, true];
            }

            // Kiểm kê thừa, điều chỉnh tăng: dùng giá trung bình hiện tại
            // (docs/thiet-ke-gia-von.md mục 5.1), không phải tiền thật.
            $ketQua = StockCost::giaVonNhapTheoTrungBinh($balance->qty, $balance->total_cost, $data->qtyDelta);

            return [$ketQua['cost'], $ketQua['has_cost']];
        }

        // Ra kho — bán, hỏng vỡ, kiểm kê thiếu, trả hàng, điều chỉnh giảm.
        $n = abs($data->qtyDelta);
        $ketQua = StockCost::giaVonXuat($balance->qty, $balance->total_cost, $n);

        return [-$ketQua['cost'], $ketQua['has_cost']];
    }
}
