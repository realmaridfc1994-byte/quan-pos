<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Billing\Models\Payment;
use App\Domain\Billing\Policies\PaymentPolicy;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Policies\ProductPolicy;
use App\Domain\Inventory\Models\Purchase;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Inventory\Models\StockTake;
use App\Domain\Inventory\Policies\PurchasePolicy;
use App\Domain\Inventory\Policies\StockMovementPolicy;
use App\Domain\Inventory\Policies\StockTakePolicy;
use App\Domain\Ordering\Models\DiningTable;
use App\Domain\Ordering\Models\Order;
use App\Domain\Ordering\Models\OrderItem;
use App\Domain\Ordering\Models\TableSession;
use App\Domain\Ordering\Policies\DiningTablePolicy;
use App\Domain\Ordering\Policies\OrderItemPolicy;
use App\Domain\Ordering\Policies\OrderPolicy;
use App\Domain\Ordering\Policies\TableSessionPolicy;
use App\Domain\Ordering\Support\GuestSessionToken;
use App\Domain\Staffing\Enums\UserRole;
use App\Domain\Staffing\Models\Shift;
use App\Domain\Staffing\Models\User;
use App\Domain\Staffing\Policies\ShiftPolicy;
use App\Domain\Staffing\Policies\UserPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(TableSession::class, TableSessionPolicy::class);
        Gate::policy(Order::class, OrderPolicy::class);
        Gate::policy(OrderItem::class, OrderItemPolicy::class);
        Gate::policy(DiningTable::class, DiningTablePolicy::class);
        Gate::policy(Payment::class, PaymentPolicy::class);
        Gate::policy(Shift::class, ShiftPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(Purchase::class, PurchasePolicy::class);
        Gate::policy(StockMovement::class, StockMovementPolicy::class);
        Gate::policy(StockTake::class, StockTakePolicy::class);

        Gate::define(
            'view-revenue-report',
            fn (User $user): bool => in_array($user->role, [UserRole::Owner, UserRole::Cashier], true)
        );

        Gate::define(
            'view-cost-profit',
            fn (User $user): bool => $user->role === UserRole::Owner
        );

        $this->chanGoiDonKenhKhach();
    }

    /**
     * Hai bộ đếm cho kênh công khai của khách quét mã QR — Phase 4.
     *
     * `guest-doi-ma`: đường đổi mã bàn lấy token, CHƯA có token nên chỉ đếm
     * được theo máy khách. 10 lần/phút đủ rộng cho khách quét lại vài lần vì
     * mạng chập chờn, đủ hẹp để chặn máy dò mã bàn — dò 22 ký tự với 10 lần
     * một phút thì hết đời cũng không trúng.
     *
     * `guest-api`: đường đã có token, đếm theo TỪNG TOKEN chứ không theo máy
     * khách. Đếm theo máy khách sẽ gộp cả bàn vào một rổ khi mọi người dùng
     * chung wifi quán — bốn người cùng bàn bấm nhanh là cả bàn bị chặn.
     */
    private function chanGoiDonKenhKhach(): void
    {
        RateLimiter::for('guest-doi-ma', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));

        RateLimiter::for('guest-api', function (Request $request) {
            $token = (string) $request->header('X-Guest-Token');

            return Limit::perMinute(60)->by(
                $token === '' ? 'guest-khong-token:'.$request->ip() : GuestSessionToken::khoaChanGoiDon($token)
            );
        });
    }
}
