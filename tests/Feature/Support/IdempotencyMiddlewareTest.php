<?php

declare(strict_types=1);

use App\Domain\Staffing\Enums\CashDirection;
use App\Domain\Staffing\Models\CashMovement;
use App\Domain\Staffing\Models\Shift;
use App\Domain\Staffing\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/**
 * Chưa có endpoint nghiệp vụ thật nào gắn middleware `idempotent`, nên test này
 * tự đăng ký một route tạm chỉ tồn tại trong lúc chạy test, mô phỏng một thao tác
 * "ghi tiền" thật (tạo một CashMovement) để chứng minh không bị tạo trùng bản ghi.
 */
beforeEach(function () {
    Route::middleware(['auth:sanctum', 'idempotent'])->post('/api/_test/idempotent-echo', function (Request $request) {
        $movement = CashMovement::query()->create([
            'shift_id' => $this->shift->id,
            'direction' => CashDirection::In,
            'amount' => 10_000,
            'reason' => 'kiểm tra idempotency',
            'created_by_user_id' => $request->user()->id,
            'occurred_at' => now(),
        ]);

        return response()->json(['id' => $movement->id], 201);
    });

    Route::middleware(['auth:sanctum', 'idempotent'])->get('/_test/idempotent-noop', fn () => response()->json(['ok' => true]));

    $this->user = User::factory()->create();
    $this->shift = Shift::factory()->open()->create(['opened_by_user_id' => $this->user->id]);
});

/**
 * Phải khớp đúng công thức cacheKeyFor() trong EnsureIdempotencyKey::class,
 * cộng thêm tiền tố cache.prefix mà Laravel tự gắn vào mọi key khi lưu xuống store.
 */
function cacheKeyChoIdemTest(int $userId, string $idempotencyKey): string
{
    return config('cache.prefix').'idem:'.hash('sha256', $idempotencyKey.'|'.$userId.'|POST|api/_test/idempotent-echo');
}

it('gửi cùng một Idempotency-Key hai lần chỉ tạo một bản ghi', function () {
    $this->actingAs($this->user);
    $key = (string) Str::uuid();

    $first = $this->postJson('/api/_test/idempotent-echo', [], ['Idempotency-Key' => $key])->assertCreated();
    $second = $this->postJson('/api/_test/idempotent-echo', [], ['Idempotency-Key' => $key])->assertCreated();

    expect(CashMovement::query()->count())->toBe(1)
        ->and($second->json('id'))->toBe($first->json('id'));
});

it('gửi hai Idempotency-Key khác nhau tạo hai bản ghi', function () {
    $this->actingAs($this->user);

    $this->postJson('/api/_test/idempotent-echo', [], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();
    $this->postJson('/api/_test/idempotent-echo', [], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();

    expect(CashMovement::query()->count())->toBe(2);
});

it('key đã hết hạn thì gửi lại tạo bản ghi mới', function () {
    $this->actingAs($this->user);
    $key = (string) Str::uuid();

    $this->postJson('/api/_test/idempotent-echo', [], ['Idempotency-Key' => $key])->assertCreated();
    expect(CashMovement::query()->count())->toBe(1);

    // Mô phỏng đã qua 24 giờ: chỉnh thẳng cột expiration trong bảng cache về quá khứ.
    DB::table('cache')->where('key', cacheKeyChoIdemTest($this->user->id, $key))
        ->update(['expiration' => now()->subDay()->getTimestamp()]);

    $this->postJson('/api/_test/idempotent-echo', [], ['Idempotency-Key' => $key])->assertCreated();

    expect(CashMovement::query()->count())->toBe(2);
});

it('hai request cùng key gửi khi request trước đang xử lý thì nhận 409', function () {
    $this->actingAs($this->user);
    $key = (string) Str::uuid();

    // Mô phỏng request đầu tiên đang xử lý dở (chưa kịp ghi 'completed'):
    // tự tay giữ chỗ khoá trước khi gọi request thứ hai.
    DB::table('cache')->insert([
        'key' => cacheKeyChoIdemTest($this->user->id, $key),
        'value' => serialize(['status' => 'processing']),
        'expiration' => now()->addHours(24)->getTimestamp(),
    ]);

    $this->postJson('/api/_test/idempotent-echo', [], ['Idempotency-Key' => $key])
        ->assertStatus(409)
        ->assertJsonPath('code', 'IDEMPOTENCY_CONFLICT');

    expect(CashMovement::query()->count())->toBe(0);
});

it('thiếu header Idempotency-Key trên route bắt buộc thì trả về 400', function () {
    $this->actingAs($this->user);

    $this->postJson('/api/_test/idempotent-echo', [])
        ->assertStatus(400)
        ->assertJsonPath('code', 'IDEMPOTENCY_KEY_REQUIRED');

    expect(CashMovement::query()->count())->toBe(0);
});

it('route GET không bị ảnh hưởng bởi middleware idempotent dù không có header', function () {
    $this->actingAs($this->user);

    $this->getJson('/_test/idempotent-noop')->assertOk()->assertJsonPath('ok', true);
});

it('lỗi nghiệp vụ (4xx) nhả khoá ngay để gửi lại cùng key được', function () {
    Route::middleware(['auth:sanctum', 'idempotent'])->post('/_test/idempotent-loi', function (Request $request) {
        if (! $request->filled('hop_le')) {
            return response()->json(['message' => 'thiếu dữ liệu'], 422);
        }

        return response()->json(['ok' => true], 201);
    });

    $this->actingAs($this->user);
    $key = (string) Str::uuid();

    $this->postJson('/_test/idempotent-loi', [], ['Idempotency-Key' => $key])->assertStatus(422);
    $this->postJson('/_test/idempotent-loi', ['hop_le' => 1], ['Idempotency-Key' => $key])->assertCreated();
});
