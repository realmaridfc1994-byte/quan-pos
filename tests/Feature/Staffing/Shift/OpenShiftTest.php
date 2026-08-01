<?php

declare(strict_types=1);

use App\Domain\Staffing\Enums\ShiftStatus;
use App\Domain\Staffing\Models\Shift;
use App\Domain\Staffing\Models\User;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

function moCa(User $user, array $payload = []): TestResponse
{
    $token = $user->createToken('pos-app')->plainTextToken;

    return test()->postJson('/api/v1/shifts/open', array_merge([
        'opening_cash' => 500_000,
    ], $payload), [
        'Authorization' => "Bearer {$token}",
        'Idempotency-Key' => (string) Str::uuid(),
    ]);
}

it('thu ngân mở ca thành công, mã ca đúng định dạng CA-yyyymmdd-NN', function () {
    $thuNgan = User::factory()->cashier()->create();

    moCa($thuNgan)
        ->assertCreated()
        ->assertJsonPath('data.opening_cash', 500_000)
        ->assertJsonPath('data.status', 'open');

    $ca = Shift::query()->sole();
    expect($ca->code)->toStartWith('CA-'.now()->format('Ymd').'-')
        ->and($ca->opened_by_user_id)->toBe($thuNgan->id)
        ->and($ca->status)->toBe(ShiftStatus::Open);
});

it('C1: đã có ca mở thì không mở được ca thứ hai', function () {
    $thuNgan = User::factory()->cashier()->create();
    Shift::factory()->open()->create(['opened_by_user_id' => $thuNgan->id]);

    moCa($thuNgan)
        ->assertUnprocessable()
        ->assertJsonPath('code', 'DOMAIN_ERROR');

    expect(Shift::query()->count())->toBe(1);
});

it('nhân viên phục vụ mở ca được — quán ít người, ai mở cửa buổi đó thì mở ca đó', function () {
    $staff = User::factory()->staff()->create();

    moCa($staff)
        ->assertCreated()
        ->assertJsonPath('data.status', 'open');

    expect(Shift::query()->count())->toBe(1);
});

it('bếp không có quyền mở ca', function () {
    $bep = User::factory()->kitchen()->create();

    moCa($bep)->assertForbidden();
});

it('không nhập tiền đầu ca thì bị chặn', function () {
    $thuNgan = User::factory()->cashier()->create();

    moCa($thuNgan, ['opening_cash' => null])->assertUnprocessable();

    expect(Shift::query()->count())->toBe(0);
});
