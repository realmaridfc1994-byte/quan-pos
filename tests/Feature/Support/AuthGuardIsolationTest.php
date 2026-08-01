<?php

declare(strict_types=1);

use App\Domain\Ordering\Models\DiningTable;
use App\Domain\Ordering\Models\TableSession;
use App\Domain\Staffing\Models\Shift;
use App\Domain\Staffing\Models\User;
use Illuminate\Support\Str;

/**
 * Test chống tái phát cho helper authHeaderFor() (tests/Pest.php).
 *
 * Sanctum RequestGuard cache người dùng đã xác thực của LẦN GỌI ĐẦU TIÊN
 * trong cùng một tiến trình test. Không gọi Auth::forgetGuards() giữa hai
 * lần xác thực khác người dùng thì lần gọi thứ hai vẫn "qua" dưới danh nghĩa
 * người dùng cũ — test phân quyền xanh mà không kiểm được gì (gỡ hẳn Policy
 * đi vẫn xanh, đã kiểm chứng thật khi phát hiện lỗi này ở Bước 5).
 *
 * Test này phải ĐỎ nếu ai đó gỡ Auth::forgetGuards() khỏi authHeaderFor().
 */
it('Owner mở bàn thành công, ngay sau đó Kitchen bị chặn khi gọi CÙNG endpoint', function () {
    Shift::factory()->open()->create();
    $owner = User::factory()->owner()->create();
    $kitchen = User::factory()->kitchen()->create();
    $banA = DiningTable::factory()->create();
    $banB = DiningTable::factory()->create();

    $moBan = fn (array $headers, DiningTable $ban) => test()->postJson('/api/v1/table-sessions', [
        'dining_table_ids' => [$ban->id],
        'primary_dining_table_id' => $ban->id,
        'guest_count' => 2,
    ], array_merge($headers, ['Idempotency-Key' => (string) Str::uuid()]));

    $moBan(authHeaderFor($owner), $banA)->assertCreated();

    // Nếu authHeaderFor() không gọi Auth::forgetGuards(), request này sẽ chạy
    // dưới danh nghĩa $owner (đã xác thực ở lần gọi trước) thay vì $kitchen —
    // và sẽ SAI trả về 201 thay vì 403.
    $moBan(authHeaderFor($kitchen), $banB)->assertForbidden();

    expect(TableSession::query()->count())->toBe(1);
});
