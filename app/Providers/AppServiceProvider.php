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
use Illuminate\Database\Connection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->dungChungMotPhien();
    }

    /**
     * Dưới cấu hình MỘT QUÁN, `tenant` và kết nối mặc định là hai cái tên của
     * cùng một phiên nói chuyện với MariaDB.
     *
     * Vì sao phải viết đoạn này thay vì để Laravel tự mở kết nối thứ hai theo
     * `config/database.php`: Laravel đếm giao dịch và giữ khoá THEO TỪNG PHIÊN.
     * Hai phiên cùng trỏ vào một database vẫn là hai người khác nhau đối với
     * MariaDB — người này chưa lưu xong thì người kia không nhìn thấy, và hai
     * người có thể giành khoá của nhau trên cùng một dòng. Mở phiên thứ hai
     * ngay lúc này nghĩa là:
     *
     *   - Giao dịch mở trên kết nối mặc định KHÔNG bọc được lệnh ghi đi qua
     *     `tenant`. Hỏng giữa chừng thì một nửa nằm lại trong database, và
     *     không có gì báo lỗi. Sổ cái kho và đường thu tiền đều nằm trong
     *     vùng đó.
     *   - Bộ test mất khả năng cuộn lại: mỗi test chạy trong một giao dịch
     *     nháp mở trên ĐÚNG MỘT kết nối (kết nối mặc định), nên mọi thứ ghi
     *     qua `tenant` sẽ nằm lại thật trong database test.
     *
     * Cho dùng chung một phiên thì hành vi hôm nay không đổi một chút nào,
     * trong khi code vẫn được viết bằng đúng cái tên `tenant` mà tương lai
     * cần. Ngày hệ thống phục vụ nhiều quán thật, gỡ hàm này đi là hai kết
     * nối tách ra theo đúng `config/database.php` — và ngày đó phải xử lý hai
     * việc đã ghi sẵn trong `docs/viec-ton.md`.
     */
    private function dungChungMotPhien(): void
    {
        $this->app->make('db')->extend('tenant', function (array $config, string $ten): Connection {
            $macDinh = (string) $this->app->make('config')->get('database.default');

            if ($macDinh === $ten) {
                throw new RuntimeException(
                    "Kết nối mặc định đang được đặt là '{$ten}', tự trỏ vào chính nó. ".
                    "Kết nối mặc định phải là 'mariadb' — xem CLAUDE.md mục 2."
                );
            }

            return $this->app->make('db')->connection($macDinh);
        });
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
