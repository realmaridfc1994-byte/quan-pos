<?php

declare(strict_types=1);

use App\Domain\Ordering\Enums\TableSessionStatus;
use App\Domain\Ordering\Models\DiningTable;
use App\Domain\Ordering\Models\TableSession;
use App\Domain\Ordering\Models\TableSessionTable;
use App\Domain\Staffing\Models\User;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

function chuyenBan(User $user, TableSession $luot, DiningTable $tu, DiningTable $sang): TestResponse
{
    $token = $user->createToken('pos-app')->plainTextToken;

    return test()->postJson("/api/v1/table-sessions/{$luot->id}/transfer", [
        'from_dining_table_id' => $tu->id,
        'to_dining_table_id' => $sang->id,
    ], [
        'Authorization' => "Bearer {$token}",
        'Idempotency-Key' => (string) Str::uuid(),
    ]);
}

it('B6: chuyển bàn thành công, nhả bàn cũ và chiếm bàn mới trong cùng một lần', function () {
    $staff = User::factory()->staff()->create();
    $luot = TableSession::factory()->create(['status' => TableSessionStatus::Open]);
    $banCu = TableSessionTable::factory()->for($luot)->create(['is_primary' => true]);
    $banMoi = DiningTable::factory()->create(['code' => 'B12']);

    chuyenBan($staff, $luot, $banCu->diningTable, $banMoi)
        ->assertOk()
        ->assertJsonPath('data.tables.0.code', 'B12')
        ->assertJsonPath('data.tables.0.is_primary', true);

    expect($banCu->refresh()->detached_at)->not->toBeNull();

    $conGiuBanCu = TableSessionTable::query()
        ->where('dining_table_id', $banCu->dining_table_id)
        ->whereNull('detached_at')
        ->exists();
    expect($conGiuBanCu)->toBeFalse();
});

it('giữ nguyên cờ bàn chính khi chuyển bàn phụ (không phải bàn chính)', function () {
    $staff = User::factory()->staff()->create();
    $luot = TableSession::factory()->create(['status' => TableSessionStatus::Open]);
    TableSessionTable::factory()->for($luot)->create(['is_primary' => true]);
    $banPhu = TableSessionTable::factory()->for($luot)->notPrimary()->create();
    $banMoi = DiningTable::factory()->create();

    chuyenBan($staff, $luot, $banPhu->diningTable, $banMoi)->assertOk();

    $banMoiGhiNhan = TableSessionTable::query()
        ->where('dining_table_id', $banMoi->id)
        ->whereNull('detached_at')
        ->sole();

    expect($banMoiGhiNhan->is_primary)->toBeFalse();
});

it('B1: chuyển sang bàn đang có khách thì bị chặn', function () {
    $staff = User::factory()->staff()->create();
    $luot = TableSession::factory()->create(['status' => TableSessionStatus::Open]);
    $banCu = TableSessionTable::factory()->for($luot)->create();
    $luotKhac = TableSession::factory()->create(['shift_id' => $luot->shift_id]);
    $banDaCoKhach = TableSessionTable::factory()->for($luotKhac)->create()->diningTable;

    chuyenBan($staff, $luot, $banCu->diningTable, $banDaCoKhach)
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Bàn này đang có khách.');

    expect($banCu->refresh()->detached_at)->toBeNull();
});

it('bàn cũ và bàn mới trùng nhau thì bị chặn', function () {
    $staff = User::factory()->staff()->create();
    $luot = TableSession::factory()->create(['status' => TableSessionStatus::Open]);
    $ban = TableSessionTable::factory()->for($luot)->create();

    chuyenBan($staff, $luot, $ban->diningTable, $ban->diningTable)->assertUnprocessable();
});

it('bếp không có quyền chuyển bàn', function () {
    $bep = User::factory()->kitchen()->create();
    $luot = TableSession::factory()->create(['status' => TableSessionStatus::Open]);
    $banCu = TableSessionTable::factory()->for($luot)->create();
    $banMoi = DiningTable::factory()->create();

    chuyenBan($bep, $luot, $banCu->diningTable, $banMoi)->assertForbidden();
});
