<?php

declare(strict_types=1);

use App\Domain\Billing\Models\Payment;
use App\Domain\Catalog\Models\Product;
use App\Domain\Ordering\Models\DiningTable;
use App\Domain\Ordering\Models\Order;
use App\Domain\Ordering\Models\OrderItem;
use App\Domain\Ordering\Models\TableSession;
use App\Domain\Staffing\Models\Shift;
use App\Domain\Staffing\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Bộ test này khớp từng ô trong bảng quyền ở yêu cầu đầu bài.
 * Model dùng để check quyền KHÔNG được lưu xuống DB (new Model(...), không create())
 * vì các Policy ở đây chỉ đọc role người dùng, không đọc dữ liệu của bản ghi.
 */
it('mở bàn / gọi món: owner, cashier, staff được, kitchen không', function () {
    expect(User::factory()->owner()->make()->can('open', TableSession::class))->toBeTrue();
    expect(User::factory()->cashier()->make()->can('open', TableSession::class))->toBeTrue();
    expect(User::factory()->staff()->make()->can('open', TableSession::class))->toBeTrue();
    expect(User::factory()->kitchen()->make()->can('open', TableSession::class))->toBeFalse();

    expect(User::factory()->owner()->make()->can('create', Order::class))->toBeTrue();
    expect(User::factory()->cashier()->make()->can('create', Order::class))->toBeTrue();
    expect(User::factory()->staff()->make()->can('create', Order::class))->toBeTrue();
    expect(User::factory()->kitchen()->make()->can('create', Order::class))->toBeFalse();
});

it('hủy món đã gửi bếp: chỉ owner và cashier, staff bị chặn', function () {
    $item = new OrderItem;

    expect(User::factory()->owner()->make()->can('cancel', $item))->toBeTrue();
    expect(User::factory()->cashier()->make()->can('cancel', $item))->toBeTrue();
    expect(User::factory()->staff()->make()->can('cancel', $item))->toBeFalse();
    expect(User::factory()->kitchen()->make()->can('cancel', $item))->toBeFalse();
});

it('giảm giá: owner không giới hạn, cashier tối đa 20%, staff không được giảm', function () {
    $session = new TableSession;

    expect(User::factory()->owner()->make()->can('discount', [$session, 100]))->toBeTrue();
    expect(User::factory()->cashier()->make()->can('discount', [$session, 20]))->toBeTrue();
    expect(User::factory()->cashier()->make()->can('discount', [$session, 21]))->toBeFalse();
    expect(User::factory()->staff()->make()->can('discount', [$session, 0]))->toBeFalse();
});

it('void bill: chỉ owner và cashier, staff bị chặn khi làm việc của cashier', function () {
    $session = new TableSession;

    expect(User::factory()->owner()->make()->can('void', $session))->toBeTrue();
    expect(User::factory()->cashier()->make()->can('void', $session))->toBeTrue();
    expect(User::factory()->staff()->make()->can('void', $session))->toBeFalse();
});

it('thu tiền: owner, cashier, staff được, kitchen không', function () {
    expect(User::factory()->owner()->make()->can('create', Payment::class))->toBeTrue();
    expect(User::factory()->cashier()->make()->can('create', Payment::class))->toBeTrue();
    expect(User::factory()->staff()->make()->can('create', Payment::class))->toBeTrue();
    expect(User::factory()->kitchen()->make()->can('create', Payment::class))->toBeFalse();
});

it('đóng ca của mình thì ai mở ca đó cũng đóng được', function () {
    $staff = User::factory()->staff()->make(['id' => 1]);
    $shift = new Shift(['opened_by_user_id' => 1]);

    expect($staff->can('close', $shift))->toBeTrue();
});

it('đóng ca người khác: chỉ owner và cashier, staff bị chặn', function () {
    $owner = User::factory()->owner()->make(['id' => 10]);
    $thuNgan = User::factory()->cashier()->make(['id' => 11]);
    $staff = User::factory()->staff()->make(['id' => 12]);
    $shift = new Shift(['opened_by_user_id' => 999]);

    expect($owner->can('close', $shift))->toBeTrue();
    expect($thuNgan->can('close', $shift))->toBeTrue();
    expect($staff->can('close', $shift))->toBeFalse();
});

it('đổi trạng thái món trên KDS: owner, cashier, kitchen được, staff không', function () {
    $item = new OrderItem;

    expect(User::factory()->owner()->make()->can('updateStatus', $item))->toBeTrue();
    expect(User::factory()->cashier()->make()->can('updateStatus', $item))->toBeTrue();
    expect(User::factory()->kitchen()->make()->can('updateStatus', $item))->toBeTrue();
    expect(User::factory()->staff()->make()->can('updateStatus', $item))->toBeFalse();
});

it('xem báo cáo doanh thu: chỉ owner và cashier', function () {
    expect(Gate::forUser(User::factory()->owner()->make())->allows('view-revenue-report'))->toBeTrue();
    expect(Gate::forUser(User::factory()->cashier()->make())->allows('view-revenue-report'))->toBeTrue();
    expect(Gate::forUser(User::factory()->staff()->make())->allows('view-revenue-report'))->toBeFalse();
    expect(Gate::forUser(User::factory()->kitchen()->make())->allows('view-revenue-report'))->toBeFalse();
});

it('xem giá vốn và lợi nhuận: chỉ owner, cashier cũng bị chặn', function () {
    expect(Gate::forUser(User::factory()->owner()->make())->allows('view-cost-profit'))->toBeTrue();
    expect(Gate::forUser(User::factory()->cashier()->make())->allows('view-cost-profit'))->toBeFalse();
});

it('quản lý món, bàn, nhân viên: chỉ owner và cashier', function () {
    foreach ([Product::class, DiningTable::class, User::class] as $lop) {
        expect(User::factory()->owner()->make()->can('viewAny', $lop))->toBeTrue();
        expect(User::factory()->cashier()->make()->can('viewAny', $lop))->toBeTrue();
        expect(User::factory()->staff()->make()->can('viewAny', $lop))->toBeFalse();
        expect(User::factory()->kitchen()->make()->can('viewAny', $lop))->toBeFalse();
    }
});
