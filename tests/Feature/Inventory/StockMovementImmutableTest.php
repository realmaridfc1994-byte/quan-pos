<?php

declare(strict_types=1);

use App\Domain\Inventory\Actions\RecordStockMovement;
use App\Domain\Inventory\DTO\RecordStockMovementData;
use App\Domain\Inventory\Enums\StockMovementRefType;
use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Inventory\Models\Ingredient;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Staffing\Models\User;
use App\Exceptions\StockMovementImmutableException;
use Illuminate\Support\Facades\DB;

/**
 * Sổ cái kho không bao giờ được sửa hay xoá — bất biến K1.
 *
 * Chặn xoá đã có từ Bước 6. Chặn SỬA thêm ở Bước 10 (review mục 8.2-H): trước
 * đó $movement->update(['qty_delta' => 999]) chạy trót lọt, và một dòng sổ cái
 * sửa được là một dòng sổ cái không còn làm chứng được cho việc gì.
 */
function ghiMotDongSoCai(): StockMovement
{
    $nl = Ingredient::factory()->create();
    $user = User::factory()->owner()->create();

    return (new RecordStockMovement)->handle(new RecordStockMovementData(
        uuid: (string) Str::uuid(),
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
}

it('gọi update() trên một dòng sổ cái đã ghi thì bị chặn', function () {
    ghiMotDongSoCai()->update(['qty_delta' => 999]);
})->throws(StockMovementImmutableException::class);

it('đổi thuộc tính rồi save() cũng bị chặn — đường KHÔNG đi qua update()', function () {
    $movement = ghiMotDongSoCai();

    $movement->cost_delta = 1;
    $movement->save();
})->throws(StockMovementImmutableException::class);

it('sửa HÀNG LOẠT qua query builder cũng bị chặn', function () {
    ghiMotDongSoCai();

    StockMovement::query()->where('qty_delta', 100)->update(['qty_delta' => 1]);
})->throws(StockMovementImmutableException::class);

it('increment() qua query builder cũng bị chặn', function () {
    ghiMotDongSoCai();

    StockMovement::query()->where('qty_delta', 100)->increment('qty_delta', 5);
})->throws(StockMovementImmutableException::class);

it('upsert() — đường sửa trá hình khi trùng uuid — cũng bị chặn', function () {
    $movement = ghiMotDongSoCai();

    StockMovement::query()->upsert(
        [['uuid' => $movement->uuid, 'ingredient_id' => $movement->ingredient_id, 'qty_delta' => 7]],
        ['uuid'],
        ['qty_delta'],
    );
})->throws(StockMovementImmutableException::class);

it('không dòng nào bị đổi sau khi các đường sửa đều bị chặn', function () {
    $movement = ghiMotDongSoCai();

    foreach ([
        fn () => $movement->update(['qty_delta' => 999]),
        fn () => StockMovement::query()->where('id', $movement->id)->update(['qty_delta' => 999]),
        fn () => StockMovement::query()->where('id', $movement->id)->increment('qty_delta'),
    ] as $duongSua) {
        try {
            $duongSua();
        } catch (StockMovementImmutableException) {
            // Đúng như mong đợi — chỉ cần chắc dữ liệu không xê dịch.
        }
    }

    expect(DB::table('stock_movements')->where('id', $movement->id)->value('qty_delta'))->toBe(100);
});

it('xoá vẫn bị chặn như cũ, và tạo dòng mới vẫn chạy bình thường', function () {
    $movement = ghiMotDongSoCai();

    expect(fn () => $movement->delete())->toThrow(StockMovementImmutableException::class)
        ->and(fn () => StockMovement::query()->where('id', $movement->id)->delete())->toThrow(StockMovementImmutableException::class);

    // Chốt chặn không được vô tình đóng luôn cửa ghi dòng mới.
    expect(ghiMotDongSoCai()->id)->not->toBe($movement->id)
        ->and(StockMovement::query()->count())->toBe(2);
});

it('lệnh vá uuid cho dòng cũ vẫn chạy được dù sổ cái đã khoá sửa', function () {
    ghiMotDongSoCai();

    // Không dựng lại được tình huống thật (cột uuid đã NOT NULL từ migration
    // 2026_08_11_000002, nên không còn dòng nào thiếu uuid trên database mới).
    // Kiểm được đúng một điều, và đó là điều quan trọng: lệnh không nổ vì
    // chốt chặn mới.
    $this->artisan('stock:backfill-uuid')->assertSuccessful();
});

// Ở đây từng có một test khẳng định "ghi thẳng qua DB::table() vẫn qua được",
// ghi lại ranh giới của chốt chặn tầng Model. Khẳng định đó KHÔNG CÒN ĐÚNG từ
// 12/08: migration 2026_08_12_000002 thêm trigger trg_stock_movements_no_update
// bịt luôn đường đó ở tầng database. Test thay thế nằm ở
// tests/Feature/Inventory/StockMovementDbTriggerTest.php — hai file gác hai
// lớp khác nhau, không trùng nhau.
