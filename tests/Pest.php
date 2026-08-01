<?php

use App\Domain\Staffing\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)->in('Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

/*
|--------------------------------------------------------------------------
| Xác thực trong test — Auth::forgetGuards() là bắt buộc
|--------------------------------------------------------------------------
|
| Guard "sanctum" (RequestGuard) cache người dùng đã xác thực của LẦN GỌI
| ĐẦU TIÊN trong cùng một tiến trình test. Nếu một test xác thực làm người A
| rồi làm người B mà không gọi Auth::forgetGuards() giữa hai lần, lần gọi
| thứ hai vẫn "qua" dưới danh nghĩa người A — test có thể xanh mà không
| kiểm được gì (gỡ hẳn Policy đi vẫn xanh). Hai helper dưới đây là nơi DUY
| NHẤT gọi Auth::forgetGuards() — mọi test xác thực nhiều người dùng phải
| đi qua đây, không tự rải rác Auth::forgetGuards() ở từng file.
| Xem tests/Feature/Support/AuthGuardIsolationTest.php để biết vì sao.
|
*/

/** Đăng nhập kiểu session — dùng cho test panel Filament/Livewire. */
function actingAsUser(User $user): User
{
    Auth::forgetGuards();
    test()->actingAs($user);

    return $user;
}

/**
 * Sinh header Authorization: Bearer cho một người dùng — cách xác thực
 * chính của API dự án này (Sanctum token), khác actingAsUser() ở trên.
 *
 * @return array<string, string>
 */
function authHeaderFor(User $user): array
{
    Auth::forgetGuards();

    return ['Authorization' => 'Bearer '.$user->createToken('pos-app')->plainTextToken];
}
