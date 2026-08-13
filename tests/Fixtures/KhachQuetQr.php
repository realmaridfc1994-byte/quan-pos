<?php

declare(strict_types=1);

/**
 * Việc lặp lại trong các test về khách quét mã QR — Phase 4.
 *
 * Đặt ở `tests/Fixtures/` (ngoài hai bộ test mà phpunit.xml quét) và được
 * `require_once` từ các file test cần dùng, để chạy lẻ MỘT file test vẫn có đủ
 * hàm.
 */

use App\Domain\Ordering\Enums\TableSessionStatus;
use App\Domain\Ordering\Models\DiningTable;
use App\Domain\Ordering\Models\TableSession;
use App\Domain\Staffing\Enums\ShiftStatus;
use App\Domain\Staffing\Models\Shift;
use App\Domain\Staffing\Models\User;
use Illuminate\Testing\TestResponse;

/**
 * Một bàn đang có khách ngồi thật.
 *
 * @return array{0: DiningTable, 1: TableSession}
 */
function banDangCoKhach(): array
{
    $ban = DiningTable::factory()->create(['name' => 'Bàn 3', 'area' => 'Sân']);

    // Dùng lại ca đang mở nếu có: quán chỉ được mở MỘT ca một lúc
    // (uq_shifts_only_one_open), nên gọi hàm này hai lần trong một test mà
    // mỗi lần đẻ một ca mới là đâm vào đúng chốt chặn đó.
    $ca = Shift::query()->where('status', ShiftStatus::Open)->first()
        ?? Shift::factory()->open()->create();

    $session = TableSession::factory()->create([
        'status' => TableSessionStatus::Open,
        'shift_id' => $ca->id,
    ]);
    $session->tables()->create([
        'dining_table_id' => $ban->id,
        'is_primary' => true,
        'attached_at' => now(),
        'attached_by_user_id' => User::factory()->create()->id,
    ]);

    return [$ban, $session];
}

function quetMaQr(string $maBan): TestResponse
{
    return test()->postJson('/api/v1/guest/sessions', ['ma_ban' => $maBan]);
}

function xemPhien(string $token): TestResponse
{
    return test()->getJson('/api/v1/guest/session', ['X-Guest-Token' => $token]);
}

/** @return array{0: string, 1: DiningTable, 2: TableSession} */
function tokenChoBanDangCoKhach(): array
{
    [$ban, $session] = banDangCoKhach();
    $token = quetMaQr($ban->public_code)->assertCreated()->json('data.token');

    return [$token, $ban, $session];
}
