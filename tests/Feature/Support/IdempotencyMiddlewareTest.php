<?php

declare(strict_types=1);

use App\Domain\Staffing\Enums\CashDirection;
use App\Domain\Staffing\Models\CashMovement;
use App\Domain\Staffing\Models\Shift;
use App\Domain\Staffing\Models\User;
use App\Exceptions\DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;

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

it('cùng key nhưng nội dung request khác lần trước thì bị từ chối, không thay thế yêu cầu cũ', function () {
    $this->actingAs($this->user);
    $key = (string) Str::uuid();

    $this->postJson('/api/_test/idempotent-echo', ['ghi_chu' => 'lan-goi-thu-nhat'], ['Idempotency-Key' => $key])
        ->assertCreated();

    $this->postJson('/api/_test/idempotent-echo', ['ghi_chu' => 'noi-dung-khac-han'], ['Idempotency-Key' => $key])
        ->assertStatus(422)
        ->assertJsonPath('code', 'IDEMPOTENCY_PAYLOAD_MISMATCH');

    expect(CashMovement::query()->count())->toBe(1);
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
    // tự tay giữ chỗ khoá trước khi gọi request thứ hai. body_hash phải khớp
    // đúng nội dung request rỗng ([]) bên dưới, nếu không sẽ bị coi là khác nội
    // dung (IdempotencyPayloadMismatchException) thay vì đang xử lý trùng (409).
    DB::table('cache')->insert([
        'key' => cacheKeyChoIdemTest($this->user->id, $key),
        'value' => serialize(['status' => 'processing', 'body_hash' => hash('sha256', '[]')]),
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

it('ValidationException ném ra khỏi Action vẫn nhả khoá, gửi lại cùng key chạy lại được', function () {
    Route::middleware(['auth:sanctum', 'idempotent'])->post('/_test/idempotent-validation-exception', function (Request $request) {
        if (! $request->filled('hop_le')) {
            throw ValidationException::withMessages(['hop_le' => 'Thiếu trường hop_le.']);
        }

        return response()->json(['ok' => true], 201);
    });

    $this->actingAs($this->user);
    $key = (string) Str::uuid();

    $this->postJson('/_test/idempotent-validation-exception', [], ['Idempotency-Key' => $key])
        ->assertStatus(422);

    $this->postJson('/_test/idempotent-validation-exception', ['hop_le' => 1], ['Idempotency-Key' => $key])
        ->assertCreated();
});

it('DomainException ném ra khỏi Action vẫn nhả khoá, gửi lại cùng key chạy lại được', function () {
    // DomainException chỉ được bootstrap/app.php đổi thành 422 cho route bắt đầu bằng "api/"
    // (xem $request->is('api/*')) — route test phải nằm dưới /api để đúng điều kiện đó.
    Route::middleware(['auth:sanctum', 'idempotent'])->post('/api/_test/idempotent-domain-exception', function (Request $request) {
        if (! $request->filled('hop_le')) {
            throw new DomainException('Chưa mở ca. Phải mở ca trước khi thao tác.');
        }

        return response()->json(['ok' => true], 201);
    });

    $this->actingAs($this->user);
    $key = (string) Str::uuid();

    $this->postJson('/api/_test/idempotent-domain-exception', [], ['Idempotency-Key' => $key])
        ->assertStatus(422);

    $this->postJson('/api/_test/idempotent-domain-exception', ['hop_le' => 1], ['Idempotency-Key' => $key])
        ->assertCreated();
});

it('RuntimeException bất ngờ ném ra khỏi Action vẫn nhả khoá, gửi lại cùng key chạy lại được', function () {
    Route::middleware(['auth:sanctum', 'idempotent'])->post('/_test/idempotent-runtime-exception', function (Request $request) {
        if (! $request->filled('hop_le')) {
            throw new RuntimeException('Lỗi hệ thống không lường trước.');
        }

        return response()->json(['ok' => true], 201);
    });

    $this->actingAs($this->user);
    $key = (string) Str::uuid();

    // RuntimeException không nằm trong danh sách exception nghiệp vụ được ánh xạ ở
    // bootstrap/app.php nên Laravel trả về lỗi máy chủ (5xx) — điều quan trọng cần
    // kiểm ở đây là khoá vẫn được nhả, không phải mã lỗi cụ thể là gì.
    $this->postJson('/_test/idempotent-runtime-exception', [], ['Idempotency-Key' => $key])
        ->assertServerError();

    $this->postJson('/_test/idempotent-runtime-exception', ['hop_le' => 1], ['Idempotency-Key' => $key])
        ->assertCreated();
});

it('sau khi thành công, gửi lại cùng key trả lại response cũ, không tạo bản ghi thứ hai', function () {
    $this->actingAs($this->user);
    $key = (string) Str::uuid();

    $first = $this->postJson('/api/_test/idempotent-echo', [], ['Idempotency-Key' => $key])->assertCreated();
    $second = $this->postJson('/api/_test/idempotent-echo', [], ['Idempotency-Key' => $key])->assertCreated();

    expect(CashMovement::query()->count())->toBe(1)
        ->and($second->json('id'))->toBe($first->json('id'))
        ->and($second->json())->toBe($first->json());
});

it('gửi lại CÙNG nội dung sau khi lỗi nghiệp vụ vẫn chạy được, không bị 409', function () {
    // Biến trạng thái NGOÀI request (không nằm trong body) — mô phỏng đúng tình
    // huống thật: thu ngân gửi thu tiền trước khi ca được mở, bị từ chối, rồi
    // gửi lại y hệt nội dung đó sau khi ca đã mở, vẫn cùng Idempotency-Key.
    Route::middleware(['auth:sanctum', 'idempotent'])->post('/api/_test/idempotent-thu-tien', function (Request $request) {
        if (Cache::get('_test_ca_chua_mo', true)) {
            throw new DomainException('Chưa mở ca. Phải mở ca trước khi thu tiền.');
        }

        return response()->json(['so_tien' => $request->integer('so_tien')], 201);
    });

    $this->actingAs($this->user);
    $key = (string) Str::uuid();
    $body = ['so_tien' => 200_000];

    $this->postJson('/api/_test/idempotent-thu-tien', $body, ['Idempotency-Key' => $key])
        ->assertStatus(422);

    // Ca đã được mở — mô phỏng biến trạng thái ngoài request đổi giữa hai lần gửi.
    Cache::put('_test_ca_chua_mo', false);

    $this->postJson('/api/_test/idempotent-thu-tien', $body, ['Idempotency-Key' => $key])
        ->assertCreated()
        ->assertJsonPath('so_tien', 200_000);
});
