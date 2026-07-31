# REVIEW-PHASE0-LAN2.md — Chứng minh 5 lỗi 🔴 đã được sửa thật

> File này chỉ báo cáo, không sửa code. Tạo theo yêu cầu chủ dự án để đối chiếu
> 5 lỗi 🔴 nêu ra sau `docs/review-phase0.md` với kết quả sửa thật trong repo.

---

## 1. BẢNG ĐỐI CHIẾU

| Lỗi | File đã sửa | Test chứng minh | Tên test |
|---|---|---|---|
| 🔴1 Idempotency kẹt khoá khi có exception | `app/Http/Middleware/EnsureIdempotencyKey.php` | 4 test mới trong `tests/Feature/Support/IdempotencyMiddlewareTest.php` | `ValidationException ném ra khỏi Action vẫn nhả khoá, gửi lại cùng key chạy lại được`<br>`DomainException ném ra khỏi Action vẫn nhả khoá, gửi lại cùng key chạy lại được`<br>`RuntimeException bất ngờ ném ra khỏi Action vẫn nhả khoá, gửi lại cùng key chạy lại được`<br>`sau khi thành công, gửi lại cùng key trả lại response cũ, không tạo bản ghi thứ hai` |
| 🔴2 CashVariance cho két thiếu tiền | `app/Support/Money.php` (thêm `isLessThan`, `equals`) + `app/Support/CashVariance.php` (file mới) | 4 test mới trong `tests/Unit/Support/CashVarianceTest.php` (file mới) | `đếm thiếu tiền thì isShortage true, absolute và format đúng`<br>`đếm khớp với sổ sách thì isBalanced true`<br>`đếm thừa tiền thì isSurplus true, absolute và format đúng`<br>`không có trường hợp nào — kể cả thiếu tiền — ném exception` |
| 🔴3 Rate limit + khoá tạm PIN | `routes/api.php` (throttle) + `app/Domain/Staffing/Actions/VerifyApproverPin.php` (đổi tên từ `VerifyManagerPin`, thêm khoá tạm + activity log) + `bootstrap/app.php` (map `ThrottleRequestsException` → 429) | 5 test mới trong `tests/Feature/Staffing/Auth/PinVerifyTest.php` | `sai PIN 5 lần liên tiếp thì lần thứ 6 bị khoá dù nhập đúng PIN`<br>`sau khi khoá hết hạn thì nhập đúng PIN lại được`<br>`nhập đúng PIN giữa chừng thì bộ đếm sai về 0, chưa đủ 5 lần mới thì không bị khoá`<br>`mỗi lần PIN sai đều ghi một bản ghi activity_log, không lộ giá trị PIN`<br>`gọi pin-verify quá 5 lần trong 1 phút thì nhận HTTP 429` |
| 🔴4 phase0:check không xoá dữ liệu | `app/Console/Commands/Phase0Check.php` (bỏ tự chạy test, thêm `--with-tests` có rào chắn) + `README.md` mục 5 | 2 test mới trong `tests/Feature/Console/Phase0CheckTest.php` (file mới) | `chạy phase0:check không cờ thì không xoá bảng dữ liệu nào`<br>`chạy --with-tests khi đang có ca mở thì dừng lại, không chạy test` |
| 🔴5 Đổi Manager thành Cashier | `app/Domain/Staffing/Enums/UserRole.php` + 8 Policy + `VerifyApproverPin.php` + `DatabaseSeeder.php` + `UserFactory.php` + `AppServiceProvider.php` + `AuthController.php` + migration mới `2026_08_01_000001_rename_manager_role_to_cashier.php` + `docs/schema.md` + `docker/mysql/init/01-schema.sql`, `02-seed-demo.sql` + `README.md` mục 6 + `CLAUDE.md` mục 1 | 0 test mới — **sửa test hiện có** (`PermissionsTest.php`, `PinVerifyTest.php`, `ActiveUserMiddlewareTest.php`) đổi `->manager()` → `->cashier()` | *(không có test mới, xem mục 4 bên dưới)* |

---

## 2. NỘI DUNG ĐẦY ĐỦ CÁC FILE SAU KHI SỬA

> Đây là nội dung **hiện tại** (mới nhất) của 6 file. Ba file đầu có thêm nội dung
> từ hai việc làm SAU 5 lỗi này (kiểm nội dung request trùng key cho Idempotency;
> gộp tên method cho Money) — xem mục 5 để biết chính xác phần nào thuộc 5 lỗi,
> phần nào không.

### 2.1. `app/Http/Middleware/EnsureIdempotencyKey.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\IdempotencyConflictException;
use App\Exceptions\IdempotencyKeyRequiredException;
use App\Exceptions\IdempotencyPayloadMismatchException;
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
    /** Khoá "đang xử lý" tự hết hạn nhanh — tiến trình chết đột ngột (timeout, mất kết nối) không kẹt khoá 24 giờ. */
    private const PROCESSING_TTL_SECONDS = 60;

    private const COMPLETED_TTL_HOURS = 24;

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
        $bodyHash = hash('sha256', (string) $request->getContent());

        $claimed = $store->add(
            $cacheKey,
            ['status' => 'processing', 'body_hash' => $bodyHash],
            now()->addSeconds(self::PROCESSING_TTL_SECONDS)
        );

        if (! $claimed) {
            /** @var array{status: string, body_hash?: string, http_status?: int, headers?: array<string, string>, body?: string}|null $existing */
            $existing = $store->get($cacheKey);

            // Cùng mã nhưng nội dung khác lần trước — không được âm thầm thay thế
            // hay bỏ qua request, đặc biệt nguy hiểm với endpoint thu tiền.
            if ($existing !== null && ($existing['body_hash'] ?? null) !== $bodyHash) {
                throw new IdempotencyPayloadMismatchException(
                    'Mã chống trùng này đã dùng cho một yêu cầu khác. Vui lòng thử lại.'
                );
            }

            if ($existing === null || $existing['status'] === 'processing') {
                throw new IdempotencyConflictException('Yêu cầu trước đó với cùng mã đang được xử lý.');
            }

            $replay = response($existing['body'] ?? '', $existing['http_status'] ?? 200);
            foreach ($existing['headers'] ?? [] as $name => $value) {
                $replay->headers->set($name, $value);
            }

            return $replay;
        }

        try {
            $response = $next($request);
        } catch (\Throwable $e) {
            // Bất kỳ lỗi nào bay ra (validation, domain, lỗi hệ thống...) đều coi như
            // "chưa hoàn tất" — nhả khoá ngay để gửi lại cùng key được, không kẹt 24 giờ.
            $store->forget($cacheKey);

            throw $e;
        }

        if ($response->isSuccessful()) {
            $store->put($cacheKey, [
                'status' => 'completed',
                'body_hash' => $bodyHash,
                'http_status' => $response->getStatusCode(),
                'headers' => [
                    'Content-Type' => $response->headers->get('Content-Type', 'application/json'),
                ],
                'body' => $response->getContent(),
            ], now()->addHours(self::COMPLETED_TTL_HOURS));
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

**Phần thuộc 🔴1**: khối `try { $response = $next($request); } catch (\Throwable $e) { $store->forget($cacheKey); throw $e; }` và việc tách `PROCESSING_TTL_SECONDS` (60s) riêng khỏi `COMPLETED_TTL_HOURS` (24h).
**Phần KHÔNG thuộc 🔴1** (việc làm sau, mục "kiểm nội dung request trùng key"): toàn bộ phần `body_hash` — tham số, dòng `IdempotencyPayloadMismatchException`, import của nó.

### 2.2. `app/Support/Money.php`

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

    public static function fromInt(int|float $amount): self
    {
        if (is_float($amount)) {
            throw new InvalidArgumentException('Số tiền phải là số nguyên đồng, không được là số thực.');
        }

        if ($amount < 0) {
            throw new InvalidArgumentException("Số tiền không được âm: {$amount}");
        }

        return new self($amount);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    public function plus(self $other): self
    {
        return new self($this->amount + $other->amount);
    }

    public function minus(self $other): self
    {
        if ($other->amount > $this->amount) {
            throw new InvalidArgumentException(
                "Phép trừ ra số âm: {$this->amount} - {$other->amount}"
            );
        }

        return new self($this->amount - $other->amount);
    }

    public function times(int $quantity): self
    {
        if ($quantity < 0) {
            throw new InvalidArgumentException("Số lượng không được âm: {$quantity}");
        }

        return new self($this->amount * $quantity);
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

    public function isLessThan(self $other): bool
    {
        return $this->amount < $other->amount;
    }

    public function equals(self $other): bool
    {
        return $this->amount === $other->amount;
    }

    /** Định dạng cho người đọc: 1250000 → "1.250.000 đ" */
    public function format(): string
    {
        return number_format($this->amount, 0, ',', '.').' đ';
    }
}
```

**Phần thuộc 🔴2**: `isLessThan()`, `equals()` — hai method so sánh không ném lỗi, để `CashVariance` dùng được mà không phải bắt exception.
**Phần KHÔNG thuộc 🔴2** (việc làm sau, mục "gộp tên method"): việc xoá hẳn `fromDong`, `add`, `subtract`, `multiply` — trước đó các tên này tồn tại song song với `fromInt`/`plus`/`minus`/`times` làm alias qua lại; nay chỉ còn một bộ tên.

### 2.3. `app/Support/CashVariance.php`

```php
<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Chênh lệch giữa tiền mặt ĐẾM THỰC TẾ và tiền mặt LẼ RA phải có khi đóng ca.
 *
 * Vì sao không dùng Money cho việc này: Money là bất biến "số tiền thật luôn
 * không âm" — đúng cho tiền trong két, tiền trên hoá đơn. Nhưng chênh lệch đối
 * soát là một khái niệm khác hẳn: một HIỆU SỐ CÓ DẤU, và trường hợp quan trọng
 * nhất cần ghi lại được chính là lúc nó âm (két thiếu tiền). Nhồi khái niệm này
 * vào Money sẽ phải phá vỡ bất biến "không âm" của Money cho MỌI chỗ dùng khác
 * (thu tiền, tính hoá đơn...) — rủi ro hơn nhiều so với tách riêng một class nhỏ.
 */
final readonly class CashVariance
{
    private function __construct(public int $amount) {}

    public static function between(Money $counted, Money $expected): self
    {
        return new self($counted->amount - $expected->amount);
    }

    public function isBalanced(): bool
    {
        return $this->amount === 0;
    }

    public function isShortage(): bool
    {
        return $this->amount < 0;
    }

    public function isSurplus(): bool
    {
        return $this->amount > 0;
    }

    public function absolute(): Money
    {
        return Money::fromInt(abs($this->amount));
    }

    /** "Thiếu 200.000 đ" / "Thừa 50.000 đ" / "Khớp" */
    public function format(): string
    {
        return match (true) {
            $this->isShortage() => 'Thiếu '.$this->absolute()->format(),
            $this->isSurplus() => 'Thừa '.$this->absolute()->format(),
            default => 'Khớp',
        };
    }
}
```

Toàn bộ file này thuộc 🔴2 (file mới tạo hoàn toàn cho lỗi này), chỉ riêng chữ `fromInt` ở dòng `absolute()` — lúc tạo file gốc là `fromDong`, đổi thành `fromInt` ở việc làm sau (gộp tên method), không đổi hành vi.

### 2.4. `app/Domain/Staffing/Actions/VerifyApproverPin.php`

```php
<?php

declare(strict_types=1);

namespace App\Domain\Staffing\Actions;

use App\Domain\Staffing\DTO\PinVerifyData;
use App\Domain\Staffing\Enums\UserRole;
use App\Domain\Staffing\Models\User;
use App\Exceptions\DomainException;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

/**
 * Xác thực mã PIN của chủ quán/thu ngân để duyệt một hành động nhạy cảm
 * (ví dụ: hủy món đã phục vụ ra bàn — xem bất biến H5 ở docs/schema.md).
 *
 * Chống dò PIN bằng vét cạn: PIN chỉ 4-6 số nên phải tự khoá tạm người bị dò,
 * không thể chỉ dựa vào rate limit theo người GỌI (một người dò PIN của nhiều
 * người khác nhau vẫn né được rate limit theo IP/user gọi).
 */
final class VerifyApproverPin
{
    private const MAX_SAI_LIEN_TIEP = 5;

    private const KHOA_PHUT = 15;

    public function handle(PinVerifyData $data): User
    {
        $approver = User::query()->find($data->userId);

        if ($approver === null || ! $approver->is_active) {
            throw new DomainException('Người này không có quyền duyệt.');
        }

        if (! in_array($approver->role, [UserRole::Owner, UserRole::Cashier], true)) {
            throw new DomainException('Người này không có quyền duyệt.');
        }

        if ($approver->pin_code === null) {
            throw new DomainException('Người này chưa thiết lập mã PIN.');
        }

        $store = Cache::store('database');

        if ($store->has($this->khoaKeyCho($approver->id))) {
            throw new DomainException('Mã PIN đã bị tạm khoá do thử sai nhiều lần. Đợi 15 phút.');
        }

        if (! Hash::check($data->pin, $approver->pin_code)) {
            $this->ghiNhanSaiVaKhoaNeuCan($store, $approver, $data->requestedByUserId);

            throw new DomainException('Mã PIN không đúng.');
        }

        $store->forget($this->demSaiKeyCho($approver->id));

        return $approver;
    }

    private function ghiNhanSaiVaKhoaNeuCan(CacheRepository $store, User $approver, int $requestedByUserId): void
    {
        $demKey = $this->demSaiKeyCho($approver->id);
        $soLanSai = (int) $store->get($demKey, 0) + 1;
        $store->put($demKey, $soLanSai, now()->addMinutes(self::KHOA_PHUT));

        // Truyền thẳng Model thay vì int cho causedBy(): CauserResolver chỉ tra được
        // int qua guard hiện tại của request, guard đó không đảm bảo còn tồn tại
        // đúng lúc gọi (ví dụ gọi trực tiếp Action ngoài vòng đời HTTP như trong test).
        $nguoiGoi = User::query()->find($requestedByUserId);

        activity('pin-verify')
            ->causedBy($nguoiGoi)
            ->performedOn($approver)
            ->withProperties([
                'requested_by_user_id' => $requestedByUserId,
                'approver_user_id' => $approver->id,
                'so_lan_sai_lien_tiep' => $soLanSai,
            ])
            ->log('Thử mã PIN sai.');

        if ($soLanSai >= self::MAX_SAI_LIEN_TIEP) {
            $store->put($this->khoaKeyCho($approver->id), true, now()->addMinutes(self::KHOA_PHUT));
            $store->forget($demKey);
        }
    }

    private function demSaiKeyCho(int $approverId): string
    {
        return "pin-verify-sai:{$approverId}";
    }

    private function khoaKeyCho(int $approverId): string
    {
        return "pin-verify-khoa:{$approverId}";
    }
}
```

Toàn bộ nội dung nghiệp vụ (khoá tạm, activity log) thuộc 🔴3. Tên class `VerifyApproverPin` và điều kiện `[UserRole::Owner, UserRole::Cashier]` thuộc 🔴5 (trước đó là `VerifyManagerPin` với `[UserRole::Owner, UserRole::Manager]`).

### 2.5. `app/Domain/Staffing/Enums/UserRole.php`

```php
<?php

declare(strict_types=1);

namespace App\Domain\Staffing\Enums;

enum UserRole: string
{
    case Owner = 'owner';
    case Cashier = 'cashier';
    case Staff = 'staff';
    case Kitchen = 'kitchen';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Chủ quán',
            self::Cashier => 'Thu ngân',
            self::Staff => 'Nhân viên',
            self::Kitchen => 'Bếp',
        };
    }
}
```

Toàn bộ file thuộc 🔴5.

### 2.6. `app/Console/Commands/Phase0Check.php`

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Catalog\Models\Product;
use App\Domain\Ordering\Models\DiningTable;
use App\Domain\Staffing\Models\User;
use App\Http\Middleware\EnsureIdempotencyKey;
use App\Support\Money;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Route;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\Console\Command\Command as CommandAlias;

/**
 * Bảng danh sách kiểm tra Phase 0 — chạy trước khi bắt đầu Phase 1.
 * Mỗi mục là một điều kiện đã chốt trong docs/schema.md, không phải suy đoán.
 */
final class Phase0Check extends Command
{
    protected $signature = 'phase0:check {--with-tests : Chạy toàn bộ test suite thật — XOÁ SẠCH dữ liệu hiện có, chỉ dùng khi quán đóng cửa}';

    protected $description = 'Kiểm tra toàn bộ điều kiện Phase 0 và in báo cáo tiếng Việt';

    /** @var list<string> */
    private array $mucConChuaXong = [];

    /** Tên 15 bảng nghiệp vụ thật — không tính bảng hạ tầng Laravel (cache, session, activity_log...) */
    private const BANG_NGHIEP_VU = [
        'users', 'shifts', 'cash_movements',
        'dining_tables', 'table_sessions', 'table_session_tables',
        'categories', 'products', 'product_variants', 'option_groups', 'options',
        'orders', 'order_items', 'order_item_options',
        'payments',
    ];

    /** Chỉ mục CHẶN LỖI — nếu thiếu một cái là có thể xảy ra lỗi nghiệp vụ nghiêm trọng */
    private const CHI_MUC_CHAN_LOI = [
        'table_session_tables' => ['uq_tst_one_session_per_table'],
        'orders' => ['uq_orders_uuid', 'uq_orders_session_seq_station'],
        'payments' => ['uq_payments_uuid'],
        'shifts' => ['uq_shifts_only_one_open'],
        'table_sessions' => ['uq_table_sessions_bill_no'],
        'product_variants' => ['uq_variants_product_name'],
        'options' => ['uq_options_group_name'],
        'users' => ['uq_users_username'],
        'dining_tables' => ['uq_dining_tables_code'],
        'products' => ['uq_products_code'],
        'categories' => ['uq_categories_name'],
    ];

    public function handle(): int
    {
        $this->newLine();
        $this->line('=== PHASE 0 — KIỂM TRA HỆ THỐNG ===');
        $this->newLine();

        $this->kiemTraKetNoiDatabase();
        $this->kiemTraCacheVaKhoa();
        $this->kiemTraCharset();
        $this->kiemTraSoBang();
        $this->kiemTraChiMuc();
        $this->kiemTraUsers();
        $this->kiemTraBan();
        $this->kiemTraThucDon();
        $this->kiemTraMiddlewareIdempotency();
        $this->kiemTraMoney();
        $this->kiemTraActivityLog();
        $this->kiemTraFileTest();

        $this->newLine();
        $ketQuaChanDoan = $this->mucConChuaXong === [] ? CommandAlias::SUCCESS : CommandAlias::FAILURE;

        if ($ketQuaChanDoan === CommandAlias::SUCCESS) {
            $this->line('<fg=green>PHASE 0 HOÀN TẤT ✅</>');
        } else {
            $this->line('<fg=red>CÒN '.count($this->mucConChuaXong).' MỤC CHƯA XONG ❌</>');
            foreach ($this->mucConChuaXong as $muc) {
                $this->line("  - {$muc}");
            }
        }

        if (! $this->option('with-tests')) {
            return $ketQuaChanDoan;
        }

        return $this->chayTestNeuAnToan() ? $ketQuaChanDoan : CommandAlias::FAILURE;
    }

    private function baoOk(string $noiDung): void
    {
        $this->line("✅ {$noiDung}");
    }

    private function baoFail(string $noiDung): void
    {
        $this->line("❌ {$noiDung}");
        $this->mucConChuaXong[] = $noiDung;
    }

    private function kiemTraKetNoiDatabase(): void
    {
        try {
            $host = config('database.connections.mysql.host');
            $port = config('database.connections.mysql.port');
            $phienBan = DB::selectOne('select version() as v')->v;
            $this->baoOk("Kết nối được database ở {$host}:{$port} — phiên bản: {$phienBan}");
        } catch (\Throwable $e) {
            $this->baoFail('Không kết nối được database: '.$e->getMessage());
        }
    }

    private function kiemTraCacheVaKhoa(): void
    {
        $driver = config('cache.default');

        try {
            $lock = Cache::lock('phase0-check-lock', 10);
            if (! $lock->get()) {
                $this->baoFail("Không lấy được khoá thử (cache driver: {$driver}).");

                return;
            }
            $lock->release();

            if ($driver === 'database') {
                $this->baoOk('Cache và khoá (driver database) hoạt động bình thường.');
            } else {
                $this->baoFail(
                    "Cache và khoá đang chạy được, nhưng driver hiện tại là '{$driver}', không phải 'database'. ".
                    'Muốn dùng khoá phân tán đúng chuẩn Phase 0 thì cần đổi .env sang CACHE_STORE=database — hỏi chủ dự án trước khi đổi.'
                );
            }
        } catch (\Throwable $e) {
            $this->baoFail('Cache/khoá lỗi: '.$e->getMessage());
        }
    }

    private function kiemTraCharset(): void
    {
        $hang = DB::selectOne(
            'select DEFAULT_CHARACTER_SET_NAME as bang_ma, DEFAULT_COLLATION_NAME as doi_chieu
             from information_schema.SCHEMATA where SCHEMA_NAME = DATABASE()'
        );

        if ($hang === null) {
            $this->baoFail('Không đọc được bảng mã database.');

            return;
        }

        $dungBangMa = str_starts_with((string) $hang->bang_ma, 'utf8mb4');
        $dungDoiChieu = str_starts_with((string) $hang->doi_chieu, 'utf8mb4');

        if ($dungBangMa && $dungDoiChieu) {
            $this->baoOk("Bảng mã database đúng chuẩn: {$hang->bang_ma} / {$hang->doi_chieu}");
        } else {
            $this->baoFail("Bảng mã database sai chuẩn: {$hang->bang_ma} / {$hang->doi_chieu} (cần utf8mb4 / utf8mb4_unicode_ci)");
        }
    }

    private function kiemTraSoBang(): void
    {
        $tenBangThat = DB::table('information_schema.tables')
            ->where('table_schema', DB::raw('DATABASE()'))
            ->whereIn('table_name', self::BANG_NGHIEP_VU)
            ->orderBy('table_name')
            ->pluck('table_name')
            ->all();

        $soLuong = count($tenBangThat);
        $soCanCo = count(self::BANG_NGHIEP_VU);

        if ($soLuong === $soCanCo) {
            $this->baoOk("Đủ {$soCanCo} bảng nghiệp vụ: ".implode(', ', $tenBangThat));
        } else {
            $thieu = array_diff(self::BANG_NGHIEP_VU, $tenBangThat);
            $this->baoFail("Chỉ có {$soLuong}/{$soCanCo} bảng nghiệp vụ. Thiếu: ".implode(', ', $thieu));
        }
    }

    private function kiemTraChiMuc(): void
    {
        $thieu = [];

        foreach (self::CHI_MUC_CHAN_LOI as $bang => $danhSachChiMuc) {
            $chiMucThat = DB::table('information_schema.statistics')
                ->where('table_schema', DB::raw('DATABASE()'))
                ->where('table_name', $bang)
                ->distinct()
                ->pluck('index_name')
                ->all();

            foreach ($danhSachChiMuc as $chiMuc) {
                if (! in_array($chiMuc, $chiMucThat, true)) {
                    $thieu[] = "{$bang}.{$chiMuc}";
                }
            }
        }

        $tongSo = array_sum(array_map('count', self::CHI_MUC_CHAN_LOI));

        if ($thieu === []) {
            $this->baoOk("Đủ {$tongSo} chỉ mục chặn lỗi bắt buộc.");
        } else {
            $this->baoFail('Thiếu chỉ mục chặn lỗi: '.implode(', ', $thieu));
        }
    }

    private function kiemTraUsers(): void
    {
        $soRole = User::query()->distinct()->count('role');

        if ($soRole >= 4) {
            $this->baoOk("Đủ {$soRole} vai trò người dùng khác nhau.");
        } else {
            $this->baoFail("Chỉ có {$soRole}/4 vai trò người dùng. Chạy `php artisan db:seed` để nạp dữ liệu mẫu.");
        }
    }

    private function kiemTraBan(): void
    {
        $soBan = DiningTable::query()->count();

        if ($soBan >= 12) {
            $this->baoOk("Đủ {$soBan} bàn.");
        } else {
            $this->baoFail("Chỉ có {$soBan}/12 bàn. Chạy `php artisan db:seed` để nạp dữ liệu mẫu.");
        }
    }

    private function kiemTraThucDon(): void
    {
        $soMon = Product::query()->count();
        $soMonCoBienThe = Product::query()->has('variants', '>=', 2)->count();
        $soMonCoTuyChon = Product::query()->has('optionGroups')->count();

        $moTa = "{$soMon} món, {$soMonCoBienThe} món có nhiều biến thể, {$soMonCoTuyChon} món có tùy chọn";

        if ($soMon >= 60 && $soMonCoBienThe >= 1 && $soMonCoTuyChon >= 10) {
            $this->baoOk("Thực đơn đủ: {$moTa}.");
        } else {
            $this->baoFail("Thực đơn chưa đủ: {$moTa} (cần ≥60 món, ≥1 món nhiều biến thể, ≥10 món có tùy chọn).");
        }
    }

    private function kiemTraMiddlewareIdempotency(): void
    {
        $alias = Route::getMiddleware()['idempotent'] ?? null;

        if ($alias === EnsureIdempotencyKey::class) {
            $this->baoOk("Middleware Idempotency đã đăng ký với alias 'idempotent'.");
        } else {
            $this->baoFail("Middleware Idempotency chưa đăng ký alias 'idempotent' trong bootstrap/app.php.");
        }
    }

    private function kiemTraMoney(): void
    {
        try {
            $tong = Money::fromInt(100_000)->plus(Money::fromInt(50_000));
            if ($tong->amount === 150_000) {
                $this->baoOk("Class Money hoạt động: 100.000 đ + 50.000 đ = {$tong->format()}");
            } else {
                $this->baoFail("Class Money tính sai: kết quả {$tong->amount}, phải là 150000.");
            }
        } catch (\Throwable $e) {
            $this->baoFail('Class Money lỗi: '.$e->getMessage());
        }
    }

    private function kiemTraActivityLog(): void
    {
        try {
            $soLuongTruoc = Activity::query()->count();

            activity('phase0-check')
                ->withProperties(['nguon' => 'phase0:check'])
                ->log('Bản ghi thử để kiểm tra activity log — an toàn xoá.');

            $banGhiMoi = Activity::query()->where('log_name', 'phase0-check')->latest('id')->first();

            if ($banGhiMoi !== null && Activity::query()->count() === $soLuongTruoc + 1) {
                $this->baoOk("Activitylog đang ghi bình thường (bản ghi mới id={$banGhiMoi->id}).");
            } else {
                $this->baoFail('Activitylog không ghi được bản ghi thử.');
            }
        } catch (\Throwable $e) {
            $this->baoFail('Activitylog lỗi: '.$e->getMessage());
        }
    }

    /**
     * Chỉ ĐẾM file, không chạy gì cả — một lệnh chẩn đoán không bao giờ được phép
     * phá dữ liệu. Muốn chạy test thật thì gõ thêm --with-tests, có rào chắn riêng.
     */
    private function kiemTraFileTest(): void
    {
        $soFile = collect(File::allFiles(base_path('tests')))
            ->filter(fn ($file) => $file->getExtension() === 'php')
            ->count();

        $this->baoOk(
            "Có {$soFile} file test trong tests/ — chạy `php artisan test` ".
            '(hoặc `php artisan phase0:check --with-tests` khi quán đang đóng cửa) để kiểm chứng thật.'
        );
    }

    /**
     * Rào chắn ba lớp trước khi cho phép chạy Pest thật: đúng môi trường, không có
     * ca đang mở, và người vận hành phải gõ đúng chữ xác nhận — vì test dùng
     * RefreshDatabase, chạy nhầm giữa giờ bán là mất trắng bàn/hoá đơn/ca đang mở.
     */
    private function chayTestNeuAnToan(): bool
    {
        $this->newLine();
        $this->line('<fg=yellow>--with-tests: chuẩn bị chạy toàn bộ test suite thật.</>');

        if (! in_array(app()->environment(), ['local', 'testing'], true)) {
            $this->line("<fg=red>❌ Môi trường hiện tại là \"".app()->environment().'" — không phải "local" hay "testing". Không chạy test ở đây.</>');

            return false;
        }

        if (DB::table('shifts')->where('status', 'open')->exists()) {
            $this->line('<fg=red>❌ Đang có ca làm việc mở. Không chạy test khi quán đang bán hàng.</>');

            return false;
        }

        $this->line('<fg=red>Lệnh này sẽ XOÁ SẠCH VÀ NẠP LẠI schema database (RefreshDatabase). Toàn bộ bàn, hoá đơn, ca hiện có sẽ MẤT.</>');
        $xacNhan = $this->ask('Gõ chính xác XOA-DU-LIEU để tiếp tục (gõ sai hoặc để trống sẽ huỷ)');

        if ($xacNhan !== 'XOA-DU-LIEU') {
            $this->line('Đã huỷ, không chạy test.');

            return false;
        }

        $ketQua = Process::timeout(600)->run(base_path('vendor/bin/pest').' --colors=never');

        $output = $ketQua->output().$ketQua->errorOutput();

        if (preg_match('/Tests:\s*(.+)/', $output, $m)) {
            $dongTomTat = trim($m[1]);
        } else {
            $dongTomTat = 'không đọc được dòng tóm tắt — xem chi tiết bên dưới';
        }

        if ($ketQua->successful()) {
            $this->line("<fg=green>✅ Toàn bộ test suite PASS. {$dongTomTat}</>");
            $this->line('Nhớ chạy `php artisan db:seed` để có lại dữ liệu mẫu.');

            return true;
        }

        $this->line("<fg=red>❌ Test suite có test FAIL. {$dongTomTat}</>");
        $this->line($output);

        return false;
    }
}
```

Toàn bộ file thuộc 🔴4 — riêng phần `kiemTraKetNoiDatabase`...`kiemTraActivityLog` (11 hàm đầu) là code có từ trước lỗi này (lúc tạo `phase0:check` lần đầu), không đổi khi sửa 🔴4. Phần thuộc riêng 🔴4: đổi `kiemTraTestSuite()` (tự chạy Pest, cũ) thành `kiemTraFileTest()` (chỉ đếm, đọc-only) + toàn bộ `chayTestNeuAnToan()` + option `--with-tests` trong `$signature`.

### 2.7. `routes/api.php`

```php
<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::prefix('auth')->group(function (): void {
        Route::post('login', [AuthController::class, 'login']);

        Route::middleware(['auth:sanctum', 'active'])->group(function (): void {
            Route::post('logout', [AuthController::class, 'logout']);

            // Chống dò PIN bằng cách thử vét cạn: tối đa 5 lần/phút và 20 lần/giờ theo user gọi.
            Route::post('pin-verify', [AuthController::class, 'pinVerify'])
                ->middleware(['throttle:5,1,pin-verify-minute', 'throttle:20,60,pin-verify-hour']);
        });
    });
});
```

Toàn bộ file thuộc 🔴3 (dòng `->middleware(['throttle:...'])` là phần thêm mới; phần còn lại có từ trước).

---

## 3. NỘI DUNG CÁC TEST MỚI (chỉ test mới, không kèm test cũ)

### 🔴1 — 4 test mới trong `tests/Feature/Support/IdempotencyMiddlewareTest.php`

```php
it('ValidationException ném ra khỏi Action vẫn nhả khoá, gửi lại cùng key chạy lại được', function () {
    Route::middleware(['auth:sanctum', 'idempotent'])->post('/_test/idempotent-validation-exception', function (Request $request) {
        if (! $request->filled('hop_le')) {
            throw ValidationException::withMessages(['hop_le' => 'Thiếu trường hop_le.']);
        }

        return response()->json(['ok' => true], 201);
    });

    $this->actingAs($this->user);
    $key = (string) Str::uuid();

    $this->postJson('/_test/idempotent-validation-exception', [], ['Idempotency-Key' => $key])
        ->assertStatus(422);

    $this->postJson('/_test/idempotent-validation-exception', ['hop_le' => 1], ['Idempotency-Key' => $key])
        ->assertCreated();
});

it('DomainException ném ra khỏi Action vẫn nhả khoá, gửi lại cùng key chạy lại được', function () {
    // DomainException chỉ được bootstrap/app.php đổi thành 422 cho route bắt đầu bằng "api/"
    // (xem $request->is('api/*')) — route test phải nằm dưới /api để đúng điều kiện đó.
    Route::middleware(['auth:sanctum', 'idempotent'])->post('/api/_test/idempotent-domain-exception', function (Request $request) {
        if (! $request->filled('hop_le')) {
            throw new DomainException('Chưa mở ca. Phải mở ca trước khi thao tác.');
        }

        return response()->json(['ok' => true], 201);
    });

    $this->actingAs($this->user);
    $key = (string) Str::uuid();

    $this->postJson('/api/_test/idempotent-domain-exception', [], ['Idempotency-Key' => $key])
        ->assertStatus(422);

    $this->postJson('/api/_test/idempotent-domain-exception', ['hop_le' => 1], ['Idempotency-Key' => $key])
        ->assertCreated();
});

it('RuntimeException bất ngờ ném ra khỏi Action vẫn nhả khoá, gửi lại cùng key chạy lại được', function () {
    Route::middleware(['auth:sanctum', 'idempotent'])->post('/_test/idempotent-runtime-exception', function (Request $request) {
        if (! $request->filled('hop_le')) {
            throw new RuntimeException('Lỗi hệ thống không lường trước.');
        }

        return response()->json(['ok' => true], 201);
    });

    $this->actingAs($this->user);
    $key = (string) Str::uuid();

    // RuntimeException không nằm trong danh sách exception nghiệp vụ được ánh xạ ở
    // bootstrap/app.php nên Laravel trả về lỗi máy chủ (5xx) — điều quan trọng cần
    // kiểm ở đây là khoá vẫn được nhả, không phải mã lỗi cụ thể là gì.
    $this->postJson('/_test/idempotent-runtime-exception', [], ['Idempotency-Key' => $key])
        ->assertServerError();

    $this->postJson('/_test/idempotent-runtime-exception', ['hop_le' => 1], ['Idempotency-Key' => $key])
        ->assertCreated();
});

it('sau khi thành công, gửi lại cùng key trả lại response cũ, không tạo bản ghi thứ hai', function () {
    $this->actingAs($this->user);
    $key = (string) Str::uuid();

    $first = $this->postJson('/api/_test/idempotent-echo', [], ['Idempotency-Key' => $key])->assertCreated();
    $second = $this->postJson('/api/_test/idempotent-echo', [], ['Idempotency-Key' => $key])->assertCreated();

    expect(CashMovement::query()->count())->toBe(1)
        ->and($second->json('id'))->toBe($first->json('id'))
        ->and($second->json())->toBe($first->json());
});
```

### 🔴2 — toàn bộ `tests/Unit/Support/CashVarianceTest.php` (file mới, 4 test)

```php
<?php

declare(strict_types=1);

use App\Support\CashVariance;
use App\Support\Money;

it('đếm thiếu tiền thì isShortage true, absolute và format đúng', function () {
    $chenh_lech = CashVariance::between(
        counted: Money::fromInt(4_800_000),
        expected: Money::fromInt(5_000_000),
    );

    expect($chenh_lech->isShortage())->toBeTrue()
        ->and($chenh_lech->isBalanced())->toBeFalse()
        ->and($chenh_lech->isSurplus())->toBeFalse()
        ->and($chenh_lech->absolute()->amount)->toBe(200_000)
        ->and($chenh_lech->format())->toBe('Thiếu 200.000 đ');
});

it('đếm khớp với sổ sách thì isBalanced true', function () {
    $chenh_lech = CashVariance::between(
        counted: Money::fromInt(5_000_000),
        expected: Money::fromInt(5_000_000),
    );

    expect($chenh_lech->isBalanced())->toBeTrue()
        ->and($chenh_lech->isShortage())->toBeFalse()
        ->and($chenh_lech->isSurplus())->toBeFalse()
        ->and($chenh_lech->absolute()->amount)->toBe(0)
        ->and($chenh_lech->format())->toBe('Khớp');
});

it('đếm thừa tiền thì isSurplus true, absolute và format đúng', function () {
    $chenh_lech = CashVariance::between(
        counted: Money::fromInt(5_050_000),
        expected: Money::fromInt(5_000_000),
    );

    expect($chenh_lech->isSurplus())->toBeTrue()
        ->and($chenh_lech->isBalanced())->toBeFalse()
        ->and($chenh_lech->isShortage())->toBeFalse()
        ->and($chenh_lech->absolute()->amount)->toBe(50_000)
        ->and($chenh_lech->format())->toBe('Thừa 50.000 đ');
});

it('không có trường hợp nào — kể cả thiếu tiền — ném exception', function () {
    expect(fn () => CashVariance::between(Money::fromInt(0), Money::fromInt(10_000_000)))
        ->not->toThrow(Throwable::class);

    expect(fn () => CashVariance::between(Money::fromInt(10_000_000), Money::fromInt(0)))
        ->not->toThrow(Throwable::class);

    expect(fn () => CashVariance::between(Money::zero(), Money::zero()))
        ->not->toThrow(Throwable::class);
});
```

*(Ở đây `Money::fromInt` là tên hiện tại; lúc mới viết cho 🔴2, tên gốc là `Money::fromDong` — đổi tên ở việc làm sau, không đổi số liệu/kết quả.)*

### 🔴3 — 5 test mới + 1 hàm hỗ trợ trong `tests/Feature/Staffing/Auth/PinVerifyTest.php`

```php
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
        ->toThrow(DomainException::class, 'Mã PIN đã bị tạm khoá do thử sai nhiều lần. Đợi 15 phút.');
});

it('sau khi khoá hết hạn thì nhập đúng PIN lại được', function () {
    $thuNgan = User::factory()->cashier()->withPin('1234')->create();
    $staff = User::factory()->staff()->create();

    for ($i = 0; $i < 5; $i++) {
        thuSaiPin($thuNgan, $staff);
    }

    expect(fn () => app(VerifyApproverPin::class)->handle(new PinVerifyData($thuNgan->id, '1234', $staff->id)))
        ->toThrow(DomainException::class);

    DB::table('cache')
        ->where('key', config('cache.prefix').'pin-verify-khoa:'.$thuNgan->id)
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

    app(VerifyApproverPin::class)->handle(new PinVerifyData($thuNgan->id, '1234', $staff->id));

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

    for ($i = 0; $i < 5; $i++) {
        pinVerify($staff, $thuNgan->id, '1234')->assertOk();
    }

    pinVerify($staff, $thuNgan->id, '1234')
        ->assertStatus(429)
        ->assertJsonPath('code', 'TOO_MANY_ATTEMPTS');
});
```

*(Biến `$thuNgan` lúc mới viết cho 🔴3 tên là `$manager`, đổi tên biến ở việc sửa 🔴5 — không đổi logic test.)*

### 🔴4 — toàn bộ `tests/Feature/Console/Phase0CheckTest.php` (file mới, 2 test)

```php
<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Product;
use App\Domain\Ordering\Models\DiningTable;
use App\Domain\Staffing\Models\Shift;
use App\Domain\Staffing\Models\User;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    Shift::factory()->closed()->create();
    DiningTable::factory()->count(2)->create();
    Product::factory()->count(2)->create();
});

it('chạy phase0:check không cờ thì không xoá bảng dữ liệu nào', function () {
    $demTruoc = [
        'users' => User::query()->count(),
        'shifts' => Shift::query()->count(),
        'dining_tables' => DiningTable::query()->count(),
        'products' => Product::query()->count(),
    ];

    Artisan::call('phase0:check');

    expect(User::query()->count())->toBe($demTruoc['users'])
        ->and(Shift::query()->count())->toBe($demTruoc['shifts'])
        ->and(DiningTable::query()->count())->toBe($demTruoc['dining_tables'])
        ->and(Product::query()->count())->toBe($demTruoc['products']);
});

it('chạy --with-tests khi đang có ca mở thì dừng lại, không chạy test', function () {
    Shift::factory()->open()->create();

    $exitCode = Artisan::call('phase0:check', ['--with-tests' => true]);

    expect($exitCode)->not->toBe(0);
});
```

### 🔴5 — không có test mới

Không thêm `it(...)` mới nào cho riêng lỗi này. Việc kiểm chứng là **sửa test có sẵn** để chúng tiếp tục PASS với vai trò `cashier` thay vì `manager` — bản chất các assertion (ai làm được gì) giữ nguyên 100%, chỉ đổi tên factory state gọi (`->manager()` → `->cashier()`) và mô tả. Nói cách khác: **65 test hiện tại chính là bằng chứng cho 🔴5** — nếu đổi role sai chỗ nào, toàn bộ `PermissionsTest.php` (11 test) và `PinVerifyTest.php` (9 test) sẽ đỏ ngay.

---

## 4. SỐ TEST TRƯỚC VÀ SAU KHI SỬA

| Mốc | Số test | Số assertion |
|---|---|---|
| **Trước khi sửa 5 lỗi** (ngay sau khi `phase0:check`/CI được tạo, trước lỗi 🔴1) | 43 | 151 |
| Sau 🔴1 (Idempotency) | 47 (+4) | 162 |
| Sau 🔴2 (CashVariance) | 51 (+4) | 180 |
| Sau 🔴3 (Rate limit + khoá PIN) | 56 (+5) | 202 |
| Sau 🔴4 (phase0:check không xoá) | 58 (+2) | 207 |
| **Sau 🔴5 (Manager→Cashier)** | 58 (+0, chỉ sửa test có sẵn) | 207 |
| *(việc làm sau, không thuộc 5 lỗi: kiểm nội dung request trùng key)* | 59 (+1) | 211 |
| *(việc làm sau, không thuộc 5 lỗi: gộp tên method Money)* | 60 (+1 ròng — bớt 1 test alias cũ, thêm 2 test mới) | 215 |
| *(việc làm sau, không thuộc 5 lỗi: kiểm chứng cột tự tính database)* | 65 (+5) | 226 |
| **Hiện tại (lúc viết báo cáo này)** | **65** | **226** |

**Riêng cho 5 lỗi 🔴**: 43 → 58 test (**+15 test, +56 assertion**), toàn bộ PASS ở lần chạy gần nhất (đã chạy lại `./vendor/bin/pest` đầy đủ sau mỗi lần sửa, không có test nào bị bỏ qua hay đánh dấu skip).

---

## 5. DANH SÁCH FILE ĐÃ ĐỤNG VÀO MÀ KHÔNG NẰM TRONG 5 LỖI TRÊN

Tất cả các file dưới đây thuộc **các yêu cầu riêng biệt, sau 5 lỗi này**, mỗi yêu cầu đều đã qua đúng quy trình "PHẠM VI TÔI HIỂU → DUYỆT → BÁO CÁO PHẠM VI" — liệt kê lại ở đây để minh bạch, không phải việc tự ý làm thêm:

| File | Thuộc việc nào | Vì sao không tính vào 5 lỗi |
|---|---|---|
| `docs/review-phase0.md` | Chuẩn bị gói review (yêu cầu trước 5 lỗi) | Đây là NGUYÊN NHÂN sinh ra danh sách lỗi, không phải KẾT QUẢ sửa lỗi |
| `app/Exceptions/IdempotencyPayloadMismatchException.php` | Kiểm nội dung request trùng key (yêu cầu sau 🔴5) | Tính năng mới (chặn cùng key khác nội dung), không phải sửa 1 trong 5 lỗi đã liệt kê |
| `bootstrap/app.php` (phần map `IdempotencyPayloadMismatchException`) | Cùng việc trên | Cùng lý do — phần map `ThrottleRequestsException` trong cùng file thì CÓ thuộc 🔴3 |
| `tests/Feature/Support/IdempotencyMiddlewareTest.php` (test "cùng key khác nội dung" + sửa fixture `body_hash` cho test cũ) | Cùng việc trên | Cùng lý do — 4 test khác trong file này (mục 3) thì CÓ thuộc 🔴1 |
| `app/Support/Money.php` (phần xoá `fromDong`/`add`/`subtract`/`multiply`) | Gộp tên method Money (yêu cầu sau 🔴5) | Dọn dẹp code trùng tên, không phải sửa lỗi — `isLessThan`/`equals` trong cùng file thì CÓ thuộc 🔴2 |
| `app/Support/CashVariance.php` (1 dòng `fromDong`→`fromInt`) | Cùng việc trên | Cùng lý do |
| `tests/Unit/Support/MoneyTest.php` | Cùng việc trên | Viết lại theo tên method mới, không kiểm chứng lỗi nào trong 5 lỗi |
| `tests/Unit/Support/CashVarianceTest.php` (8 chỗ đổi tên gọi) | Cùng việc trên | Cùng lý do |
| `tests/Feature/Database/GeneratedColumnsTest.php` | Kiểm chứng cột tự tính do database (yêu cầu sau 🔴5) | Xác minh một quy tắc khác trong CLAUDE.md (mục 7), không liên quan 5 lỗi |

**Không có file nào bị đụng ngoài phạm vi mà không giải thích được** — mọi thay đổi đều nằm trong một yêu cầu đã được duyệt riêng.

---

## 6. NHỮNG CHỖ KHÔNG CHẮC ĐÃ SỬA ĐÚNG

1. **🔴1 — Lỗi PHP thật sự "chết đột ngột" (fatal, hết bộ nhớ) không được `catch`.** `catch (\Throwable $e)` bắt được mọi exception PHP có thể bắt, nhưng một tiến trình bị kill cứng (OOM killer, `max_execution_time`, mất kết nối MySQL giữa chừng) có thể không kịp chạy tới dòng `$store->forget($cacheKey)`. Rủi ro này được giảm nhẹ bằng `PROCESSING_TTL_SECONDS = 60` (khoá tự hết hạn sau 60 giây dù không ai nhả), nhưng chưa phải "sửa triệt để" — chỉ là giới hạn thời gian kẹt tối đa.

2. **🔴2 — `CashVariance` là value object độc lập, chưa có Action nào thật sự dùng nó.** Chưa có `CloseShift` Action nối `CashVariance::between()` với luồng đóng ca thật (Action đó thuộc Phase sau, ngoài phạm vi 5 lỗi). Test chứng minh class hoạt động đúng về mặt tính toán, nhưng chưa chứng minh được nó *được gọi đúng chỗ* trong nghiệp vụ thật vì chỗ đó chưa tồn tại.

3. **🔴3 — Khoá tạm tính theo người BỊ DÒ (approver), không tính theo cặp (người gọi, người bị dò).** Một người ác ý có thể dò PIN của nhiều thu ngân/chủ quán khác nhau — mỗi người bị dò có bộ đếm riêng, nên về lý thuyết vẫn dò được nhiều PIN trong cùng khung giờ, miễn không vượt quá 20 lần/giờ (rate limit theo người GỌI). Hai lớp phòng thủ cộng lại làm việc dò khó hơn nhiều, nhưng không phải bất khả thi tuyệt đối — đây là đánh đổi có chủ đích (đơn giản, đủ dùng cho quán 5-15 bàn), không phải sơ suất, nhưng cần nói rõ để chủ dự án biết giới hạn thật.

4. **🔴4 — Rào chắn `--with-tests` chỉ kiểm ca đang mở, chưa kiểm các dấu hiệu "quán đang hoạt động" khác.** Ví dụ về lý thuyết: nếu có `table_sessions` đang mở nhưng (do lỗi dữ liệu nào đó) không gắn với `shift` nào đang mở — tình huống này không nên xảy ra theo thiết kế nghiệp vụ hiện tại (mọi `table_session` đều bắt buộc thuộc một `shift`), nhưng lệnh không tự kiểm tra điều đó một cách độc lập; nó tin tưởng hoàn toàn vào bất biến "ca mở ⇔ có thể có giao dịch dở".

5. **🔴5 — Cột `username` của bản ghi cũ không được đổi theo `role`.** Migration mới chỉ đổi cột `role` (`manager`→`cashier`), không đổi `username`. Trên database đã seed từ trước khi sửa, nếu chạy `php artisan migrate` (không phải `migrate:fresh`), bản ghi cũ sẽ có `role='cashier'` nhưng `username` vẫn còn là chữ `'manager'` — sai lệch tên hiển thị dù quyền hạn đã đúng. Đã kiểm chứng đúng như dự đoán này khi chạy `php artisan migrate` (không fresh) trên database dev thật. Không sửa vì đây là dữ liệu, không phải cấu trúc — chủ dự án cần biết để tự đổi `username`/`name` cho các tài khoản cũ nếu áp dụng lên môi trường có dữ liệu thật đang chạy.
