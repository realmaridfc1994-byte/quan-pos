<?php

declare(strict_types=1);

use App\Domain\Billing\Enums\PaymentMethod;
use App\Domain\Billing\Enums\PaymentStatus;
use App\Domain\Billing\Models\Payment;
use App\Domain\Ordering\Models\DiningTable;
use App\Domain\Ordering\Models\TableSession;
use App\Domain\Ordering\Models\TableSessionTable;
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

it('C3: còn bàn đang mở thì không đóng ca được, thông báo nêu đúng tên bàn', function () {
    $thuNgan = User::factory()->cashier()->create();
    $ca = Shift::factory()->open()->create(['opened_by_user_id' => $thuNgan->id]);
    $ban = DiningTable::factory()->create(['code' => 'B07']);
    $luot = TableSession::factory()->open()->create(['shift_id' => $ca->id]);
    TableSessionTable::factory()->for($luot)->for($ban, 'diningTable')->create(['is_primary' => true]);

    dongCa($thuNgan, $ca)
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Còn bàn B07 đang mở hoặc đang tính tiền. Phải tính tiền hết bàn trước khi đóng ca.');

    expect($ca->refresh()->status)->toBe(ShiftStatus::Open);
});

it('C3: lượt khách billing chưa gán bàn (đã nhả bàn cũ) thì nêu mã lượt khách thay vì tên bàn', function () {
    $thuNgan = User::factory()->cashier()->create();
    $ca = Shift::factory()->open()->create(['opened_by_user_id' => $thuNgan->id]);
    $luot = TableSession::factory()->billing()->create(['shift_id' => $ca->id, 'code' => 'PH-TEST-0001']);

    dongCa($thuNgan, $ca)
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Còn bàn lượt khách PH-TEST-0001 (chưa gán bàn) đang mở hoặc đang tính tiền. Phải tính tiền hết bàn trước khi đóng ca.');
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

it('C4: két thiếu 50.000 vẫn đóng ca được, chênh lệch âm đúng 50.000', function () {
    $thuNgan = User::factory()->cashier()->create();
    $ca = Shift::factory()->open()->create([
        'opened_by_user_id' => $thuNgan->id,
        'opening_cash' => 500_000,
    ]);

    dongCa($thuNgan, $ca, ['counted_cash' => 450_000])
        ->assertOk()
        ->assertJsonPath('data.expected_cash', 500_000)
        ->assertJsonPath('data.counted_cash', 450_000)
        ->assertJsonPath('data.variance_text', 'Thiếu 50.000 đ');

    expect($ca->refresh()->status)->toBe(ShiftStatus::Closed);
});

it('C4: két thừa 30.000 vẫn đóng ca được, chênh lệch dương đúng 30.000', function () {
    $thuNgan = User::factory()->cashier()->create();
    $ca = Shift::factory()->open()->create([
        'opened_by_user_id' => $thuNgan->id,
        'opening_cash' => 500_000,
    ]);

    dongCa($thuNgan, $ca, ['counted_cash' => 530_000])
        ->assertOk()
        ->assertJsonPath('data.expected_cash', 500_000)
        ->assertJsonPath('data.counted_cash', 530_000)
        ->assertJsonPath('data.variance_text', 'Thừa 30.000 đ');

    expect($ca->refresh()->status)->toBe(ShiftStatus::Closed);
});

it('C5: sau khi đóng ca, dữ liệu thu chi cũ có sửa thêm thì con số đã chốt vẫn không đổi', function () {
    $thuNgan = User::factory()->cashier()->create();
    $ca = Shift::factory()->open()->create([
        'opened_by_user_id' => $thuNgan->id,
        'opening_cash' => 500_000,
    ]);

    dongCa($thuNgan, $ca, ['counted_cash' => 500_000])->assertOk();

    $ca->refresh();
    $expectedTruoc = $ca->expected_cash;
    $countedTruoc = $ca->counted_cash;

    // Mô phỏng có ai đó ghi thêm một khoản thu chi vặt "trễ" cho ca đã đóng
    // (dữ liệu cũ bị sửa/bổ sung sau khi đã chốt sổ).
    CashMovement::factory()->create([
        'shift_id' => $ca->id,
        'direction' => CashDirection::In,
        'amount' => 200_000,
        'created_by_user_id' => $thuNgan->id,
    ]);

    expect($ca->refresh()->expected_cash)->toBe($expectedTruoc)
        ->and($ca->counted_cash)->toBe($countedTruoc);
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

it('C4: chi ra vượt tiền thu trong ca thì đóng ca vẫn thành công, expected_cash chốt về 0 và note ghi rõ phần vượt', function () {
    $thuNgan = User::factory()->cashier()->create();
    $ca = Shift::factory()->open()->create([
        'opened_by_user_id' => $thuNgan->id,
        'opening_cash' => 200_000,
    ]);
    CashMovement::factory()->create([
        'shift_id' => $ca->id,
        'direction' => CashDirection::Out,
        'amount' => 800_000,
        'created_by_user_id' => $thuNgan->id,
    ]);
    $luotDaDong = TableSession::factory()->closed()->create(['shift_id' => $ca->id]);
    Payment::factory()->create([
        'table_session_id' => $luotDaDong->id,
        'shift_id' => $ca->id,
        'method' => PaymentMethod::Cash,
        'status' => PaymentStatus::Completed,
        'amount' => 300_000,
        'tendered_amount' => 300_000,
        'change_amount' => 0,
    ]);

    // congVao = 200.000 + 300.000 = 500.000, truRa = 800.000 → vượt 300.000
    dongCa($thuNgan, $ca, ['counted_cash' => 0])
        ->assertOk()
        ->assertJsonPath('data.expected_cash', 0)
        ->assertJsonPath('data.counted_cash', 0)
        ->assertJsonPath('data.variance_text', 'Khớp')
        ->assertJsonPath('data.note', 'Chi ra vượt tiền thu 300.000 đ — tiền bù từ ngoài két.');

    $ca->refresh();
    expect($ca->status)->toBe(ShiftStatus::Closed)
        ->and($ca->expected_cash)->toBe(0)
        ->and($ca->counted_cash)->toBe(0);
});

it('C4: chi ra vượt tiền thu, ghi chú của thu ngân được giữ lại, phần vượt nối thêm phía sau', function () {
    $thuNgan = User::factory()->cashier()->create();
    $ca = Shift::factory()->open()->create([
        'opened_by_user_id' => $thuNgan->id,
        'opening_cash' => 0,
    ]);
    CashMovement::factory()->create([
        'shift_id' => $ca->id,
        'direction' => CashDirection::Out,
        'amount' => 100_000,
        'created_by_user_id' => $thuNgan->id,
    ]);

    dongCa($thuNgan, $ca, ['counted_cash' => 0, 'note' => 'Ca vắng khách'])
        ->assertOk()
        ->assertJsonPath('data.expected_cash', 0)
        ->assertJsonPath('data.note', 'Ca vắng khách — Chi ra vượt tiền thu 100.000 đ — tiền bù từ ngoài két.');
});

it('C4: thu nhiều hơn chi thì tính expected_cash như cũ, không bị ảnh hưởng bởi thay đổi chặn số âm', function () {
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
        ->assertJsonPath('data.note', null);

    expect($ca->refresh()->expected_cash)->toBe(550_000);
});

it('nhân viên phục vụ không đóng được ca của người khác', function () {
    $staff = User::factory()->staff()->create();
    $chuCa = User::factory()->cashier()->create();
    $ca = Shift::factory()->open()->create(['opened_by_user_id' => $chuCa->id]);

    dongCa($staff, $ca)->assertForbidden();
});
