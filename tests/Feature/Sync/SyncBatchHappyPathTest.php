<?php

declare(strict_types=1);

/**
 * Gói đồng bộ bình thường, không xung đột — chống trùng theo op_uuid, thứ
 * tự phụ thuộc, và giới hạn 200 thao tác/gói (docs/thiet-ke-dong-bo.md).
 */

use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Ordering\Models\DiningTable;
use App\Domain\Ordering\Models\Order;
use App\Domain\Ordering\Models\TableSession;
use App\Domain\Staffing\Models\Shift;
use App\Domain\Staffing\Models\User;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/** @param array<int, array<string, mixed>> $operations */
function guiDongBoHappy(User $user, array $operations, ?string $batchUuid = null): TestResponse
{
    return test()->postJson('/api/v1/sync/batch', [
        'device_id' => 'pos-happy',
        'batch_uuid' => $batchUuid ?? (string) Str::uuid(),
        'client_time' => now()->toIso8601String(),
        'operations' => $operations,
    ], authHeaderFor($user));
}

beforeEach(function () {
    $this->thuNgan = User::factory()->cashier()->create();
    Shift::factory()->open()->create();

    $category = Category::factory()->create();
    $this->mon = Product::factory()->for($category)->create();
    $this->bienThe = ProductVariant::factory()->for($this->mon)->create(['price' => 25_000]);
});

it('mở bàn rồi gọi món rồi gửi bếp, đúng thứ tự phụ thuộc dù gửi lộn xộn trong mảng', function () {
    $ban = DiningTable::factory()->create();
    $luot = (string) Str::uuid();
    $opMo = (string) Str::uuid();
    $opMon = (string) Str::uuid();
    $orderUuid = (string) Str::uuid();

    // Cố ý gửi place_order TRƯỚC open_session trong mảng — thuật toán xếp
    // thứ tự phải tự đưa open_session lên trước theo depends_on, không theo
    // vị trí trong mảng.
    $response = guiDongBoHappy($this->thuNgan, [
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
                    'quantity' => 2,
                    'note' => null,
                    'client_unit_price' => 25_000,
                    'options' => [],
                ]],
            ],
        ],
        [
            'op_uuid' => $opMo,
            'type' => 'open_session',
            'occurred_at' => now()->toIso8601String(),
            'depends_on' => [],
            'payload' => [
                'uuid' => $luot,
                'dining_table_ids' => [$ban->id],
                'primary_dining_table_id' => $ban->id,
                'guest_count' => 3,
            ],
        ],
    ])->assertOk();

    expect($response->json('summary'))->toBe(['applied' => 2, 'duplicate' => 0, 'conflict' => 0, 'deferred' => 0, 'rejected' => 0]);

    $ketQuaMo = collect($response->json('results'))->firstWhere('op_uuid', $opMo);
    $ketQuaMon = collect($response->json('results'))->firstWhere('op_uuid', $opMon);
    expect($ketQuaMo['status'])->toBe('applied')
        ->and($ketQuaMon['status'])->toBe('applied');

    $session = TableSession::query()->where('uuid', $luot)->sole();
    expect($session->subtotal_amount)->toBe(50_000);
    expect(Order::query()->where('uuid', $orderUuid)->where('table_session_id', $session->id)->exists())->toBeTrue();
});

it('gửi lại đúng gói cũ (cùng op_uuid) chỉ trả về duplicate, không tạo thêm gì', function () {
    $ban = DiningTable::factory()->create();
    $luot = (string) Str::uuid();
    $opMo = (string) Str::uuid();

    $operations = [[
        'op_uuid' => $opMo,
        'type' => 'open_session',
        'occurred_at' => now()->toIso8601String(),
        'depends_on' => [],
        'payload' => [
            'uuid' => $luot,
            'dining_table_ids' => [$ban->id],
            'primary_dining_table_id' => $ban->id,
            'guest_count' => 2,
        ],
    ]];

    guiDongBoHappy($this->thuNgan, $operations)->assertOk();
    $response = guiDongBoHappy($this->thuNgan, $operations)->assertOk();

    expect($response->json('results.0.status'))->toBe('duplicate')
        ->and(TableSession::query()->where('uuid', $luot)->count())->toBe(1);
});

it('gói quá 200 thao tác bị chặn ngay, không xử lý gì', function () {
    $operations = [];
    for ($i = 0; $i < 201; $i++) {
        $operations[] = [
            'op_uuid' => (string) Str::uuid(),
            'type' => 'open_session',
            'occurred_at' => now()->toIso8601String(),
            'depends_on' => [],
            'payload' => ['uuid' => (string) Str::uuid(), 'dining_table_ids' => [1], 'primary_dining_table_id' => 1, 'guest_count' => 1],
        ];
    }

    // FormRequest chặn trước (max:200) — Action tự có cùng chốt chặn phòng
    // trường hợp có nơi khác gọi thẳng Action, không qua HTTP.
    guiDongBoHappy($this->thuNgan, $operations)
        ->assertUnprocessable()
        ->assertJsonPath('code', 'VALIDATION_ERROR');
});

it('vòng lặp phụ thuộc (depends_on quay vòng) bị từ chối cả vòng, không phải va chạm', function () {
    $opA = (string) Str::uuid();
    $opB = (string) Str::uuid();

    $response = guiDongBoHappy($this->thuNgan, [
        [
            'op_uuid' => $opA,
            'type' => 'open_session',
            'occurred_at' => now()->toIso8601String(),
            'depends_on' => [$opB],
            'payload' => ['uuid' => (string) Str::uuid(), 'dining_table_ids' => [1], 'primary_dining_table_id' => 1, 'guest_count' => 1],
        ],
        [
            'op_uuid' => $opB,
            'type' => 'open_session',
            'occurred_at' => now()->toIso8601String(),
            'depends_on' => [$opA],
            'payload' => ['uuid' => (string) Str::uuid(), 'dining_table_ids' => [1], 'primary_dining_table_id' => 1, 'guest_count' => 1],
        ],
    ])->assertOk();

    expect($response->json('results.0.status'))->toBe('rejected')
        ->and($response->json('results.1.status'))->toBe('rejected');
});
