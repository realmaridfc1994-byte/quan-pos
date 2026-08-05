<?php

declare(strict_types=1);

/**
 * Quyết định 05/08: KHÔNG phụ thuộc lịch chạy nền (schedule:work) để dọn sổ
 * cái chống trùng op_uuid — một cửa sổ dòng lệnh phải mở suốt đời là thứ
 * chắc chắn hỏng ở quán. Dọn NGAY TRONG luồng đồng bộ, xem
 * SyncBatch::donDepSyncAppliedOpsCu().
 */

use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Ordering\Models\DiningTable;
use App\Domain\Staffing\Models\Shift;
use App\Domain\Staffing\Models\User;
use App\Domain\Sync\Models\SyncAppliedOp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/** @param array<int, array<string, mixed>> $operations */
function guiDongBoDonDep(User $user, array $operations): TestResponse
{
    return test()->postJson('/api/v1/sync/batch', [
        'device_id' => 'pos-cleanup',
        'batch_uuid' => (string) Str::uuid(),
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

/** @return array<int, array<string, mixed>> một thao tác open_session hợp lệ, chắc chắn ra kết quả "applied". */
function motThaoTacApDung(): array
{
    $ban = DiningTable::factory()->create();

    return [[
        'op_uuid' => (string) Str::uuid(),
        'type' => 'open_session',
        'occurred_at' => now()->toIso8601String(),
        'depends_on' => [],
        'payload' => [
            'uuid' => (string) Str::uuid(),
            'dining_table_ids' => [$ban->id],
            'primary_dining_table_id' => $ban->id,
            'guest_count' => 2,
        ],
    ]];
}

it('gói đồng bộ có áp dụng thì tự dọn sync_applied_ops cũ hơn 7 ngày, giữ nguyên bản ghi còn mới', function () {
    $opCu = SyncAppliedOp::query()->create([
        'op_uuid' => (string) Str::uuid(),
        'op_type' => 'open_session',
        'device_id' => 'pos-cu',
        'result_payload' => ['server_ids' => ['table_session_id' => 1]],
        'applied_at' => now()->subDays(10),
    ]);

    $opConMoi = SyncAppliedOp::query()->create([
        'op_uuid' => (string) Str::uuid(),
        'op_type' => 'open_session',
        'device_id' => 'pos-moi',
        'result_payload' => ['server_ids' => ['table_session_id' => 2]],
        'applied_at' => now()->subDays(3),
    ]);

    $operations = motThaoTacApDung();

    guiDongBoDonDep($this->thuNgan, $operations)->assertOk();

    expect(SyncAppliedOp::query()->where('op_uuid', $opCu->op_uuid)->exists())->toBeFalse()
        ->and(SyncAppliedOp::query()->where('op_uuid', $opConMoi->op_uuid)->exists())->toBeTrue()
        ->and(SyncAppliedOp::query()->where('op_uuid', $operations[0]['op_uuid'])->exists())->toBeTrue();
});

it('lỗi khi dọn sync_applied_ops KHÔNG làm hỏng gói đồng bộ đang xử lý, chỉ ghi log', function () {
    SyncAppliedOp::query()->create([
        'op_uuid' => (string) Str::uuid(),
        'op_type' => 'open_session',
        'device_id' => 'pos-cu',
        'result_payload' => ['server_ids' => ['table_session_id' => 1]],
        'applied_at' => now()->subDays(10),
    ]);

    // Giả lập lỗi thật (VD ổ đĩa đầy, deadlock) chỉ ở đúng câu DELETE dọn sổ
    // sync_applied_ops — không đụng tới các câu lệnh khác trong cùng gói.
    DB::beforeExecuting(function (string $sql) {
        if (stripos($sql, 'delete') !== false && stripos($sql, 'sync_applied_ops') !== false) {
            throw new RuntimeException('Giả lập lỗi ổ đĩa lúc dọn sync_applied_ops.');
        }
    });

    Log::spy();

    $operations = motThaoTacApDung();

    $response = guiDongBoDonDep($this->thuNgan, $operations)->assertOk();

    expect($response->json('summary'))->toBe(['applied' => 1, 'duplicate' => 0, 'conflict' => 0, 'deferred' => 0, 'rejected' => 0])
        ->and($response->json('results.0.status'))->toBe('applied');

    Log::shouldHaveReceived('warning')
        ->once()
        ->withArgs(fn (string $message) => str_contains($message, 'Dọn sync_applied_ops'));
});
