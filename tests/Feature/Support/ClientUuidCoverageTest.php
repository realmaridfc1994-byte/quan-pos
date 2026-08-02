<?php

declare(strict_types=1);

/**
 * Phase 2 Bước 2 — quét các route POST dưới /api/v1 tạo bản ghi ở 5 bảng cần
 * định danh do máy POS sinh: table_sessions, orders, order_items,
 * order_item_options, payments.
 *
 * orders và payments đã có uuid từ Phase 1 (kiểm chứng lại ở đây cho đủ bộ,
 * không lặp lại toàn bộ test đã có ở PlaceOrderTest/RecordPaymentTest).
 */

use App\Domain\Billing\Models\Payment;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Option;
use App\Domain\Catalog\Models\OptionGroup;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Ordering\Enums\TableSessionStatus;
use App\Domain\Ordering\Models\DiningTable;
use App\Domain\Ordering\Models\Order;
use App\Domain\Ordering\Models\OrderItem;
use App\Domain\Ordering\Models\OrderItemOption;
use App\Domain\Ordering\Models\TableSession;
use App\Domain\Staffing\Models\Shift;
use App\Domain\Staffing\Models\User;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

function moBanCoverage(User $user, array $overrides = []): TestResponse
{
    $ban = DiningTable::factory()->create();

    return test()->postJson('/api/v1/table-sessions', array_merge([
        'uuid' => (string) Str::uuid(),
        'dining_table_ids' => [$ban->id],
        'primary_dining_table_id' => $ban->id,
        'guest_count' => 2,
    ], $overrides), array_merge(authHeaderFor($user), ['Idempotency-Key' => (string) Str::uuid()]));
}

function goiMonCoverage(User $user, TableSession $luot, array $overrides = []): TestResponse
{
    $category = Category::factory()->create();
    $product = Product::factory()->for($category)->create();
    $variant = ProductVariant::factory()->for($product)->create(['price' => 25_000]);

    $item = array_merge([
        'uuid' => (string) Str::uuid(),
        'product_id' => $product->id,
        'product_variant_id' => $variant->id,
        'quantity' => 1,
        'options' => [],
    ], $overrides['item'] ?? []);

    $payload = array_merge(['uuid' => (string) Str::uuid(), 'items' => [$item]], $overrides['payload'] ?? []);

    return test()->postJson(
        "/api/v1/table-sessions/{$luot->id}/orders",
        $payload,
        array_merge(authHeaderFor($user), ['Idempotency-Key' => (string) Str::uuid()])
    );
}

function thuTienCoverage(User $user, TableSession $luot, array $overrides = []): TestResponse
{
    return test()->postJson(
        "/api/v1/table-sessions/{$luot->id}/payments",
        array_merge([
            'uuid' => (string) Str::uuid(),
            'method' => 'cash',
            'amount' => 500_000,
            'tendered_amount' => 500_000,
        ], $overrides),
        array_merge(authHeaderFor($user), ['Idempotency-Key' => (string) Str::uuid()])
    );
}

// ── table_sessions ──────────────────────────────────────────────────────

it('mở lượt khách thiếu uuid thì bị chặn 422', function () {
    Shift::factory()->open()->create();
    $staff = User::factory()->staff()->create();

    moBanCoverage($staff, ['uuid' => null])->assertUnprocessable();
});

it('mở lượt khách gửi hai lần cùng uuid chỉ tạo một bản ghi table_sessions', function () {
    Shift::factory()->open()->create();
    $staff = User::factory()->staff()->create();
    $uuid = (string) Str::uuid();
    $ban = DiningTable::factory()->create();

    $payload = ['uuid' => $uuid, 'dining_table_ids' => [$ban->id], 'primary_dining_table_id' => $ban->id, 'guest_count' => 2];

    test()->postJson('/api/v1/table-sessions', $payload, array_merge(authHeaderFor($staff), ['Idempotency-Key' => (string) Str::uuid()]))->assertCreated();
    test()->postJson('/api/v1/table-sessions', $payload, array_merge(authHeaderFor($staff), ['Idempotency-Key' => (string) Str::uuid()]))->assertCreated();

    expect(TableSession::query()->where('uuid', $uuid)->count())->toBe(1);
});

// ── orders / order_items / order_item_options ──────────────────────────

it('gọi món thiếu uuid của dòng món thì bị chặn 422', function () {
    $ca = Shift::factory()->open()->create();
    $staff = User::factory()->staff()->create();
    $luot = TableSession::factory()->withTable()->create(['shift_id' => $ca->id, 'status' => TableSessionStatus::Open]);

    goiMonCoverage($staff, $luot, ['item' => ['uuid' => null]])->assertUnprocessable();
});

it('gọi món thiếu uuid của tuỳ chọn đã chọn thì bị chặn 422', function () {
    $ca = Shift::factory()->open()->create();
    $staff = User::factory()->staff()->create();
    $luot = TableSession::factory()->withTable()->create(['shift_id' => $ca->id, 'status' => TableSessionStatus::Open]);

    $category = Category::factory()->create();
    $product = Product::factory()->for($category)->create();
    $variant = ProductVariant::factory()->for($product)->create();
    $nhom = OptionGroup::factory()->forProduct($product)->create(['min_select' => 0, 'max_select' => 1]);
    $option = Option::factory()->for($nhom, 'optionGroup')->create();

    goiMonCoverage($staff, $luot, [
        'item' => [
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'quantity' => 1,
            'options' => [['option_id' => $option->id]],
        ],
    ])->assertUnprocessable();
});

it('gọi món gửi lại đúng uuid phiếu cũ thì không tạo trùng order_items/order_item_options', function () {
    $ca = Shift::factory()->open()->create();
    $staff = User::factory()->staff()->create();
    $luot = TableSession::factory()->withTable()->create(['shift_id' => $ca->id, 'status' => TableSessionStatus::Open]);

    $category = Category::factory()->create();
    $product = Product::factory()->for($category)->create();
    $variant = ProductVariant::factory()->for($product)->create();
    $nhom = OptionGroup::factory()->forProduct($product)->create(['min_select' => 0, 'max_select' => 1]);
    $option = Option::factory()->for($nhom, 'optionGroup')->create();

    $payload = [
        'uuid' => (string) Str::uuid(),
        'items' => [[
            'uuid' => (string) Str::uuid(),
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'quantity' => 1,
            'options' => [['option_id' => $option->id, 'uuid' => (string) Str::uuid()]],
        ]],
    ];

    goiMonCoverage($staff, $luot, ['payload' => $payload])->assertCreated();
    goiMonCoverage($staff, $luot, ['payload' => $payload])->assertCreated();

    expect(Order::query()->where('uuid', $payload['uuid'])->count())->toBe(1)
        ->and(OrderItem::query()->where('uuid', $payload['items'][0]['uuid'])->count())->toBe(1)
        ->and(OrderItemOption::query()->where('uuid', $payload['items'][0]['options'][0]['uuid'])->count())->toBe(1);
});

// ── payments ─────────────────────────────────────────────────────────

it('thu tiền thiếu uuid thì bị chặn 422', function () {
    $ca = Shift::factory()->open()->create();
    $thuNgan = User::factory()->cashier()->create();
    $luot = TableSession::factory()->for($ca, 'shift')->withTable()->create([
        'status' => TableSessionStatus::Billing,
        'subtotal_amount' => 500_000,
        'total_amount' => 500_000,
    ]);

    thuTienCoverage($thuNgan, $luot, ['uuid' => null])->assertUnprocessable();
});

it('thu tiền gửi hai lần cùng uuid chỉ tạo một phiếu thu', function () {
    $ca = Shift::factory()->open()->create();
    $thuNgan = User::factory()->cashier()->create();
    $luot = TableSession::factory()->for($ca, 'shift')->withTable()->create([
        'status' => TableSessionStatus::Billing,
        'subtotal_amount' => 500_000,
        'total_amount' => 500_000,
    ]);

    $uuid = (string) Str::uuid();
    thuTienCoverage($thuNgan, $luot, ['uuid' => $uuid])->assertCreated();
    thuTienCoverage($thuNgan, $luot, ['uuid' => $uuid])->assertSuccessful();

    expect(Payment::query()->where('uuid', $uuid)->count())->toBe(1);
});
