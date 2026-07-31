<?php

declare(strict_types=1);

use App\Domain\Ordering\Models\OrderItem;
use App\Domain\Ordering\Models\TableSessionTable;
use App\Domain\Staffing\Models\Shift;
use Illuminate\Database\QueryException;

/**
 * Ba cột này do MySQL/MariaDB tự tính (GENERATED ALWAYS ... STORED), không do
 * PHP tính. $fillable đã loại các cột này ra (không có trong danh sách của cả
 * 3 Model), nhưng đó chỉ chặn được mass-assignment qua create()/fill()/update().
 * Test này kiểm chứng lớp bảo vệ THẬT SỰ nằm ở database: dù gán thẳng thuộc
 * tính Eloquent rồi save() — đường mà $fillable không chặn được — MariaDB vẫn
 * từ chối câu lệnh, và giá trị đọc lại từ database luôn là kết quả tự tính,
 * không bao giờ là giá trị đã cố ghi.
 */
it('order_items.line_amount luôn do database tự tính, cố ghi tay bị chặn', function () {
    $item = OrderItem::factory()->create([
        'unit_price' => 10_000,
        'options_amount' => 2_000,
        'quantity' => 3,
    ]);

    expect($item->fresh()->line_amount)->toBe(36_000);

    $item->line_amount = 999_999;

    expect(fn () => $item->save())->toThrow(QueryException::class);
    expect($item->fresh()->line_amount)->toBe(36_000);
});

it('table_session_tables.occupied_table_id luôn do database tự tính, cố ghi tay bị chặn', function () {
    $tst = TableSessionTable::factory()->create();

    expect($tst->fresh()->occupied_table_id)->toEqual($tst->dining_table_id);

    $tst->occupied_table_id = 999_999;

    expect(fn () => $tst->save())->toThrow(QueryException::class);
    expect($tst->fresh()->occupied_table_id)->toEqual($tst->dining_table_id);
});

it('table_session_tables.occupied_table_id là NULL khi bàn đã nhả (detached)', function () {
    $tst = TableSessionTable::factory()->detached()->create();

    expect($tst->fresh()->occupied_table_id)->toBeNull();
});

it('shifts.open_guard luôn do database tự tính, cố ghi tay bị chặn', function () {
    $shift = Shift::factory()->open()->create();

    expect($shift->fresh()->open_guard)->toEqual(1);

    $shift->open_guard = 99;

    expect(fn () => $shift->save())->toThrow(QueryException::class);
    expect($shift->fresh()->open_guard)->toEqual(1);
});

it('shifts.open_guard là NULL khi ca đã đóng', function () {
    $shift = Shift::factory()->closed()->create();

    expect($shift->fresh()->open_guard)->toBeNull();
});
