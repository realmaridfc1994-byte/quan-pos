<?php

declare(strict_types=1);

use App\Domain\Inventory\Actions\RecordStockMovement;
use App\Domain\Inventory\DTO\RecordStockMovementData;
use App\Domain\Inventory\Enums\StockMovementRefType;
use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Inventory\Models\Ingredient;
use App\Domain\Inventory\Models\StockBalance;
use App\Domain\Staffing\Models\User;
use App\Exceptions\StockBalanceWriteNotAllowedException;

/**
 * Test tự chặn tái phát — docs/thiet-ke-gia-von.md mục 1:
 * "Không Action nào khác được chạm vào stock_balances."
 *
 * Chặn ở TẦNG MODEL (xem StockBalance::choPhepGhi()), không dựa vào quét văn
 * bản — quét văn bản từng báo động giả khi một file khác chỉ NHẮC TỚI
 * StockBalance trong comment mà không thật sự ghi (xem ReceivePurchase.php).
 * Kiểm hành vi thật: ghi có bị chặn hay không, không phải "có chuỗi chữ nào
 * gần nhau không".
 */
it('ghi thẳng vào StockBalance từ bên ngoài RecordStockMovement bị chặn', function () {
    $nl = Ingredient::factory()->create();

    StockBalance::query()->create(['ingredient_id' => $nl->id, 'qty' => 10, 'total_cost' => 1000]);
})->throws(StockBalanceWriteNotAllowedException::class);

it('gọi update() trên một instance StockBalance có sẵn cũng bị chặn', function () {
    $nl = Ingredient::factory()->create();

    $balance = StockBalance::choPhepGhi(
        fn () => StockBalance::query()->create(['ingredient_id' => $nl->id, 'qty' => 10, 'total_cost' => 1000])
    );

    $balance->update(['qty' => 999]);
})->throws(StockBalanceWriteNotAllowedException::class);

it('firstOrCreate từ bên ngoài cũng bị chặn', function () {
    $nl = Ingredient::factory()->create();

    StockBalance::query()->firstOrCreate(['ingredient_id' => $nl->id], ['qty' => 0, 'total_cost' => 0]);
})->throws(StockBalanceWriteNotAllowedException::class);

it('ghi qua quan hệ $ingredient->stockBalance()->update() cũng bị chặn — đường KHÔNG đi qua save()', function () {
    $nl = Ingredient::factory()->create();

    StockBalance::choPhepGhi(
        fn () => StockBalance::query()->create(['ingredient_id' => $nl->id, 'qty' => 10, 'total_cost' => 1000])
    );

    $nl->stockBalance()->update(['qty' => 500]);
})->throws(StockBalanceWriteNotAllowedException::class);

it('increment()/decrement() qua quan hệ cũng bị chặn', function () {
    $nl = Ingredient::factory()->create();

    StockBalance::choPhepGhi(
        fn () => StockBalance::query()->create(['ingredient_id' => $nl->id, 'qty' => 10, 'total_cost' => 1000])
    );

    $nl->stockBalance()->increment('qty', 5);
})->throws(StockBalanceWriteNotAllowedException::class);

it('xoá một dòng StockBalance từ bên ngoài cũng bị chặn', function () {
    $nl = Ingredient::factory()->create();

    $balance = StockBalance::choPhepGhi(
        fn () => StockBalance::query()->create(['ingredient_id' => $nl->id, 'qty' => 10, 'total_cost' => 1000])
    );

    $balance->delete();
})->throws(StockBalanceWriteNotAllowedException::class);

it('gọi RecordStockMovement thì thành công, tồn kho đổi đúng', function () {
    $nl = Ingredient::factory()->create();
    $user = User::factory()->owner()->create();

    $movement = (new RecordStockMovement)->handle(new RecordStockMovementData(
        ingredientId: $nl->id,
        type: StockMovementType::Purchase,
        qtyDelta: 100,
        knownCost: 2_000_000,
        refType: StockMovementRefType::Manual,
        refId: null,
        reason: null,
        approvedByUserId: null,
        createdByUserId: $user->id,
        shiftId: null,
    ));

    expect($movement->id)->not->toBeNull();

    $balance = StockBalance::query()->find($nl->id);
    expect($balance->qty)->toBe(100)
        ->and($balance->total_cost)->toBe(2_000_000);
});

it('choPhepGhi() luôn tắt cờ lại kể cả khi closure ném lỗi — không kẹt cờ mở mãi', function () {
    $nl = Ingredient::factory()->create();

    try {
        StockBalance::choPhepGhi(function () {
            throw new RuntimeException('Lỗi giả lập trong lúc đang được phép ghi.');
        });
    } catch (RuntimeException) {
        // Bỏ qua — chỉ quan tâm cờ có được tắt lại đúng không.
    }

    // Nếu cờ còn kẹt ở trạng thái bật, dòng dưới sẽ KHÔNG ném lỗi (sai).
    StockBalance::query()->create(['ingredient_id' => $nl->id, 'qty' => 1, 'total_cost' => 1]);
})->throws(StockBalanceWriteNotAllowedException::class);
