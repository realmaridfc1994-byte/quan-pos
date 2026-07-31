<?php

declare(strict_types=1);

use App\Domain\Staffing\Models\User;
use Illuminate\Testing\TestResponse;

use function Pest\Laravel\withHeader;

function pinVerify(User $nguoiGoi, int $userId, string $pin): TestResponse
{
    $token = $nguoiGoi->createToken('pos-app')->plainTextToken;

    return withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/auth/pin-verify', ['user_id' => $userId, 'pin' => $pin]);
}

it('PIN đúng của quản lý thì duyệt thành công', function () {
    $manager = User::factory()->manager()->withPin('1234')->create();
    $staff = User::factory()->staff()->create();

    pinVerify($staff, $manager->id, '1234')
        ->assertOk()
        ->assertJsonPath('data.approved', true)
        ->assertJsonPath('data.approver.id', $manager->id);
});

it('PIN sai thì không duyệt được', function () {
    $manager = User::factory()->manager()->withPin('1234')->create();
    $staff = User::factory()->staff()->create();

    pinVerify($staff, $manager->id, '9999')->assertUnprocessable();
});

it('nhân viên thường dù đúng PIN cũng không có quyền duyệt', function () {
    $staffCoPin = User::factory()->staff()->withPin('1234')->create();
    $staff = User::factory()->staff()->create();

    pinVerify($staff, $staffCoPin->id, '1234')->assertUnprocessable();
});

it('quản lý chưa thiết lập PIN thì không duyệt được', function () {
    $manager = User::factory()->manager()->create();
    $staff = User::factory()->staff()->create();

    pinVerify($staff, $manager->id, '1234')->assertUnprocessable();
});
