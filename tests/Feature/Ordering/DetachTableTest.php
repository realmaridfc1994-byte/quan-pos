<?php

declare(strict_types=1);

use App\Domain\Ordering\Enums\TableSessionStatus;
use App\Domain\Ordering\Models\DiningTable;
use App\Domain\Ordering\Models\TableSession;
use App\Domain\Ordering\Models\TableSessionTable;
use App\Domain\Staffing\Models\User;
use Illuminate\Testing\TestResponse;

function nhaBan(User $user, TableSession $luot, DiningTable $ban): TestResponse
{
    $token = $user->createToken('pos-app')->plainTextToken;

    return test()->deleteJson("/api/v1/table-sessions/{$luot->id}/tables/{$ban->id}", [], [
        'Authorization' => "Bearer {$token}",
    ]);
}

it('nhả một trong nhiều bàn thành công, bàn trống lại ngay', function () {
    $staff = User::factory()->staff()->create();
    $luot = TableSession::factory()->create(['status' => TableSessionStatus::Open]);
    $banChinh = TableSessionTable::factory()->for($luot)->create(['is_primary' => true]);
    $banPhu = TableSessionTable::factory()->for($luot)->notPrimary()->create();

    nhaBan($staff, $luot, $banPhu->diningTable)->assertOk();

    expect($banPhu->refresh()->detached_at)->not->toBeNull();

    $conChiem = TableSessionTable::query()
        ->where('dining_table_id', $banPhu->dining_table_id)
        ->whereNull('detached_at')
        ->exists();
    expect($conChiem)->toBeFalse();
});

it('B5: không nhả được bàn cuối cùng của lượt khách đang mở', function () {
    $staff = User::factory()->staff()->create();
    $luot = TableSession::factory()->withTable()->create(['status' => TableSessionStatus::Open]);
    $banDuyNhat = $luot->tables()->whereNull('detached_at')->sole();

    nhaBan($staff, $luot, $banDuyNhat->diningTable)
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Không nhả được bàn cuối cùng. Muốn dọn hết thì đóng lượt khách.');

    expect($banDuyNhat->refresh()->detached_at)->toBeNull();
});

it('B3: nhả đúng bàn 5 (chính) trong nhóm 5/6/7 thì bàn 6 (ghép sớm nhất còn lại) thành bàn chính mới', function () {
    $staff = User::factory()->staff()->create();
    $luot = TableSession::factory()->create(['status' => TableSessionStatus::Open]);

    $ban5 = TableSessionTable::factory()->for($luot)->create([
        'is_primary' => true,
        'attached_at' => now()->subMinutes(180), // "19:00"
    ]);
    $ban6 = TableSessionTable::factory()->for($luot)->notPrimary()->create([
        'attached_at' => now()->subMinutes(150), // "19:30"
    ]);
    $ban7 = TableSessionTable::factory()->for($luot)->notPrimary()->create([
        'attached_at' => now()->subMinutes(120), // "20:00"
    ]);

    nhaBan($staff, $luot, $ban5->diningTable)->assertOk();

    expect($ban6->refresh()->is_primary)->toBeTrue()
        ->and($ban7->refresh()->is_primary)->toBeFalse();

    $soLuongChinh = TableSessionTable::query()
        ->where('table_session_id', $luot->id)
        ->whereNull('detached_at')
        ->where('is_primary', true)
        ->count();
    expect($soLuongChinh)->toBe(1);
});

it('B3: bàn ghép lại sau khi nhả tính theo lần ghép MỚI, không phải lần ghép đầu tiên trong lịch sử', function () {
    $staff = User::factory()->staff()->create();
    $luot = TableSession::factory()->create(['status' => TableSessionStatus::Open]);

    $ban5 = TableSessionTable::factory()->for($luot)->create([
        'is_primary' => true,
        'attached_at' => now()->subMinutes(180), // "19:00"
    ]);
    $ban6 = TableSessionTable::factory()->for($luot)->notPrimary()->create([
        'attached_at' => now()->subMinutes(150), // "19:30"
    ]);
    $ban7 = TableSessionTable::factory()->for($luot)->notPrimary()->create([
        'attached_at' => now()->subMinutes(120), // "20:00"
    ]);

    // Nhả bàn 6 lúc 20:15, ghép lại bàn 6 lúc 21:00.
    $ban6->update(['detached_at' => now()->subMinutes(105)]); // "20:15"
    $ban6MoiGhep = TableSessionTable::factory()->for($luot)->notPrimary()->create([
        'dining_table_id' => $ban6->dining_table_id,
        'attached_at' => now()->subMinutes(60), // "21:00"
    ]);

    // Giờ nhả bàn 5 (bàn chính) — bàn 7 (ghép lúc 20:00) phải thành bàn chính,
    // KHÔNG phải bàn 6 — vì lần ghép HIỆN TẠI của bàn 6 (21:00, sau khi ghép
    // lại) muộn hơn lần ghép của bàn 7 (20:00), dù bàn 6 từng ghép sớm hơn
    // trong lịch sử (19:30, lần đã bị nhả).
    nhaBan($staff, $luot, $ban5->diningTable)->assertOk();

    expect($ban7->refresh()->is_primary)->toBeTrue()
        ->and($ban6MoiGhep->refresh()->is_primary)->toBeFalse();

    $soLuongChinh = TableSessionTable::query()
        ->where('table_session_id', $luot->id)
        ->whereNull('detached_at')
        ->where('is_primary', true)
        ->count();
    expect($soLuongChinh)->toBe(1);
});

it('nhả bàn không thuộc lượt khách đang chọn thì bị chặn', function () {
    $staff = User::factory()->staff()->create();
    $luot = TableSession::factory()->withTable()->create(['status' => TableSessionStatus::Open]);
    $banKhongLienQuan = DiningTable::factory()->create();

    nhaBan($staff, $luot, $banKhongLienQuan)->assertUnprocessable();
});

it('bếp không có quyền nhả bàn', function () {
    $bep = User::factory()->kitchen()->create();
    $luot = TableSession::factory()->create(['status' => TableSessionStatus::Open]);
    $banChinh = TableSessionTable::factory()->for($luot)->create();
    TableSessionTable::factory()->for($luot)->notPrimary()->create();

    nhaBan($bep, $luot, $banChinh->diningTable)->assertForbidden();
});
