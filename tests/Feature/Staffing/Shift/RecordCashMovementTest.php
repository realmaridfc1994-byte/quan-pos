<?php

declare(strict_types=1);

use App\Domain\Staffing\Actions\RecordCashMovement;
use App\Domain\Staffing\DTO\RecordCashMovementData;
use App\Domain\Staffing\Enums\CashDirection;
use App\Domain\Staffing\Models\CashMovement;
use App\Domain\Staffing\Models\Shift;
use App\Domain\Staffing\Models\User;
use App\Exceptions\DomainException;
use App\Support\Money;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

function ghiThuChi(User $user, Shift $ca, array $payload = []): TestResponse
{
    $token = $user->createToken('pos-app')->plainTextToken;

    return test()->postJson("/api/v1/shifts/{$ca->id}/cash-movements", array_merge([
        'direction' => 'out',
        'amount' => 200_000,
        'reason' => 'mua đá',
    ], $payload), [
        'Authorization' => "Bearer {$token}",
        'Idempotency-Key' => (string) Str::uuid(),
    ]);
}

it('ghi khoản chi ra thành công', function () {
    $thuNgan = User::factory()->cashier()->create();
    $ca = Shift::factory()->open()->create(['opened_by_user_id' => $thuNgan->id]);

    ghiThuChi($thuNgan, $ca)
        ->assertCreated()
        ->assertJsonPath('data.direction', 'out')
        ->assertJsonPath('data.amount', 200_000)
        ->assertJsonPath('data.reason', 'mua đá');

    expect(CashMovement::query()->count())->toBe(1);
});

it('ghi khoản bỏ thêm vào két thành công', function () {
    $thuNgan = User::factory()->cashier()->create();
    $ca = Shift::factory()->open()->create(['opened_by_user_id' => $thuNgan->id]);

    ghiThuChi($thuNgan, $ca, ['direction' => 'in', 'amount' => 1_000_000, 'reason' => 'chủ bỏ thêm tiền lẻ'])
        ->assertCreated()
        ->assertJsonPath('data.direction', 'in');
});

it('C7: không có lý do thì bị chặn', function () {
    $thuNgan = User::factory()->cashier()->create();
    $ca = Shift::factory()->open()->create(['opened_by_user_id' => $thuNgan->id]);

    ghiThuChi($thuNgan, $ca, ['reason' => null])->assertUnprocessable();

    expect(CashMovement::query()->count())->toBe(0);
});

it('C7: lý do chỉ toàn khoảng trắng cũng bị chặn (middleware TrimStrings cắt về rỗng, rule required bắt)', function () {
    $thuNgan = User::factory()->cashier()->create();
    $ca = Shift::factory()->open()->create(['opened_by_user_id' => $thuNgan->id]);

    ghiThuChi($thuNgan, $ca, ['reason' => '   '])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'VALIDATION_ERROR');

    expect(CashMovement::query()->count())->toBe(0);
});

it('C7: gọi thẳng Action với lý do toàn khoảng trắng vẫn bị chặn (không chỉ dựa vào validate)', function () {
    $thuNgan = User::factory()->cashier()->create();
    $ca = Shift::factory()->open()->create(['opened_by_user_id' => $thuNgan->id]);

    expect(fn () => app(RecordCashMovement::class)->handle(
        new RecordCashMovementData(
            shiftId: $ca->id,
            direction: CashDirection::Out,
            amount: Money::fromInt(50_000),
            reason: '   ',
            createdByUserId: $thuNgan->id,
        )
    ))->toThrow(DomainException::class, 'Phải ghi rõ lý do thu chi.');

    expect(CashMovement::query()->count())->toBe(0);
});

it('ca đã đóng thì không ghi thu chi được nữa', function () {
    $thuNgan = User::factory()->cashier()->create();
    $ca = Shift::factory()->closed()->create(['opened_by_user_id' => $thuNgan->id]);

    ghiThuChi($thuNgan, $ca)->assertUnprocessable();
});

it('nhân viên phục vụ không có quyền ghi thu chi vặt', function () {
    $staff = User::factory()->staff()->create();
    $ca = Shift::factory()->open()->create(['opened_by_user_id' => $staff->id]);

    ghiThuChi($staff, $ca)->assertForbidden();

    expect(CashMovement::query()->count())->toBe(0);
});
