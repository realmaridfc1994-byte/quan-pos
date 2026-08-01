<?php

declare(strict_types=1);

use App\Domain\Ordering\Actions\RecalculateSessionSubtotal;
use App\Domain\Ordering\Enums\TableSessionStatus;
use App\Domain\Ordering\Models\Order;
use App\Domain\Ordering\Models\OrderItem;
use App\Domain\Ordering\Models\TableSession;
use App\Domain\Ordering\Models\TableSessionTable;
use App\Domain\Staffing\Models\User;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

function dongLuotKhach(User $user, TableSession $luot): TestResponse
{
    $token = $user->createToken('pos-app')->plainTextToken;

    return test()->postJson("/api/v1/table-sessions/{$luot->id}/close", [], [
        'Authorization' => "Bearer {$token}",
        'Idempotency-Key' => (string) Str::uuid(),
    ]);
}

it('B4: đóng lượt khách thì nhả hết mọi bàn, bàn trống lại ngay', function () {
    $staff = User::factory()->staff()->create();
    $luot = TableSession::factory()->create(['status' => TableSessionStatus::Open]);
    $banChinh = TableSessionTable::factory()->for($luot)->create(['is_primary' => true]);
    $banPhu = TableSessionTable::factory()->for($luot)->notPrimary()->create();

    dongLuotKhach($staff, $luot)
        ->assertOk()
        ->assertJsonPath('data.status', 'closed')
        ->assertJsonPath('data.tables', []);

    expect($banChinh->refresh()->detached_at)->not->toBeNull()
        ->and($banPhu->refresh()->detached_at)->not->toBeNull();

    expect(TableSessionTable::query()->whereNull('detached_at')->count())->toBe(0);
});

it('T6: còn món chưa thu tiền thì không đóng lượt khách được', function () {
    $staff = User::factory()->staff()->create();
    $luot = TableSession::factory()->withTable()->create(['status' => TableSessionStatus::Open]);
    $order = Order::factory()->for($luot, 'tableSession')->create();
    OrderItem::factory()->for($order)->ordered()->create();
    app(RecalculateSessionSubtotal::class)->handle($luot);

    dongLuotKhach($staff, $luot)
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Bàn chưa thu đủ tiền, không đóng được.');

    expect($luot->refresh()->status)->toBe(TableSessionStatus::Open);
});

it('T6: món đã phục vụ nhưng chưa thu tiền vẫn chặn đóng lượt khách', function () {
    $staff = User::factory()->staff()->create();
    $luot = TableSession::factory()->withTable()->create(['status' => TableSessionStatus::Open]);
    $order = Order::factory()->for($luot, 'tableSession')->create();
    OrderItem::factory()->for($order)->served()->create();
    app(RecalculateSessionSubtotal::class)->handle($luot);

    dongLuotKhach($staff, $luot)->assertUnprocessable();
});

it('món đã huỷ hết thì đóng lượt khách được bình thường', function () {
    $staff = User::factory()->staff()->create();
    $luot = TableSession::factory()->withTable()->create(['status' => TableSessionStatus::Open]);
    $order = Order::factory()->for($luot, 'tableSession')->create();
    OrderItem::factory()->for($order)->cancelled()->create();

    dongLuotKhach($staff, $luot)->assertOk();

    expect($luot->refresh()->status)->toBe(TableSessionStatus::Closed);
});

it('lượt khách đã đóng rồi thì không đóng lại được', function () {
    $staff = User::factory()->staff()->create();
    $luot = TableSession::factory()->closed()->create();

    dongLuotKhach($staff, $luot)->assertUnprocessable();
});

it('bếp không có quyền đóng lượt khách', function () {
    $bep = User::factory()->kitchen()->create();
    $luot = TableSession::factory()->withTable()->create(['status' => TableSessionStatus::Open]);

    dongLuotKhach($bep, $luot)->assertForbidden();
});
