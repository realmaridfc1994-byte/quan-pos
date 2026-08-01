<?php

declare(strict_types=1);

use App\Domain\Ordering\Enums\TableSessionStatus;
use App\Domain\Ordering\Models\TableSession;
use App\Domain\Ordering\Models\TableSessionTable;
use App\Domain\Staffing\Models\User;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

function huyLuotKhach(User $user, TableSession $luot, array $payload): TestResponse
{
    return test()->postJson(
        "/api/v1/table-sessions/{$luot->id}/void",
        $payload,
        array_merge(authHeaderFor($user), ['Idempotency-Key' => (string) Str::uuid()])
    );
}

it('H1+H2: huỷ lượt khách — không xoá dòng, ghi đủ ai/lúc nào/vì sao', function () {
    $owner = User::factory()->owner()->create();
    $luot = TableSession::factory()->withTable()->create(['status' => TableSessionStatus::Open]);

    huyLuotKhach($owner, $luot, ['reason' => 'Mở nhầm bàn'])
        ->assertOk()
        ->assertJsonPath('data.status', 'void')
        ->assertJsonPath('data.void_reason', 'Mở nhầm bàn');

    $luot->refresh();
    expect($luot->status)->toBe(TableSessionStatus::Void)
        ->and($luot->void_reason)->toBe('Mở nhầm bàn')
        ->and($luot->voided_by_user_id)->toBe($owner->id)
        ->and($luot->voided_at)->not->toBeNull();

    expect(TableSession::query()->count())->toBe(1); // không dòng nào bị xoá
});

it('B4: huỷ lượt khách thì nhả hết bàn ngay', function () {
    $owner = User::factory()->owner()->create();
    $luot = TableSession::factory()->withTable()->create(['status' => TableSessionStatus::Open]);

    huyLuotKhach($owner, $luot, ['reason' => 'Khách bỏ về'])->assertOk();

    expect(TableSessionTable::query()->where('table_session_id', $luot->id)->whereNull('detached_at')->count())->toBe(0);
});

it('H2: không ghi lý do thì bị chặn', function () {
    $owner = User::factory()->owner()->create();
    $luot = TableSession::factory()->withTable()->create(['status' => TableSessionStatus::Open]);

    huyLuotKhach($owner, $luot, ['reason' => ''])->assertUnprocessable();

    expect($luot->refresh()->status)->toBe(TableSessionStatus::Open);
});

it('H6: không huỷ được lượt khách đã thu tiền', function () {
    $owner = User::factory()->owner()->create();
    $luot = TableSession::factory()->withTable()->create([
        'status' => TableSessionStatus::Billing,
        'subtotal_amount' => 100_000,
        'total_amount' => 100_000,
        'paid_amount' => 100_000,
    ]);

    huyLuotKhach($owner, $luot, ['reason' => 'Đổi ý'])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Không huỷ được lượt khách đã thu tiền. Phải huỷ phiếu thu trước.');

    expect($luot->refresh()->status)->toBe(TableSessionStatus::Billing);
});

it('lượt khách đã đóng rồi thì không huỷ được nữa', function () {
    $owner = User::factory()->owner()->create();
    $luot = TableSession::factory()->closed()->create();

    huyLuotKhach($owner, $luot, ['reason' => 'Test'])->assertUnprocessable();
});

it('lượt khách đã huỷ rồi thì không huỷ lại được', function () {
    $owner = User::factory()->owner()->create();
    $luot = TableSession::factory()->create([
        'status' => TableSessionStatus::Void,
        'voided_by_user_id' => $owner->id,
        'voided_at' => now(),
        'void_reason' => 'Đã huỷ trước đó',
    ]);

    huyLuotKhach($owner, $luot, ['reason' => 'Huỷ lần nữa'])->assertUnprocessable();
});

it('nhân viên phục vụ không có quyền huỷ lượt khách', function () {
    $staff = User::factory()->staff()->create();
    $luot = TableSession::factory()->withTable()->create(['status' => TableSessionStatus::Open]);

    huyLuotKhach($staff, $luot, ['reason' => 'Test'])->assertForbidden();
});
