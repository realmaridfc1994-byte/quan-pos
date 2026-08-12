<?php

declare(strict_types=1);

use App\Domain\Inventory\Actions\RecordStockMovement;
use App\Domain\Inventory\DTO\RecordStockMovementData;
use App\Domain\Inventory\Enums\StockMovementRefType;
use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Inventory\Models\Ingredient;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Staffing\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Bất biến K1 ở TẦNG DATABASE — trigger trg_stock_movements_no_update.
 *
 * ĐÂY KHÔNG PHẢI BẢN TRÙNG của StockMovementImmutableTest. Hai file gác hai
 * lớp khác nhau, bắt hai loại lỗi khác nhau:
 *
 *   - StockMovementImmutableTest gác lớp Eloquent: code nghiệp vụ viết nhầm
 *     ($movement->update(...), query()->update(...)). Báo lỗi sớm, tiếng Việt,
 *     kèm ngữ cảnh.
 *   - File này gác lớp database: MỌI đường đi vòng qua Eloquent — DB::table(),
 *     DB::statement(), SQL gõ tay trong phpMyAdmin, script vá dữ liệu viết
 *     vội. Đây là chốt cuối cùng, không ai đi vòng được.
 *
 * Nên mọi test ở đây CỐ Ý không dùng Eloquent để ghi. Dùng Eloquent thì lớp
 * Model chặn trước và trigger không bao giờ được kiểm.
 */
function ghiMotDongSoCaiChoTrigger(): StockMovement
{
    return (new RecordStockMovement)->handle(new RecordStockMovementData(
        uuid: (string) Str::uuid(),
        ingredientId: Ingredient::factory()->create()->id,
        type: StockMovementType::Purchase,
        qtyDelta: 100,
        knownCost: 2_000_000,
        refType: StockMovementRefType::Manual,
        refId: null,
        reason: null,
        approvedByUserId: null,
        createdByUserId: User::factory()->owner()->create()->id,
        shiftId: null,
    ));
}

it('DB::table()->update() bị database chặn, không phải chỉ Eloquent chặn', function () {
    $movement = ghiMotDongSoCaiChoTrigger();

    DB::table('stock_movements')->where('id', $movement->id)->update(['reason' => 'vá tay']);
})->throws(QueryException::class);

it('câu UPDATE thô gõ thẳng vào database cũng bị chặn', function () {
    $movement = ghiMotDongSoCaiChoTrigger();

    DB::statement('UPDATE stock_movements SET qty_delta = 999 WHERE id = ?', [$movement->id]);
})->throws(QueryException::class);

it('DB::table()->increment() cũng bị chặn', function () {
    $movement = ghiMotDongSoCaiChoTrigger();

    DB::table('stock_movements')->where('id', $movement->id)->increment('qty_delta', 5);
})->throws(QueryException::class);

it('UPDATE hàng loạt không điều kiện cũng bị chặn ngay từ dòng đầu tiên', function () {
    ghiMotDongSoCaiChoTrigger();
    ghiMotDongSoCaiChoTrigger();

    DB::statement('UPDATE stock_movements SET reason = NULL');
})->throws(QueryException::class);

it('UPDATE không đổi giá trị nào cũng vẫn bị chặn — trigger không xét nội dung', function () {
    $movement = ghiMotDongSoCaiChoTrigger();

    // Gán lại đúng giá trị cũ. Không có thay đổi thật nào, nhưng vẫn là một
    // câu UPDATE — và sổ cái thì không nhận câu UPDATE nào cả.
    DB::statement('UPDATE stock_movements SET qty_delta = qty_delta WHERE id = ?', [$movement->id]);
})->throws(QueryException::class);

it('lỗi trả về mang đúng mã SQLSTATE 45000 và câu giải thích của mình', function () {
    $movement = ghiMotDongSoCaiChoTrigger();

    try {
        DB::table('stock_movements')->where('id', $movement->id)->update(['reason' => 'vá tay']);
        $this->fail('Đáng lẽ phải bị trigger chặn.');
    } catch (QueryException $e) {
        expect($e->getMessage())->toContain('45000')
            ->and($e->getMessage())->toContain('so cai chi ghi them');
    }
});

it('không dòng nào xê dịch sau khi mọi đường UPDATE đều bị chặn', function () {
    $movement = ghiMotDongSoCaiChoTrigger();

    foreach ([
        fn () => DB::table('stock_movements')->where('id', $movement->id)->update(['qty_delta' => 999]),
        fn () => DB::statement('UPDATE stock_movements SET qty_delta = 999 WHERE id = ?', [$movement->id]),
        fn () => DB::table('stock_movements')->where('id', $movement->id)->increment('qty_delta', 5),
    ] as $duongSua) {
        try {
            $duongSua();
        } catch (QueryException) {
            // Đúng như mong đợi — chỉ cần chắc dữ liệu không xê dịch.
        }
    }

    expect(DB::table('stock_movements')->where('id', $movement->id)->value('qty_delta'))->toBe(100);
});

it('trigger KHÔNG chặn nhầm việc ghi dòng mới', function () {
    $mot = ghiMotDongSoCaiChoTrigger();
    $hai = ghiMotDongSoCaiChoTrigger();

    expect($hai->id)->not->toBe($mot->id)
        ->and(StockMovement::query()->count())->toBe(2);
});

/**
 * ĐẢO NGƯỢC test cũ (12/08, review Phase 3 Bước 10).
 *
 * Trước đây test này khẳng định "xoá sổ cái không bị trigger chặn — xoá do tầng
 * khác lo". Tầng khác đó là tầng Model, mà tầng Model chỉ bắt được code đi qua
 * Eloquent — đúng cái lý do đã khiến dự án thêm trigger chặn SỬA. Lý lẽ "khoá
 * ngoại đã chặn phần lớn đường xoá" cũng không đủ: một dòng sổ cái không phải
 * dòng cuối của nguyên liệu nào thì không khoá ngoại nào giữ, và xoá đúng một
 * dòng như vậy làm tổng sổ cái lệch khỏi bảng tồn vĩnh viễn.
 */
it('trigger chặn cả việc XOÁ dòng sổ cái, kể cả bằng SQL thô', function () {
    $movement = ghiMotDongSoCaiChoTrigger();

    // Gỡ tham chiếu từ bảng tồn trước, để chắc chắn thứ chặn là trigger chứ
    // không phải khoá ngoại.
    DB::table('stock_balances')->where('last_movement_id', $movement->id)->update(['last_movement_id' => null]);

    try {
        DB::table('stock_movements')->where('id', $movement->id)->delete();
        test()->fail('Phải bị trigger chặn.');
    } catch (QueryException $e) {
        expect($e->getMessage())->toContain('khong duoc xoa');
    }

    expect(DB::table('stock_movements')->where('id', $movement->id)->exists())->toBeTrue();
});

it('trigger không đụng tới bảng khác — sửa bảng tồn vẫn bình thường', function () {
    $movement = ghiMotDongSoCaiChoTrigger();

    DB::table('stock_balances')->where('ingredient_id', $movement->ingredient_id)->update(['qty' => 42]);

    expect(DB::table('stock_balances')->where('ingredient_id', $movement->ingredient_id)->value('qty'))->toBe(42);
});
