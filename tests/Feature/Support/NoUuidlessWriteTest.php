<?php

declare(strict_types=1);

/**
 * Chốt chặn cuối cùng (Phase 2 Bước 2): năm bảng cần định danh do máy POS
 * sinh — table_sessions, orders, order_items, order_item_options, payments —
 * đều có cột `uuid` NOT NULL ở tầng database (migration
 * 2026_08_04_000001_make_client_uuid_not_null). Test này không kiểm tra một
 * Action cụ thể nào, mà khẳng định thẳng: dù có ai đó sau này thêm một
 * đường ghi mới (Action, lệnh artisan, seeder...) quên truyền uuid, database
 * vẫn từ chối — không phụ thuộc vào việc lập trình viên có nhớ kiểm tra ở
 * tầng ứng dụng hay không.
 */

use App\Domain\Billing\Models\Payment;
use App\Domain\Ordering\Models\Order;
use App\Domain\Ordering\Models\OrderItem;
use App\Domain\Ordering\Models\OrderItemOption;
use App\Domain\Ordering\Models\TableSession;
use App\Domain\Staffing\Models\Shift;
use Illuminate\Database\QueryException;

it('table_sessions từ chối bản ghi thiếu uuid', function () {
    $data = TableSession::factory()->make(['uuid' => null])->toArray();

    expect(fn () => TableSession::query()->create($data))->toThrow(QueryException::class);
});

it('orders từ chối bản ghi thiếu uuid', function () {
    $data = Order::factory()->make(['uuid' => null])->toArray();

    expect(fn () => Order::query()->create($data))->toThrow(QueryException::class);
});

it('order_items từ chối bản ghi thiếu uuid', function () {
    $data = OrderItem::factory()->make(['uuid' => null])->toArray();

    expect(fn () => OrderItem::query()->create($data))->toThrow(QueryException::class);
});

it('order_item_options từ chối bản ghi thiếu uuid', function () {
    $data = OrderItemOption::factory()->make(['uuid' => null])->toArray();

    expect(fn () => OrderItemOption::query()->create($data))->toThrow(QueryException::class);
});

it('payments từ chối bản ghi thiếu uuid', function () {
    // table_session_id và shift_id không thể để PaymentFactory tự sinh mỗi cái
    // một Shift::factory() riêng — hai ca "open" cùng lúc đụng
    // uq_shifts_only_one_open. Gán chung một ca cho cả hai khoá ngoại.
    $ca = Shift::factory()->create();
    $luot = TableSession::factory()->create(['shift_id' => $ca->id]);
    $data = Payment::factory()->make(['uuid' => null, 'shift_id' => $ca->id, 'table_session_id' => $luot->id])->toArray();

    expect(fn () => Payment::query()->create($data))->toThrow(QueryException::class);
});
