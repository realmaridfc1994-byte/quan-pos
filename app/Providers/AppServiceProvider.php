<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Billing\Models\Payment;
use App\Domain\Billing\Policies\PaymentPolicy;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Policies\ProductPolicy;
use App\Domain\Ordering\Models\DiningTable;
use App\Domain\Ordering\Models\Order;
use App\Domain\Ordering\Models\OrderItem;
use App\Domain\Ordering\Models\TableSession;
use App\Domain\Ordering\Policies\DiningTablePolicy;
use App\Domain\Ordering\Policies\OrderItemPolicy;
use App\Domain\Ordering\Policies\OrderPolicy;
use App\Domain\Ordering\Policies\TableSessionPolicy;
use App\Domain\Staffing\Enums\UserRole;
use App\Domain\Staffing\Models\Shift;
use App\Domain\Staffing\Models\User;
use App\Domain\Staffing\Policies\ShiftPolicy;
use App\Domain\Staffing\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
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

        Gate::define(
            'view-revenue-report',
            fn (User $user): bool => in_array($user->role, [UserRole::Owner, UserRole::Cashier], true)
        );

        Gate::define(
            'view-cost-profit',
            fn (User $user): bool => $user->role === UserRole::Owner
        );
    }
}
