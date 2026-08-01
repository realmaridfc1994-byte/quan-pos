<?php

declare(strict_types=1);

use App\Domain\Ordering\Enums\TableSessionStatus;
use App\Domain\Ordering\Models\DiningTable;
use App\Domain\Ordering\Models\TableSession;
use App\Domain\Ordering\Models\TableSessionTable;
use App\Domain\Staffing\Models\Shift;
use App\Domain\Staffing\Models\User;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

// Mọi TableSession trong file này dùng CHUNG một ca — TableSessionFactory mặc
// định tự sinh một Shift đang mở cho mỗi lượt khách, hai lượt khách độc lập sẽ
// đụng khoá uq_shifts_only_one_open nếu không khai báo chung shift_id.
beforeEach(function () {
    $this->ca = Shift::factory()->open()->create();
});

function ghepBan(User $user, TableSession $luot, DiningTable $ban): TestResponse
{
    $token = $user->createToken('pos-app')->plainTextToken;

    return test()->postJson("/api/v1/table-sessions/{$luot->id}/tables", [
        'dining_table_id' => $ban->id,
    ], [
        'Authorization' => "Bearer {$token}",
        'Idempotency-Key' => (string) Str::uuid(),
    ]);
}

it('ghép thêm bàn vào lượt khách đang mở thành công', function () {
    $staff = User::factory()->staff()->create();
    $luot = TableSession::factory()->withTable()->create(['shift_id' => $this->ca->id, 'status' => TableSessionStatus::Open]);
    $banMoi = DiningTable::factory()->create(['code' => 'B09']);

    ghepBan($staff, $luot, $banMoi)
        ->assertCreated()
        ->assertJsonPath('data.tables', fn ($tables) => count($tables) === 2);

    $duocGhep = TableSessionTable::query()
        ->where('table_session_id', $luot->id)
        ->where('dining_table_id', $banMoi->id)
        ->whereNull('detached_at')
        ->sole();

    expect($duocGhep->is_primary)->toBeFalse();
});

it('B1: ghép bàn đang có khách của lượt khác thì bị chặn', function () {
    $staff = User::factory()->staff()->create();
    $luot = TableSession::factory()->withTable()->create(['shift_id' => $this->ca->id, 'status' => TableSessionStatus::Open]);
    $luotKhac = TableSession::factory()->create(['shift_id' => $this->ca->id]);
    $banDaCoKhach = TableSessionTable::factory()->for($luotKhac)->create()->diningTable;

    ghepBan($staff, $luot, $banDaCoKhach)
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Bàn này đang có khách.');
});

it('lượt khách đã đóng thì không ghép bàn được nữa', function () {
    $staff = User::factory()->staff()->create();
    $luot = TableSession::factory()->closed()->create(['shift_id' => $this->ca->id]);
    $ban = DiningTable::factory()->create();

    ghepBan($staff, $luot, $ban)->assertUnprocessable();
});

it('bếp không có quyền ghép bàn', function () {
    $bep = User::factory()->kitchen()->create();
    $luot = TableSession::factory()->withTable()->create(['shift_id' => $this->ca->id, 'status' => TableSessionStatus::Open]);
    $ban = DiningTable::factory()->create();

    ghepBan($bep, $luot, $ban)->assertForbidden();
});
