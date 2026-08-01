<?php

declare(strict_types=1);

use App\Domain\Ordering\Models\TableSession;
use App\Domain\Staffing\Enums\CashDirection;
use App\Domain\Staffing\Enums\ShiftStatus;
use App\Domain\Staffing\Models\CashMovement;
use App\Domain\Staffing\Models\Shift;
use App\Domain\Staffing\Models\User;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

function dongCa(User $user, Shift $ca, array $payload = []): TestResponse
{
    $token = $user->createToken('pos-app')->plainTextToken;

    return test()->postJson("/api/v1/shifts/{$ca->id}/close", array_merge([
        'counted_cash' => 550_000,
    ], $payload), [
        'Authorization' => "Bearer {$token}",
        'Idempotency-Key' => (string) Str::uuid(),
    ]);
}

it('C4: expected_cash = đầu ca + thu vào − chi ra, đếm đúng thì khớp', function () {
    $thuNgan = User::factory()->cashier()->create();
    $ca = Shift::factory()->open()->create([
        'opened_by_user_id' => $thuNgan->id,
        'opening_cash' => 500_000,
    ]);
    CashMovement::factory()->create([
        'shift_id' => $ca->id,
        'direction' => CashDirection::In,
        'amount' => 100_000,
        'created_by_user_id' => $thuNgan->id,
    ]);
    CashMovement::factory()->create([
        'shift_id' => $ca->id,
        'direction' => CashDirection::Out,
        'amount' => 50_000,
        'created_by_user_id' => $thuNgan->id,
    ]);

    dongCa($thuNgan, $ca, ['counted_cash' => 550_000])
        ->assertOk()
        ->assertJsonPath('data.expected_cash', 550_000)
        ->assertJsonPath('data.counted_cash', 550_000)
        ->assertJsonPath('data.variance_text', 'Khớp');

    $ca->refresh();
    expect($ca->status)->toBe(ShiftStatus::Closed)
        ->and($ca->expected_cash)->toBe(550_000);
});

it('C6: đóng ca không nhập tiền đếm thực tế thì bị chặn', function () {
    $thuNgan = User::factory()->cashier()->create();
    $ca = Shift::factory()->open()->create(['opened_by_user_id' => $thuNgan->id]);

    dongCa($thuNgan, $ca, ['counted_cash' => null])->assertUnprocessable();

    expect($ca->refresh()->status)->toBe(ShiftStatus::Open);
});

it('C3: còn lượt khách đang mở thì không đóng ca được', function () {
    $thuNgan = User::factory()->cashier()->create();
    $ca = Shift::factory()->open()->create(['opened_by_user_id' => $thuNgan->id]);
    TableSession::factory()->open()->create(['shift_id' => $ca->id]);

    dongCa($thuNgan, $ca)
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Còn lượt khách đang mở hoặc đang tính tiền. Phải tính tiền hết bàn trước khi đóng ca.');

    expect($ca->refresh()->status)->toBe(ShiftStatus::Open);
});

it('C3: lượt khách đang tính tiền (billing) cũng chặn đóng ca', function () {
    $thuNgan = User::factory()->cashier()->create();
    $ca = Shift::factory()->open()->create(['opened_by_user_id' => $thuNgan->id]);
    TableSession::factory()->billing()->create(['shift_id' => $ca->id]);

    dongCa($thuNgan, $ca)->assertUnprocessable();
});

it('lượt khách đã đóng của ca không cản trở đóng ca', function () {
    $thuNgan = User::factory()->cashier()->create();
    $ca = Shift::factory()->open()->create([
        'opened_by_user_id' => $thuNgan->id,
        'opening_cash' => 500_000,
    ]);
    TableSession::factory()->closed()->create(['shift_id' => $ca->id]);

    dongCa($thuNgan, $ca, ['counted_cash' => 500_000])->assertOk();
});

it('đóng ca khi két THIẾU tiền vẫn thành công, chênh lệch âm', function () {
    $thuNgan = User::factory()->cashier()->create();
    $ca = Shift::factory()->open()->create([
        'opened_by_user_id' => $thuNgan->id,
        'opening_cash' => 500_000,
    ]);

    dongCa($thuNgan, $ca, ['counted_cash' => 300_000])
        ->assertOk()
        ->assertJsonPath('data.expected_cash', 500_000)
        ->assertJsonPath('data.counted_cash', 300_000)
        ->assertJsonPath('data.variance_text', 'Thiếu 200.000 đ');

    expect($ca->refresh()->status)->toBe(ShiftStatus::Closed);
});

it('ca đã đóng rồi thì không đóng lại được', function () {
    $thuNgan = User::factory()->cashier()->create();
    $ca = Shift::factory()->closed()->create(['opened_by_user_id' => $thuNgan->id]);

    dongCa($thuNgan, $ca)->assertUnprocessable();
});

it('nhân viên phục vụ đóng được ca do chính mình mở', function () {
    $staff = User::factory()->staff()->create();
    $ca = Shift::factory()->open()->create([
        'opened_by_user_id' => $staff->id,
        'opening_cash' => 500_000,
    ]);

    dongCa($staff, $ca, ['counted_cash' => 500_000])->assertOk();
});

it('nhân viên phục vụ không đóng được ca của người khác', function () {
    $staff = User::factory()->staff()->create();
    $chuCa = User::factory()->cashier()->create();
    $ca = Shift::factory()->open()->create(['opened_by_user_id' => $chuCa->id]);

    dongCa($staff, $ca)->assertForbidden();
});
