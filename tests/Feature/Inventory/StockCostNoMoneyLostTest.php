<?php

declare(strict_types=1);

use App\Domain\Inventory\Actions\RecordStockMovement;
use App\Domain\Inventory\DTO\RecordStockMovementData;
use App\Domain\Inventory\Enums\StockMovementRefType;
use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Inventory\Models\Ingredient;
use App\Domain\Inventory\Models\StockBalance;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Staffing\Models\User;

/**
 * Tầng 3 của bộ test — docs/thiet-ke-gia-von.md mục 8.
 *
 * 1000 thao tác ngẫu nhiên (nhập, bán, kiểm kê thừa, kiểm kê thiếu, trả hàng),
 * hạt ngẫu nhiên CỐ ĐỊNH để không bao giờ đỏ ngẫu nhiên. Sau MỖI thao tác,
 * bốn bất biến phải đúng; cuối cùng đẳng thức tổng tiền phải khớp từng đồng.
 */
it('1000 thao tác ngẫu nhiên không mất một đồng nào, hạt ngẫu nhiên cố định', function () {
    mt_srand(20260807);

    $ingredient = Ingredient::factory()->create();
    $user = User::factory()->owner()->create();
    $action = new RecordStockMovement;

    $tongDaNhap = 0;
    $tongGiaVonDaXuat = 0;
    $refCounter = 0;

    for ($vongLap = 1; $vongLap <= 1000; $vongLap++) {
        $loai = mt_rand(1, 5);

        $movement = match ($loai) {
            1 => (function () use ($action, $ingredient, $user): StockMovement {
                // Nhập hàng — tiền thật.
                $qty = mt_rand(1, 500);
                $cost = mt_rand(1, 100) * $qty;

                return $action->handle(new RecordStockMovementData(
                    ingredientId: $ingredient->id,
                    type: StockMovementType::Purchase,
                    qtyDelta: $qty,
                    knownCost: $cost,
                    refType: StockMovementRefType::Manual,
                    refId: null,
                    reason: null,
                    approvedByUserId: null,
                    createdByUserId: $user->id,
                    shiftId: null,
                ));
            })(),
            2 => $action->handle(new RecordStockMovementData(
                ingredientId: $ingredient->id,
                type: StockMovementType::Sale,
                // Vượt hẳn khoảng nhập (1-500) để cố tình đẩy tồn về 0 và xuống âm.
                qtyDelta: -mt_rand(1, 600),
                knownCost: null,
                refType: StockMovementRefType::OrderItem,
                refId: ++$refCounter,
                reason: null,
                approvedByUserId: null,
                createdByUserId: $user->id,
                shiftId: null,
            )),
            3 => $action->handle(new RecordStockMovementData(
                ingredientId: $ingredient->id,
                type: StockMovementType::Stocktake,
                qtyDelta: mt_rand(1, 300),
                knownCost: null,
                refType: StockMovementRefType::StockTakeItem,
                refId: ++$refCounter,
                reason: null,
                approvedByUserId: null,
                createdByUserId: $user->id,
                shiftId: null,
            )),
            4 => $action->handle(new RecordStockMovementData(
                ingredientId: $ingredient->id,
                type: StockMovementType::Stocktake,
                qtyDelta: -mt_rand(1, 300),
                knownCost: null,
                refType: StockMovementRefType::StockTakeItem,
                refId: ++$refCounter,
                reason: null,
                approvedByUserId: null,
                createdByUserId: $user->id,
                shiftId: null,
            )),
            default => $action->handle(new RecordStockMovementData(
                ingredientId: $ingredient->id,
                type: StockMovementType::Return,
                qtyDelta: -mt_rand(1, 200),
                knownCost: null,
                refType: StockMovementRefType::Manual,
                refId: null,
                reason: null,
                approvedByUserId: null,
                createdByUserId: $user->id,
                shiftId: null,
            )),
        };

        if ($movement->cost_delta > 0) {
            $tongDaNhap += $movement->cost_delta;
        } elseif ($movement->cost_delta < 0) {
            $tongGiaVonDaXuat += -$movement->cost_delta;
        }

        $balance = StockBalance::query()->find($ingredient->id);

        $tongQtyDelta = (int) StockMovement::query()->where('ingredient_id', $ingredient->id)->sum('qty_delta');
        $tongCostDelta = (int) StockMovement::query()->where('ingredient_id', $ingredient->id)->sum('cost_delta');

        expect($balance->qty)->toBe($tongQtyDelta, "Vòng lặp {$vongLap}: qty tồn không khớp sổ cái");
        expect($balance->total_cost)->toBe($tongCostDelta, "Vòng lặp {$vongLap}: total_cost tồn không khớp sổ cái");

        if ($balance->qty === 0) {
            expect($balance->total_cost)->toBe(0, "Vòng lặp {$vongLap}: qty=0 nhưng total_cost != 0");
        }

        if ($balance->qty > 0) {
            expect($balance->total_cost)->toBeGreaterThanOrEqual(0, "Vòng lặp {$vongLap}: qty>0 nhưng total_cost âm");
        }
    }

    $balanceCuoi = StockBalance::query()->find($ingredient->id);

    expect($tongDaNhap - $tongGiaVonDaXuat)->toBe($balanceCuoi->total_cost);
});
