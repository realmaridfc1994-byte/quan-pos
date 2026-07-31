# REVIEW-PHASE0.md — Gói review cho Opus (Bước 9, theo `docs/PHASE.md`)

> File này được Claude Code tạo theo yêu cầu chủ dự án, để chuẩn bị cho bước review
> cuối cùng trước khi đóng Phase 0. Không phải code, không đụng vào giới hạn "không viết
> code mới" của Bước 9.
>
> Lưu ý: nội dung mục 3 và 4 dựa trên việc **đọc lại code hiện có**, không phải nhật ký
> quyết định gốc của người/phiên đã viết ra chúng (auth, migration, idempotency được viết
> ở các phiên làm việc trước, không phải trong phiên tạo file này). Với phần CI/phase0:check/
> README/seeder bổ sung món — đó là việc của phiên hiện tại nên chắc chắn hơn về lý do.

---

## 1. Danh sách file đã tạo trong Phase 0, nhóm theo chức năng

### 1.1. Migration — dịch 15 bảng nghiệp vụ + hạ tầng (database/migrations/)

| File | Bảng |
|---|---|
| `2026_07_31_000001_create_users_table.php` | `users` |
| `2026_07_31_000002_create_shifts_table.php` | `shifts` |
| `2026_07_31_000003_create_cash_movements_table.php` | `cash_movements` |
| `2026_07_31_000004_create_dining_tables_table.php` | `dining_tables` |
| `2026_07_31_000005_create_table_sessions_table.php` | `table_sessions` |
| `2026_07_31_000006_create_table_session_tables_table.php` | `table_session_tables` |
| `2026_07_31_000007_create_categories_table.php` | `categories` |
| `2026_07_31_000008_create_products_table.php` | `products` |
| `2026_07_31_000009_create_product_variants_table.php` | `product_variants` |
| `2026_07_31_000010_create_option_groups_table.php` | `option_groups` |
| `2026_07_31_000011_create_options_table.php` | `options` |
| `2026_07_31_000012_create_orders_table.php` | `orders` |
| `2026_07_31_000013_create_order_items_table.php` | `order_items` |
| `2026_07_31_000014_create_order_item_options_table.php` | `order_item_options` |
| `2026_07_31_000015_create_payments_table.php` | `payments` |
| `2026_07_31_000016_create_cache_table.php` | `cache`, `cache_locks` (hạ tầng, phục vụ Idempotency + `Cache::lock`) |
| `2026_07_31_081235_create_personal_access_tokens_table.php` | `personal_access_tokens` (Sanctum) |
| `2026_07_31_081240_create_activity_log_table.php` | `activity_log` (spatie/laravel-activitylog) |
| `2026_07_31_081241_add_event_column_to_activity_log_table.php` | thêm cột `event` |
| `2026_07_31_081242_add_batch_uuid_column_to_activity_log_table.php` | thêm cột `batch_uuid` |

### 1.2. Model + Enum theo Domain (app/Domain/*/Models, */Enums)

- **Staffing**: `User`, `Shift`, `CashMovement` — enum `UserRole`, `ShiftStatus`, `CashDirection`
- **Ordering**: `DiningTable`, `TableSession`, `TableSessionTable`, `Order`, `OrderItem`, `OrderItemOption` — enum `TableSessionStatus`, `OrderStatus`, `OrderItemStatus`
- **Catalog**: `Category`, `Product`, `ProductVariant`, `OptionGroup`, `Option` — enum `Station`
- **Billing**: `Payment` — enum `PaymentMethod`, `PaymentStatus`

### 1.3. Đăng nhập, phân quyền, PIN (app/Http, app/Domain/Staffing)

- `app/Http/Controllers/Api/AuthController.php`
- `app/Domain/Staffing/Actions/AuthenticateUser.php`, `RevokeCurrentToken.php`, `VerifyManagerPin.php`
- `app/Domain/Staffing/DTO/LoginData.php`, `LogoutData.php`, `PinVerifyData.php`, `AuthenticatedSession.php`
- `app/Http/Requests/LoginRequest.php`, `PinVerifyRequest.php`
- `app/Http/Resources/UserResource.php`, `ApiResource.php`
- `app/Http/Middleware/EnsureUserIsActive.php`
- 8 Policy: `PaymentPolicy`, `ProductPolicy`, `TableSessionPolicy`, `OrderPolicy`, `OrderItemPolicy`, `DiningTablePolicy`, `ShiftPolicy`, `UserPolicy`
- `app/Providers/AppServiceProvider.php` — đăng ký toàn bộ Policy + 2 Gate riêng (`view-revenue-report`, `view-cost-profit`)
- `routes/api.php`

### 1.4. Idempotency và nền tảng dùng chung (app/Http/Middleware, app/Exceptions, app/Support)

- `app/Http/Middleware/EnsureIdempotencyKey.php`
- `app/Exceptions/IdempotencyConflictException.php`, `IdempotencyKeyRequiredException.php`, `DomainException.php`
- `app/Support/Money.php`
- `app/Support/Action.php` (base class cho Action, chưa có Action nghiệp vụ nào kế thừa ngoài auth)
- Xử lý đổi exception → JSON trong `bootstrap/app.php`

### 1.5. Dữ liệu mẫu (database/factories, database/seeders)

- 15 Factory (một cho mỗi Model nghiệp vụ) + `UserFactory`
- `database/seeders/DatabaseSeeder.php` — 4 user, 12 bàn, 8 nhóm món, 60 món (phiên hiện tại thêm 6 món để đủ 60)

### 1.6. CI và công cụ kiểm tra chất lượng

- `pint.json` — cấu hình Pint, preset `laravel`
- `.github/workflows/ci.yml` — chạy Pint → Larastan → Pest khi push/PR
- `phpstan.neon` — Larastan level 6 (có sẵn từ trước, không đổi)
- `app/Console/Commands/Phase0Check.php` — lệnh `php artisan phase0:check`

### 1.7. Test (tests/Feature, tests/Unit)

- `tests/Feature/Staffing/Auth/LoginTest.php`, `LogoutTest.php`, `PinVerifyTest.php`, `ActiveUserMiddlewareTest.php`
- `tests/Feature/Staffing/PermissionsTest.php`
- `tests/Feature/Support/IdempotencyMiddlewareTest.php`
- `tests/Feature/Database/MigrationRollbackTest.php`
- `tests/Unit/Support/MoneyTest.php`
- 43 test, 151 assertion — PASS tại thời điểm viết file này.

### 1.8. Tài liệu và cấu hình môi trường

- `README.md` (viết lại hoàn toàn trong phiên hiện tại)
- `.env.example` (đổi `CACHE_STORE` sang `database` trong phiên hiện tại)
- `docs/schema.md`, `docs/huong-dan-docker.md`, `docs/PHASE.md`, `docs/viec-ton.md` — có sẵn, không do phiên nào ghi mới ngoài việc đọc

---

## 2. Nội dung đầy đủ các file liên quan tiền và bảo mật

### 2.1. Middleware Idempotency — `app/Http/Middleware/EnsureIdempotencyKey.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\IdempotencyConflictException;
use App\Exceptions\IdempotencyKeyRequiredException;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Chống thu trùng khi máy POS bấm gửi hai lần vì mạng lag (hoặc khách bấm hai lần).
 *
 * Chỉ áp dụng cho POST/PATCH. Client phải tự sinh header Idempotency-Key trước khi
 * gửi; gửi lại đúng key đó trong 24 giờ thì:
 *  - Nếu lần trước đã xử lý xong THÀNH CÔNG (2xx): trả lại nguyên response cũ,
 *    không chạy lại logic — không tạo bản ghi thứ hai.
 *  - Nếu lần trước đang xử lý dở: trả 409, không đợi, không chạy song song.
 *  - Nếu lần trước LỖI (4xx/5xx): coi như "chưa hoàn tất", nhả khoá ngay để
 *    khách sửa dữ liệu rồi gửi lại được với cùng key.
 */
final class EnsureIdempotencyKey
{
    private const TTL_HOURS = 24;

    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->method(), ['POST', 'PATCH'], true)) {
            return $next($request);
        }

        $idempotencyKey = $request->header('Idempotency-Key');

        if (! is_string($idempotencyKey) || $idempotencyKey === '') {
            throw new IdempotencyKeyRequiredException('Thiếu header Idempotency-Key.');
        }

        $cacheKey = $this->cacheKeyFor($idempotencyKey, $request);
        $store = Cache::store('database');

        $claimed = $store->add($cacheKey, ['status' => 'processing'], now()->addHours(self::TTL_HOURS));

        if (! $claimed) {
            /** @var array{status: string, http_status?: int, headers?: array<string, string>, body?: string}|null $existing */
            $existing = $store->get($cacheKey);

            if ($existing === null || $existing['status'] === 'processing') {
                throw new IdempotencyConflictException('Yêu cầu trước đó với cùng mã đang được xử lý.');
            }

            $replay = response($existing['body'] ?? '', $existing['http_status'] ?? 200);
            foreach ($existing['headers'] ?? [] as $name => $value) {
                $replay->headers->set($name, $value);
            }

            return $replay;
        }

        $response = $next($request);

        if ($response->isSuccessful()) {
            $store->put($cacheKey, [
                'status' => 'completed',
                'http_status' => $response->getStatusCode(),
                'headers' => [
                    'Content-Type' => $response->headers->get('Content-Type', 'application/json'),
                ],
                'body' => $response->getContent(),
            ], now()->addHours(self::TTL_HOURS));
        } else {
            $store->forget($cacheKey);
        }

        return $response;
    }

    private function cacheKeyFor(string $idempotencyKey, Request $request): string
    {
        $user = $request->user();
        $userId = $user !== null ? $user->id : 'guest';

        return 'idem:'.hash('sha256', $idempotencyKey.'|'.$userId.'|'.$request->method().'|'.$request->path());
    }
}
```

Liên quan: `app/Exceptions/IdempotencyConflictException.php` → HTTP 409, `IdempotencyKeyRequiredException.php` → HTTP 400 (đổi trong `bootstrap/app.php`). Alias middleware đăng ký ở `bootstrap/app.php`: `'idempotent' => EnsureIdempotencyKey::class`.

**Lưu ý quan trọng**: middleware này đã viết xong và có test PASS, nhưng **hiện chưa được gắn vào route nào** — `routes/api.php` hiện chỉ có `login`/`logout`/`pin-verify`, chưa có endpoint tiền bạc nào (thu tiền, gọi món) để gắn `'idempotent'` vào. Sẽ cần gắn khi viết endpoint `RecordPayment`/`SubmitOrder` ở phase sau.

### 2.2. Class Money — `app/Support/Money.php`

```php
<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

/**
 * Số tiền VND, luôn là số nguyên đơn vị đồng và không bao giờ âm.
 *
 * Vì sao cần class này thay vì dùng int trần:
 *  - Chặn ngay tại chỗ việc trừ ra số âm (két âm tiền là chuyện vô nghĩa),
 *    thay vì để MySQL báo lỗi kiểu dữ liệu khó hiểu ở cuối giao dịch.
 *  - Chặn việc lỡ tay đưa float vào, làm sai một đồng trên hoá đơn.
 */
final readonly class Money
{
    private function __construct(public int $amount) {}

    public static function fromDong(int|float $amount): self
    {
        if (is_float($amount)) {
            throw new InvalidArgumentException('Số tiền phải là số nguyên đồng, không được là số thực.');
        }

        if ($amount < 0) {
            throw new InvalidArgumentException("Số tiền không được âm: {$amount}");
        }

        return new self($amount);
    }

    /** Alias của fromDong(), giữ tương thích với các mẫu code cũ dùng tên này. */
    public static function fromInt(int $amount): self
    {
        return self::fromDong($amount);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    public function add(self $other): self
    {
        return new self($this->amount + $other->amount);
    }

    /** Alias của add(). */
    public function plus(self $other): self
    {
        return $this->add($other);
    }

    public function subtract(self $other): self
    {
        if ($other->amount > $this->amount) {
            throw new InvalidArgumentException(
                "Phép trừ ra số âm: {$this->amount} - {$other->amount}"
            );
        }

        return new self($this->amount - $other->amount);
    }

    /** Alias của subtract(). */
    public function minus(self $other): self
    {
        return $this->subtract($other);
    }

    public function multiply(int $quantity): self
    {
        if ($quantity < 0) {
            throw new InvalidArgumentException("Số lượng không được âm: {$quantity}");
        }

        return new self($this->amount * $quantity);
    }

    /** Alias của multiply(). */
    public function times(int $quantity): self
    {
        return $this->multiply($quantity);
    }

    /**
     * Tính phần trăm của số tiền hiện tại, làm tròn thông thường (0.5 lên).
     * Ví dụ: 12.345đ percentage(10) = 1.235đ (vì 1.234,5 làm tròn lên).
     */
    public function percentage(int $percent): self
    {
        if ($percent < 0) {
            throw new InvalidArgumentException("Phần trăm không được âm: {$percent}");
        }

        return new self((int) round($this->amount * $percent / 100));
    }

    public function isZero(): bool
    {
        return $this->amount === 0;
    }

    public function isAtLeast(self $other): bool
    {
        return $this->amount >= $other->amount;
    }

    /** Định dạng cho người đọc: 1250000 → "1.250.000 đ" */
    public function format(): string
    {
        return number_format($this->amount, 0, ',', '.').' đ';
    }
}
```

### 2.3. Toàn bộ Policy (8 file)

**`app/Domain/Billing/Policies/PaymentPolicy.php`**
```php
<?php

declare(strict_types=1);

namespace App\Domain\Billing\Policies;

use App\Domain\Staffing\Enums\UserRole;
use App\Domain\Staffing\Models\User;

final class PaymentPolicy
{
    /** Thu tiền cho một lượt khách. */
    public function create(User $user): bool
    {
        return in_array($user->role, [UserRole::Owner, UserRole::Manager, UserRole::Staff], true);
    }
}
```

**`app/Domain/Catalog/Policies/ProductPolicy.php`**
```php
<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Policies;

use App\Domain\Staffing\Enums\UserRole;
use App\Domain\Staffing\Models\User;

final class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [UserRole::Owner, UserRole::Manager], true);
    }

    public function create(User $user): bool
    {
        return in_array($user->role, [UserRole::Owner, UserRole::Manager], true);
    }

    public function update(User $user): bool
    {
        return in_array($user->role, [UserRole::Owner, UserRole::Manager], true);
    }
}
```

**`app/Domain/Ordering/Policies/TableSessionPolicy.php`**
```php
<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Policies;

use App\Domain\Ordering\Models\TableSession;
use App\Domain\Staffing\Enums\UserRole;
use App\Domain\Staffing\Models\User;

final class TableSessionPolicy
{
    /** Mở bàn cho lượt khách mới. */
    public function open(User $user): bool
    {
        return in_array($user->role, [UserRole::Owner, UserRole::Manager, UserRole::Staff], true);
    }

    /**
     * Giảm giá trên hoá đơn.
     * Chủ quán giảm bao nhiêu cũng được, quản lý tối đa 20%, nhân viên không được giảm.
     */
    public function discount(User $user, TableSession $tableSession, int $percent): bool
    {
        return match ($user->role) {
            UserRole::Owner => true,
            UserRole::Manager => $percent <= 20,
            default => false,
        };
    }

    /** Huỷ toàn bộ hoá đơn của một lượt khách. */
    public function void(User $user, TableSession $tableSession): bool
    {
        return in_array($user->role, [UserRole::Owner, UserRole::Manager], true);
    }
}
```

**`app/Domain/Ordering/Policies/OrderPolicy.php`**
```php
<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Policies;

use App\Domain\Staffing\Enums\UserRole;
use App\Domain\Staffing\Models\User;

final class OrderPolicy
{
    /** Gọi món — gửi phiếu xuống bếp/quầy. */
    public function create(User $user): bool
    {
        return in_array($user->role, [UserRole::Owner, UserRole::Manager, UserRole::Staff], true);
    }
}
```

**`app/Domain/Ordering/Policies/OrderItemPolicy.php`**
```php
<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Policies;

use App\Domain\Staffing\Enums\UserRole;
use App\Domain\Staffing\Models\User;

final class OrderItemPolicy
{
    /**
     * Huỷ một dòng món đã gửi bếp (đã có trong CSDL).
     *
     * Món chưa gửi bếp chỉ tồn tại ở giỏ hàng phía màn hình, chưa có dòng nào để
     * phân quyền — nhân viên/quản lý/chủ quán đều huỷ được tự do ở đó.
     * Một khi đã gửi bếp (dòng đã lưu), chỉ chủ quán/quản lý được huỷ.
     * Riêng món đã phục vụ ra bàn (status=served) còn cần xác thực PIN qua
     * /api/v1/auth/pin-verify trước khi gọi hành động huỷ — đó là việc của
     * Action CancelOrderItem sau này, không nằm trong Policy này.
     */
    public function cancel(User $user): bool
    {
        return in_array($user->role, [UserRole::Owner, UserRole::Manager], true);
    }

    /** Đổi trạng thái món trên màn hình bếp/quầy (KDS). */
    public function updateStatus(User $user): bool
    {
        return in_array($user->role, [UserRole::Owner, UserRole::Manager, UserRole::Kitchen], true);
    }
}
```

**`app/Domain/Ordering/Policies/DiningTablePolicy.php`**
```php
<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Policies;

use App\Domain\Staffing\Enums\UserRole;
use App\Domain\Staffing\Models\User;

final class DiningTablePolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [UserRole::Owner, UserRole::Manager], true);
    }

    public function create(User $user): bool
    {
        return in_array($user->role, [UserRole::Owner, UserRole::Manager], true);
    }

    public function update(User $user): bool
    {
        return in_array($user->role, [UserRole::Owner, UserRole::Manager], true);
    }
}
```

**`app/Domain/Staffing/Policies/ShiftPolicy.php`**
```php
<?php

declare(strict_types=1);

namespace App\Domain\Staffing\Policies;

use App\Domain\Staffing\Enums\UserRole;
use App\Domain\Staffing\Models\Shift;
use App\Domain\Staffing\Models\User;

final class ShiftPolicy
{
    /**
     * Đóng ca. Ai cũng đóng được ca của chính mình; đóng ca của người khác
     * thì chỉ chủ quán/quản lý.
     */
    public function close(User $user, Shift $shift): bool
    {
        if ($shift->opened_by_user_id === $user->id) {
            return in_array($user->role, [UserRole::Owner, UserRole::Manager, UserRole::Staff], true);
        }

        return in_array($user->role, [UserRole::Owner, UserRole::Manager], true);
    }
}
```

**`app/Domain/Staffing/Policies/UserPolicy.php`**
```php
<?php

declare(strict_types=1);

namespace App\Domain\Staffing\Policies;

use App\Domain\Staffing\Enums\UserRole;
use App\Domain\Staffing\Models\User;

final class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [UserRole::Owner, UserRole::Manager], true);
    }

    public function create(User $user): bool
    {
        return in_array($user->role, [UserRole::Owner, UserRole::Manager], true);
    }

    public function update(User $user): bool
    {
        return in_array($user->role, [UserRole::Owner, UserRole::Manager], true);
    }
}
```

Đăng ký toàn bộ (không tự động discover) trong `app/Providers/AppServiceProvider.php::boot()`, cùng 2 Gate riêng lẻ `view-revenue-report` (Owner/Manager) và `view-cost-profit` (chỉ Owner).

### 2.4. Controller và Action xử lý auth và pin-verify

**`app/Http/Controllers/Api/AuthController.php`**
```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Staffing\Actions\AuthenticateUser;
use App\Domain\Staffing\Actions\RevokeCurrentToken;
use App\Domain\Staffing\Actions\VerifyManagerPin;
use App\Domain\Staffing\DTO\LoginData;
use App\Domain\Staffing\DTO\LogoutData;
use App\Domain\Staffing\DTO\PinVerifyData;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\PinVerifyRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AuthController extends Controller
{
    /** POST /api/v1/auth/login */
    public function login(LoginRequest $request, AuthenticateUser $action): JsonResponse
    {
        $session = $action->handle(LoginData::fromRequest($request));

        return response()->json([
            'data' => [
                'token' => $session->token,
                'user' => UserResource::make($session->user),
            ],
        ]);
    }

    /** POST /api/v1/auth/logout */
    public function logout(Request $request, RevokeCurrentToken $action): JsonResponse
    {
        $action->handle(new LogoutData($request->user()));

        return response()->json(['message' => 'Đã đăng xuất.']);
    }

    /** POST /api/v1/auth/pin-verify */
    public function pinVerify(PinVerifyRequest $request, VerifyManagerPin $action): JsonResponse
    {
        $approver = $action->handle(PinVerifyData::fromRequest($request));

        return response()->json([
            'data' => [
                'approved' => true,
                'approver' => UserResource::make($approver),
            ],
        ]);
    }
}
```

**`app/Domain/Staffing/Actions/AuthenticateUser.php`**
```php
<?php

declare(strict_types=1);

namespace App\Domain\Staffing\Actions;

use App\Domain\Staffing\DTO\AuthenticatedSession;
use App\Domain\Staffing\DTO\LoginData;
use App\Domain\Staffing\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Đăng nhập cho máy POS: số điện thoại + mật khẩu, trả về token Sanctum.
 */
final class AuthenticateUser
{
    public function handle(LoginData $data): AuthenticatedSession
    {
        $user = User::query()->where('phone', $data->phone)->first();

        // Không nói rõ "sai số điện thoại" hay "sai mật khẩu" để tránh lộ tài khoản nào tồn tại.
        if ($user === null || ! Hash::check($data->password, $user->password)) {
            throw ValidationException::withMessages([
                'phone' => 'Số điện thoại hoặc mật khẩu không đúng.',
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'phone' => 'Tài khoản đã bị vô hiệu hoá, liên hệ quản lý.',
            ]);
        }

        $token = $user->createToken('pos-app')->plainTextToken;

        return new AuthenticatedSession($user, $token);
    }
}
```

**`app/Domain/Staffing/Actions/VerifyManagerPin.php`**
```php
<?php

declare(strict_types=1);

namespace App\Domain\Staffing\Actions;

use App\Domain\Staffing\DTO\PinVerifyData;
use App\Domain\Staffing\Enums\UserRole;
use App\Domain\Staffing\Models\User;
use App\Exceptions\DomainException;
use Illuminate\Support\Facades\Hash;

/**
 * Xác thực mã PIN của chủ quán/quản lý để duyệt một hành động nhạy cảm
 * (ví dụ: hủy món đã phục vụ ra bàn — xem bất biến H5 ở docs/schema.md).
 */
final class VerifyManagerPin
{
    public function handle(PinVerifyData $data): User
    {
        $approver = User::query()->find($data->userId);

        if ($approver === null || ! $approver->is_active) {
            throw new DomainException('Người này không có quyền duyệt.');
        }

        if (! in_array($approver->role, [UserRole::Owner, UserRole::Manager], true)) {
            throw new DomainException('Người này không có quyền duyệt.');
        }

        if ($approver->pin_code === null) {
            throw new DomainException('Người này chưa thiết lập mã PIN.');
        }

        if (! Hash::check($data->pin, $approver->pin_code)) {
            throw new DomainException('Mã PIN không đúng.');
        }

        return $approver;
    }
}
```

**`app/Domain/Staffing/Actions/RevokeCurrentToken.php`**
```php
<?php

declare(strict_types=1);

namespace App\Domain\Staffing\Actions;

use App\Domain\Staffing\DTO\LogoutData;

/**
 * Đăng xuất: thu hồi đúng token đang dùng trên thiết bị đó, không đụng token của thiết bị khác.
 */
final class RevokeCurrentToken
{
    public function handle(LogoutData $data): void
    {
        $data->user->currentAccessToken()->delete();
    }
}
```

Hỗ trợ trực tiếp (không phải "Action" nhưng quyết định luồng auth/PIN, đính kèm để review đủ ngữ cảnh):

**`app/Http/Requests/PinVerifyRequest.php`**
```php
<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class PinVerifyRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Chỉ cần đã đăng nhập (middleware auth:sanctum) — ai cũng được phép hỏi "PIN này đúng không".
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'pin' => ['required', 'string'],
        ];
    }
}
```

**`app/Http/Middleware/EnsureUserIsActive.php`**
```php
<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Nhân viên nghỉ việc thì tài khoản bị tắt cờ is_active, nhưng token cũ trên máy vẫn còn.
 * Middleware này chặn ngay, không cho dùng token cũ nữa.
 */
final class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->is_active) {
            return response()->json([
                'message' => 'Tài khoản đã bị vô hiệu hoá.',
            ], 403);
        }

        return $next($request);
    }
}
```

`routes/api.php`: `login` không cần đăng nhập; `logout` và `pin-verify` nằm sau `['auth:sanctum', 'active']`.

### 2.5. Toàn bộ file migration

Đã liệt kê tên ở mục 1.1. Nội dung đầy đủ của cả 20 file (16 bảng nghiệp vụ + hạ tầng) — xem trực tiếp trong `database/migrations/`, không chép lại toàn văn ở đây vì đã có sẵn ở mục 1.1 kèm bản đối chiếu với `docs/schema.md`. Ba điểm đáng chú ý khi review nhanh:

- Mọi migration nghiệp vụ đều set `$table->charset = 'utf8mb4'; $table->collation = 'utf8mb4_unicode_ci';` ngay đầu — khớp mục 2 CLAUDE.md.
- CHECK constraint viết bằng `DB::statement()` thô ở cuối `up()` cho `shifts`, `cash_movements`, `table_sessions`, `table_session_tables`, `option_groups`, `product_variants`, `orders`, `order_items`, `payments` — đúng cách làm mẫu trong CLAUDE.md mục 5.7.
- Cột sinh (`storedAs`) dùng ở 3 chỗ: `shifts.open_guard`, `table_session_tables.occupied_table_id`, `order_items.line_amount` — đúng ba chỗ CLAUDE.md liệt kê.

---

## 3. Những chỗ đã tự quyết định vì CLAUDE.md/docs/schema.md không nói rõ

1. **TTL của Idempotency-Key = 24 giờ.** Không tài liệu nào chốt con số này. Chọn 24h vì đây là chu kỳ một ca làm việc dài nhất hợp lý (quán không mở ca qua hai ngày). *Rủi ro thấp*: quá ngắn thì khách bấm lại sau khi mạng lag lâu sẽ bị tính trùng; quá dài thì tốn dung lượng bảng `cache`.

2. **Cách gộp khoá cache cho Idempotency**: `hash('sha256', key|userId|method|path)` — nghĩa là cùng một `Idempotency-Key` gửi tới hai endpoint khác nhau, hoặc bởi hai người dùng khác nhau, được coi là hai yêu cầu độc lập. CLAUDE.md không nói rõ phạm vi khoá. Chọn cách này để một client tái dùng UUID cho nhiều request khác nhau (nếu code client có bug) không vô tình bị coi là trùng.

3. **Idempotency chỉ áp dụng cho POST/PATCH**, bỏ qua GET/DELETE/PUT. Route hiện tại của dự án chỉ dùng POST cho hành động ghi, nên quyết định này chưa bị thử thách thực tế.

4. **Mã lỗi HTTP cho hai exception Idempotency**: thiếu header → 400, đang xử lý trùng → 409. Không có trong CLAUDE.md, chọn theo quy ước HTTP chuẩn (400 = request sai định dạng, 409 = xung đột trạng thái).

5. **Đăng nhập dùng `phone` + `password`**, không dùng `username`. `docs/schema.md` có cả hai cột với hai chú thích gần giống nhau ("Tên đăng nhập" và "Số điện thoại, dùng để đăng nhập trên máy POS") — chọn `phone` vì chú thích của nó nói thẳng "dùng để đăng nhập". `username` hiện chưa dùng ở đâu trong luồng auth.

6. **Thông báo lỗi đăng nhập gộp chung** "Số điện thoại hoặc mật khẩu không đúng" thay vì báo riêng từng trường — quyết định bảo mật (chống dò tài khoản tồn tại), không phải yêu cầu viết sẵn trong tài liệu.

7. **`PinVerifyRequest::authorize()` luôn `true`** — bất kỳ ai đã đăng nhập đều gọi được `/pin-verify` để hỏi "PIN của user X này có đúng không", không giới hạn chỉ được hỏi về chính mình. Đây là quyết định để linh hoạt cho luồng duyệt chéo (nhân viên A xin B duyệt), CLAUDE.md không nói rõ ai được phép gọi endpoint này.

8. **`Money` có hai bộ tên method song song** (`add`/`plus`, `subtract`/`minus`, `multiply`/`times`, `fromDong`/`fromInt`) — file mẫu ở CLAUDE.md mục 5.0 dùng `plus`/`minus`/`times`/`fromInt`, nhưng bản đang chạy thêm bộ tên `add`/`subtract`/`multiply`/`fromDong` và giữ bộ cũ làm alias. Không rõ lý do gốc (có thể một phiên làm việc trước đổi tên chuẩn rồi giữ alias để không phá test cũ).

9. **`phase0:check` loại 6 bảng hạ tầng Laravel/gói ngoài** (`cache`, `cache_locks`, `personal_access_tokens`, `activity_log`, `sessions`, `jobs*`) ra khỏi "15 bảng nghiệp vụ" khi đếm — CLAUDE.md/schema.md chỉ nói "15 bảng", không liệt kê rõ có tính bảng hạ tầng hay không. Chọn cách loại trừ vì mục đích thật của yêu cầu là xác nhận đủ 15 bảng nghiệp vụ theo `docs/schema.md`.

10. **`phase0:check` chỉ kiểm 12 chỉ mục** trong nhóm "CHẶN LỖI" ở `docs/schema.md` Phần 5 (dòng gộp gốc trong tài liệu ghi "9 chỉ mục" nhưng gộp nhiều bảng vào một dòng) — mở rộng thành liệt kê từng chỉ mục riêng lẻ, tổng 12, chứ không kiểm toàn bộ ~25 chỉ mục tăng tốc vì tài liệu tự nói nhóm đó "không phải luật".

11. **Danh sách 6 món thêm vào seeder** (Trà đá, Rượu đế, Đậu bắp luộc, Canh bí đao, Trái cây thập cẩm, Sữa chua) và cách phân bổ (2 món mỗi nhóm Bia & nước / Rau & canh / Tráng miệng) — tự chọn tên món và giá cho đủ 60, không có danh sách gốc nào được giao.

12. **CI dùng image `mysql:8.4`** cho service container dù `.env` máy dev thực tế trỏ MariaDB 10.4.32 (XAMPP) — chọn theo đúng stack "chốt" trong CLAUDE.md mục 2 (MySQL 8.4), không theo máy dev thực tế. Xem thêm mục 4.5 bên dưới.

---

## 4. Những chỗ KHÔNG CHẮC CHẮN về tính đúng đắn

1. **🔴 Vai trò người dùng không khớp mô tả nghiệp vụ trong CLAUDE.md.** CLAUDE.md mục 1 mô tả 5 vị trí: "chủ quán, thu ngân, phục vụ, bếp và quầy pha chế". Nhưng `UserRole` enum chỉ có 4 giá trị: `Owner, Manager, Staff, Kitchen` — không có "Manager" (quản lý) trong mô tả gốc, và không có "thu ngân" hay "quầy pha chế" riêng biệt. `docs/schema.md` bất biến **H5** ghi rõ: hủy món đã phục vụ "chỉ được hủy bởi người có quyền duyệt (**chủ quán / thu ngân**, xác nhận bằng PIN)". Nhưng `VerifyManagerPin` chỉ cho phép `Owner` và `Manager` duyệt PIN — nếu "thu ngân" trong thực tế được gán vai trò `Staff` (vì không có vai trò nào khác hợp), thì **thu ngân không duyệt được PIN dù H5 nói họ phải duyệt được**. Đây là mâu thuẫn cần chủ dự án xác nhận: "Manager" có thật sự tồn tại trong quán không, và "thu ngân" ánh xạ vào vai trò nào?

2. **🟡 Không có giới hạn số lần thử sai cho `/api/v1/auth/pin-verify`.** PIN chỉ 4-6 số, không rate limit, không khoá tạm sau N lần sai. Endpoint yêu cầu đã đăng nhập nên không hoàn toàn mở cho người lạ, nhưng một nhân viên ác ý có thể dò PIN của chủ quán bằng vài nghìn lần thử trong vài phút. `docs/viec-ton.md` có sẵn một dòng **ví dụ mẫu** y hệt lo ngại này ("Cần rate limit cho endpoint pin-verify") nhưng phần "Danh sách" thật của file đang **trống** — nghĩa là lo ngại này chưa từng được ghi nhận chính thức, chỉ nằm trong phần hướng dẫn mẫu. Cần xác nhận: đây là thiếu sót thật hay đã được chấp nhận rủi ro?

3. **🟡 Idempotency middleware viết xong nhưng chưa được gắn vào route nào.** Không thể kiểm chứng nó hoạt động đúng trong tình huống thật (thu tiền, gọi món) vì các endpoint đó chưa tồn tại. Test hiện có (`IdempotencyMiddlewareTest.php`) chắc chắn dùng route giả lập, không phải route nghiệp vụ thật — cần review lại khi có `RecordPayment`/`SubmitOrder` thật.

4. **🟡 `EnsureIdempotencyKey` lưu `Content-Type` nhưng không lưu status code khác ngoài 2xx khi replay** — dòng `'Content-Type' => $response->headers->get('Content-Type', 'application/json')` chỉ giữ lại đúng một header, các header khác (ví dụ header phân trang, ETag nếu có sau này) bị mất khi replay lần hai. Với các endpoint tiền bạc hiện tại (JSON đơn giản) không sao, nhưng chưa chắc còn đủ khi API phức tạp hơn.

5. **🟡 CI test trên MySQL 8.4, máy dev thật chạy MariaDB 10.4.32.** Hai engine không tương thích 100% ở CHECK constraint và cột sinh (`STORED AS`) — cú pháp dùng trong migration đã test chạy được trên MariaDB 10.4.32 thật (theo `phase0:check` PASS), nhưng CI chưa từng chạy thật trên MariaDB để xác nhận tương thích ngược. Nếu MySQL 8.4 chấp nhận một cú pháp mà MariaDB không hỗ trợ (hoặc ngược lại), CI xanh không đảm bảo máy dev thật cũng chạy được. Nên cân nhắc đổi CI sang image MariaDB để khớp môi trường thật, hoặc xác nhận với chủ dự án là chốt hẳn MySQL 8.4 cho production sau này.

6. **🟡 `Money::percentage()` làm tròn 0.5 lên** (dùng `round()` mặc định của PHP, `PHP_ROUND_HALF_UP`) nhưng không có tài liệu nào chốt quy tắc làm tròn tiền giảm giá phần trăm. Nếu chủ quán kỳ vọng làm tròn xuống (có lợi cho quán) thay vì làm tròn chuẩn, đây sẽ là chỗ tính sai một hai đồng — nhỏ nhưng là tiền.

7. **🟡 `AuthenticateUser` không giới hạn số lần đăng nhập sai** (không rate limit/lockout) — tương tự lo ngại PIN ở mục 4.2 nhưng cho mật khẩu đăng nhập chính. Có thể chấp nhận được vì mạng nội bộ, nhưng chưa thấy ghi nhận quyết định này ở đâu.

8. **🟡 `phase0:check` tự chạy toàn bộ Pest** ở bước cuối, xoá sạch dữ liệu thật đang có trong lúc kiểm tra (đã cảnh báo rõ trong output và README) — về mặt kỹ thuật đúng như thiết kế, nhưng nếu ai đó chạy lệnh này giữa giờ quán đang hoạt động (không đọc kỹ cảnh báo), **toàn bộ bàn đang mở, hoá đơn đang mở sẽ mất thật**. Đây là hành vi có chủ đích nhưng rủi ro vận hành cao nếu người dùng không phải dân kỹ thuật — nên cân nhắc thêm bước xác nhận `--force` hoặc bỏ hẳn bước chạy test tự động ra khỏi lệnh này.

9. **🟢 (đã xác nhận, không phải lo ngại)** `LogsActivity` chỉ gắn trên `Payment`, `Order`, `Shift` — **không** gắn trên `User`, nên không có rủi ro lộ `password`/`pin_code` qua activity log. Cả ba model dùng chung khuôn `LogOptions::defaults()->logOnly($this->fillable)->logOnlyDirty()->dontSubmitEmptyLogs()` — chỉ log field nào nằm trong `$fillable` và có thay đổi thật. Ghi lại đây để xác nhận đã kiểm tra, không phải để cảnh báo.
