<?php

declare(strict_types=1);

/**
 * Chống trùng Ở TẦNG ĐỒNG BỘ theo op_uuid (bảng sync_applied_ops) — độc lập
 * với uuid nghiệp vụ trên từng bảng. Kịch bản thật: server áp dụng gói xong
 * nhưng wifi rớt đúng lúc trả kết quả, máy POS không nhận được nên giữ
 * trong hàng chờ và gửi lại — 5 loại thao tác không có uuid riêng
 * (attach/detach/send_to_kitchen/close_session/huỷ món toàn bộ) trước đây
 * bị Action gốc từ chối lần hai, giờ phải trả đúng "duplicate".
 */

use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Ordering\Enums\TableSessionStatus;
use App\Domain\Ordering\Events\OrderSentToKitchen;
use App\Domain\Ordering\Models\DiningTable;
use App\Domain\Ordering\Models\Order;
use App\Domain\Ordering\Models\TableSession;
use App\Domain\Staffing\Models\Shift;
use App\Domain\Staffing\Models\User;
use App\Domain\Sync\Models\SyncConflict;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/** @param array<int, array<string, mixed>> $operations */
function guiDongBoLai(User $user, array $operations, ?string $batchUuid = null): TestResponse
{
    return test()->postJson('/api/v1/sync/batch', [
        'device_id' => 'pos-idem',
        'batch_uuid' => $batchUuid ?? (string) Str::uuid(),
        'client_time' => now()->toIso8601String(),
        'operations' => $operations,
    ], authHeaderFor($user));
}

beforeEach(function () {
    $this->thuNgan = User::factory()->cashier()->create();
    $this->ca = Shift::factory()->open()->create();

    $category = Category::factory()->create();
    $this->mon = Product::factory()->for($category)->create();
    $this->bienThe = ProductVariant::factory()->for($this->mon)->create(['price' => 25_000]);
});

it('gửi cùng một gói nguyên vẹn hai lần — lần hai mọi thao tác đều duplicate, không tạo thêm gì, không rejected', function () {
    $ban = DiningTable::factory()->create();
    $luot = (string) Str::uuid();
    $opMo = (string) Str::uuid();
    $opMon = (string) Str::uuid();

    $operations = [
        [
            'op_uuid' => $opMo,
            'type' => 'open_session',
            'occurred_at' => now()->toIso8601String(),
            'depends_on' => [],
            'payload' => ['uuid' => $luot, 'dining_table_ids' => [$ban->id], 'primary_dining_table_id' => $ban->id, 'guest_count' => 2],
        ],
        [
            'op_uuid' => $opMon,
            'type' => 'place_order',
            'occurred_at' => now()->addSecond()->toIso8601String(),
            'depends_on' => [$opMo],
            'payload' => [
                'uuid' => (string) Str::uuid(),
                'table_session_uuid' => $luot,
                'items' => [[
                    'uuid' => (string) Str::uuid(),
                    'product_id' => $this->mon->id,
                    'product_variant_id' => $this->bienThe->id,
                    'quantity' => 1,
                    'note' => null,
                    'client_unit_price' => 25_000,
                    'options' => [],
                ]],
            ],
        ],
    ];

    guiDongBoLai($this->thuNgan, $operations)->assertOk();
    $soLuotTruoc = TableSession::query()->count();
    $soPhieuTruoc = Order::query()->count();

    $response = guiDongBoLai($this->thuNgan, $operations)->assertOk();

    expect($response->json('summary'))->toBe(['applied' => 0, 'duplicate' => 2, 'conflict' => 0, 'deferred' => 0, 'rejected' => 0]);
    foreach ($response->json('results') as $ketQua) {
        expect($ketQua['status'])->toBe('duplicate');
    }

    expect(TableSession::query()->count())->toBe($soLuotTruoc)
        ->and(Order::query()->count())->toBe($soPhieuTruoc);
});

it('send_to_kitchen gửi lại — duplicate, không đẩy thêm việc in nào vào hàng đợi', function () {
    $ban = DiningTable::factory()->create();
    $luot = (string) Str::uuid();
    $opMo = (string) Str::uuid();
    $opMon = (string) Str::uuid();
    $opGui = (string) Str::uuid();
    $orderUuid = (string) Str::uuid();

    $operations = [
        [
            'op_uuid' => $opMo,
            'type' => 'open_session',
            'occurred_at' => now()->toIso8601String(),
            'depends_on' => [],
            'payload' => ['uuid' => $luot, 'dining_table_ids' => [$ban->id], 'primary_dining_table_id' => $ban->id, 'guest_count' => 2],
        ],
        [
            'op_uuid' => $opMon,
            'type' => 'place_order',
            'occurred_at' => now()->addSecond()->toIso8601String(),
            'depends_on' => [$opMo],
            'payload' => [
                'uuid' => $orderUuid,
                'table_session_uuid' => $luot,
                'items' => [[
                    'uuid' => (string) Str::uuid(),
                    'product_id' => $this->mon->id,
                    'product_variant_id' => $this->bienThe->id,
                    'quantity' => 1,
                    'note' => null,
                    'client_unit_price' => 25_000,
                    'options' => [],
                ]],
            ],
        ],
        [
            'op_uuid' => $opGui,
            'type' => 'send_to_kitchen',
            'occurred_at' => now()->addSeconds(2)->toIso8601String(),
            'depends_on' => [$opMon],
            'payload' => ['order_uuid' => $orderUuid],
        ],
    ];

    guiDongBoLai($this->thuNgan, $operations)->assertOk();

    Event::fake([OrderSentToKitchen::class]);

    $response = guiDongBoLai($this->thuNgan, $operations)->assertOk();

    $ketQuaGui = collect($response->json('results'))->firstWhere('op_uuid', $opGui);
    expect($ketQuaGui['status'])->toBe('duplicate');

    Event::assertNotDispatched(OrderSentToKitchen::class);
});

it('close_session gửi lại — duplicate, lượt khách giữ nguyên trạng thái', function () {
    $ban = DiningTable::factory()->create();
    $luot = (string) Str::uuid();
    $opMo = (string) Str::uuid();
    $opDong = (string) Str::uuid();

    $operations = [
        [
            'op_uuid' => $opMo,
            'type' => 'open_session',
            'occurred_at' => now()->toIso8601String(),
            'depends_on' => [],
            'payload' => ['uuid' => $luot, 'dining_table_ids' => [$ban->id], 'primary_dining_table_id' => $ban->id, 'guest_count' => 2],
        ],
        [
            'op_uuid' => $opDong,
            'type' => 'close_session',
            'occurred_at' => now()->addSecond()->toIso8601String(),
            'depends_on' => [$opMo],
            'payload' => ['table_session_uuid' => $luot],
        ],
    ];

    guiDongBoLai($this->thuNgan, $operations)->assertOk();

    $session = TableSession::query()->where('uuid', $luot)->sole();
    expect($session->status)->toBe(TableSessionStatus::Closed);

    $response = guiDongBoLai($this->thuNgan, $operations)->assertOk();

    $ketQuaDong = collect($response->json('results'))->firstWhere('op_uuid', $opDong);
    expect($ketQuaDong['status'])->toBe('duplicate')
        ->and($session->refresh()->status)->toBe(TableSessionStatus::Closed);
});

it('gửi lại gói mà lần đầu có thao tác conflict — vẫn ra conflict với ĐÚNG conflict_id cũ, không tạo bản ghi chờ thứ hai', function () {
    $ban = DiningTable::factory()->create();

    $luotA = (string) Str::uuid();
    guiDongBoLai($this->thuNgan, [[
        'op_uuid' => (string) Str::uuid(),
        'type' => 'open_session',
        'occurred_at' => now()->toIso8601String(),
        'depends_on' => [],
        'payload' => ['uuid' => $luotA, 'dining_table_ids' => [$ban->id], 'primary_dining_table_id' => $ban->id, 'guest_count' => 4],
    ]])->assertOk();

    $opMoB = (string) Str::uuid();
    $luotB = (string) Str::uuid();
    $operationsB = [[
        'op_uuid' => $opMoB,
        'type' => 'open_session',
        'occurred_at' => now()->toIso8601String(),
        'depends_on' => [],
        'payload' => ['uuid' => $luotB, 'dining_table_ids' => [$ban->id], 'primary_dining_table_id' => $ban->id, 'guest_count' => 2],
    ]];

    $lan1 = guiDongBoLai($this->thuNgan, $operationsB)->assertOk();
    $conflictIdLan1 = $lan1->json('results.0.conflict_id');
    expect($lan1->json('results.0.status'))->toBe('conflict');

    $soConflictTruoc = SyncConflict::query()->count();

    $lan2 = guiDongBoLai($this->thuNgan, $operationsB)->assertOk();

    expect($lan2->json('results.0.status'))->toBe('conflict')
        ->and($lan2->json('results.0.conflict_id'))->toBe($conflictIdLan1)
        ->and(SyncConflict::query()->count())->toBe($soConflictTruoc);
});
