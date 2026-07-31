<?php

declare(strict_types=1);

use App\Domain\Staffing\Actions\VerifyApproverPin;
use App\Domain\Staffing\DTO\PinVerifyData;
use App\Domain\Staffing\Models\User;
use App\Exceptions\DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\withHeader;

function pinVerify(User $nguoiGoi, int $userId, string $pin): TestResponse
{
    $token = $nguoiGoi->createToken('pos-app')->plainTextToken;

    return withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/auth/pin-verify', ['user_id' => $userId, 'pin' => $pin]);
}

it('PIN đúng của thu ngân thì duyệt thành công', function () {
    $thuNgan = User::factory()->cashier()->withPin('1234')->create();
    $staff = User::factory()->staff()->create();

    pinVerify($staff, $thuNgan->id, '1234')
        ->assertOk()
        ->assertJsonPath('data.approved', true)
        ->assertJsonPath('data.approver.id', $thuNgan->id);
});

it('PIN sai thì không duyệt được', function () {
    $thuNgan = User::factory()->cashier()->withPin('1234')->create();
    $staff = User::factory()->staff()->create();

    pinVerify($staff, $thuNgan->id, '9999')->assertUnprocessable();
});

it('nhân viên thường dù đúng PIN cũng không có quyền duyệt', function () {
    $staffCoPin = User::factory()->staff()->withPin('1234')->create();
    $staff = User::factory()->staff()->create();

    pinVerify($staff, $staffCoPin->id, '1234')->assertUnprocessable();
});

it('thu ngân chưa thiết lập PIN thì không duyệt được', function () {
    $thuNgan = User::factory()->cashier()->create();
    $staff = User::factory()->staff()->create();

    pinVerify($staff, $thuNgan->id, '1234')->assertUnprocessable();
});

/**
 * Bốn test dưới đây gọi thẳng Action VerifyApproverPin thay vì qua HTTP, vì route
 * pin-verify còn bị giới hạn 5 lần/phút (throttle) — nếu gọi qua HTTP, request thứ 6
 * sẽ bị chặn bởi throttle (429) trước khi tới được logic khoá PIN của Action (422),
 * khiến không tách bạch được đang kiểm cái nào. Test HTTP 429 riêng ở cuối file.
 */
function thuSaiPin(User $thuNgan, User $nguoiGoi): void
{
    try {
        app(VerifyApproverPin::class)->handle(new PinVerifyData($thuNgan->id, '0000', $nguoiGoi->id));
    } catch (DomainException) {
        // Mong đợi — chỉ cần tạo ra một lần thử sai.
    }
}

it('sai PIN 5 lần liên tiếp thì lần thứ 6 bị khoá dù nhập đúng PIN', function () {
    $thuNgan = User::factory()->cashier()->withPin('1234')->create();
    $staff = User::factory()->staff()->create();

    for ($i = 0; $i < 5; $i++) {
        thuSaiPin($thuNgan, $staff);
    }

    expect(fn () => app(VerifyApproverPin::class)->handle(new PinVerifyData($thuNgan->id, '1234', $staff->id)))
        ->toThrow(DomainException::class, 'Bạn đã nhập sai mã PIN quá nhiều lần. Đợi 15 phút rồi thử lại.');
});

it('sau khi khoá hết hạn thì nhập đúng PIN lại được', function () {
    $thuNgan = User::factory()->cashier()->withPin('1234')->create();
    $staff = User::factory()->staff()->create();

    for ($i = 0; $i < 5; $i++) {
        thuSaiPin($thuNgan, $staff);
    }

    // Xác nhận đã khoá thật trước khi mô phỏng hết hạn.
    expect(fn () => app(VerifyApproverPin::class)->handle(new PinVerifyData($thuNgan->id, '1234', $staff->id)))
        ->toThrow(DomainException::class);

    // Mô phỏng đã qua 15 phút: chỉnh thẳng cột expiration trong bảng cache về quá khứ.
    DB::table('cache')
        ->where('key', config('cache.prefix').'pin-verify-khoa:'.$staff->id.':'.$thuNgan->id)
        ->update(['expiration' => now()->subMinute()->getTimestamp()]);

    $approver = app(VerifyApproverPin::class)->handle(new PinVerifyData($thuNgan->id, '1234', $staff->id));

    expect($approver->id)->toBe($thuNgan->id);
});

it('nhập đúng PIN giữa chừng thì bộ đếm sai về 0, chưa đủ 5 lần mới thì không bị khoá', function () {
    $thuNgan = User::factory()->cashier()->withPin('1234')->create();
    $staff = User::factory()->staff()->create();

    for ($i = 0; $i < 3; $i++) {
        thuSaiPin($thuNgan, $staff);
    }

    // Nhập đúng giữa chừng — bộ đếm phải về 0.
    app(VerifyApproverPin::class)->handle(new PinVerifyData($thuNgan->id, '1234', $staff->id));

    // Sai tiếp 4 lần — nếu bộ đếm không reset thì tổng đã là 7, chắc chắn bị khoá.
    // Vì đã reset về 0, 4 lần này chưa đủ ngưỡng 5 nên vẫn chưa khoá.
    for ($i = 0; $i < 4; $i++) {
        thuSaiPin($thuNgan, $staff);
    }

    $approver = app(VerifyApproverPin::class)->handle(new PinVerifyData($thuNgan->id, '1234', $staff->id));

    expect($approver->id)->toBe($thuNgan->id);
});

it('mỗi lần PIN sai đều ghi một bản ghi activity_log, không lộ giá trị PIN', function () {
    $thuNgan = User::factory()->cashier()->withPin('1234')->create();
    $staff = User::factory()->staff()->create();

    for ($i = 0; $i < 3; $i++) {
        thuSaiPin($thuNgan, $staff);
    }

    $banGhi = Activity::query()->where('log_name', 'pin-verify')->get();

    expect($banGhi)->toHaveCount(3);

    foreach ($banGhi as $ghi) {
        expect($ghi->causer_id)->toBe($staff->id)
            ->and($ghi->subject_id)->toBe($thuNgan->id)
            ->and(json_encode($ghi->properties))->not->toContain('0000');
    }
});

it('gọi pin-verify quá 5 lần trong 1 phút thì nhận HTTP 429', function () {
    $thuNgan = User::factory()->cashier()->withPin('1234')->create();
    $staff = User::factory()->staff()->create();

    // Dùng đúng PIN mỗi lần để không đụng vào bộ đếm khoá của Action — mục đích
    // của test này chỉ là kiểm tra rate limit ở tầng route, không phải tầng Action.
    for ($i = 0; $i < 5; $i++) {
        pinVerify($staff, $thuNgan->id, '1234')->assertOk();
    }

    pinVerify($staff, $thuNgan->id, '1234')
        ->assertStatus(429)
        ->assertJsonPath('code', 'TOO_MANY_ATTEMPTS');
});

it('khoá tính theo từng cặp người gọi/người bị dò: A bị khoá không ảnh hưởng B', function () {
    $thuNgan = User::factory()->cashier()->withPin('1234')->create();
    $a = User::factory()->staff()->create();
    $b = User::factory()->staff()->create();

    for ($i = 0; $i < 5; $i++) {
        thuSaiPin($thuNgan, $a);
    }

    expect(fn () => app(VerifyApproverPin::class)->handle(new PinVerifyData($thuNgan->id, '1234', $a->id)))
        ->toThrow(DomainException::class, 'Bạn đã nhập sai mã PIN quá nhiều lần. Đợi 15 phút rồi thử lại.');

    // B chưa từng nhập sai lần nào — cặp (B, thu ngân) không bị khoá.
    $approver = app(VerifyApproverPin::class)->handle(new PinVerifyData($thuNgan->id, '1234', $b->id));

    expect($approver->id)->toBe($thuNgan->id);
});

it('gửi requested_by_user_id giả trong body thì activity log vẫn ghi đúng người đang đăng nhập', function () {
    $thuNgan = User::factory()->cashier()->withPin('1234')->create();
    $staff = User::factory()->staff()->create();
    $nguoiGia = User::factory()->staff()->create();

    $token = $staff->createToken('pos-app')->plainTextToken;

    withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/auth/pin-verify', [
            'user_id' => $thuNgan->id,
            'pin' => '9999',
            'requested_by_user_id' => $nguoiGia->id,
        ])
        ->assertUnprocessable();

    $ghi = Activity::query()->where('log_name', 'pin-verify')->latest('id')->first();

    expect($ghi)->not->toBeNull()
        ->and($ghi->causer_id)->toBe($staff->id)
        ->and($ghi->causer_id)->not->toBe($nguoiGia->id);
});

it('khi một cặp bị khoá thì có thêm bản ghi activity log tên pin-verify-khoa', function () {
    $thuNgan = User::factory()->cashier()->withPin('1234')->create();
    $staff = User::factory()->staff()->create();

    for ($i = 0; $i < 5; $i++) {
        thuSaiPin($thuNgan, $staff);
    }

    $ghiKhoa = Activity::query()->where('log_name', 'pin-verify-khoa')->get();

    expect($ghiKhoa)->toHaveCount(1);
    expect($ghiKhoa->first()->causer_id)->toBe($staff->id)
        ->and($ghiKhoa->first()->subject_id)->toBe($thuNgan->id);
});
