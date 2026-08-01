# CLAUDE.md — QUY ƯỚC DỰ ÁN POS QUÁN NHẬU

File này là **luật của repo**. Claude Code đọc file này mỗi lần khởi động.
Khi hướng dẫn ở đây khác với thói quen mặc định của Laravel hay của AI, **file này thắng**.

Tài liệu nền tảng, đọc khi cần chi tiết:

- `docs/schema.md` — thiết kế cơ sở dữ liệu đầy đủ: 15 bảng, DDL, 40 bất biến, chiến lược khoá. **Đây là nguồn chân lý về dữ liệu.**
- `docs/huong-dan-docker.md` — cách dựng môi trường MySQL bằng Docker và 27 tình huống nghiệp vụ đã kiểm chứng.

---

## 1. TỔNG QUAN DỰ ÁN

Hệ thống bán hàng tại quầy (POS) cho **một quán nhậu Việt Nam**, dùng trên máy tính bảng và máy tính quầy trong mạng nội bộ, không phải sản phẩm SaaS nhiều chi nhánh.
Người dùng: đúng 4 vai trò thật trong code (`UserRole`) — **owner** (chủ quán, toàn quyền), **cashier** (thu ngân, đứng quầy — quán 5-15 bàn không có tầng quản lý riêng nên chính thu ngân là người duyệt giảm giá ≤20%, huỷ món đã phục vụ, void bill, xem báo cáo doanh thu), **staff** (phục vụ, gọi món/mở bàn/thu tiền, không duyệt được việc nhạy cảm), **kitchen** (bếp/quầy pha chế, chỉ đổi trạng thái món trên KDS) — tổng cộng dưới 10 người mỗi ca.
Quy mô thật: **5–15 bàn**, một tối vài trăm dòng món, một máy in bếp và một máy in quầy. Đây là quy mô rất nhỏ với MySQL, nên **ưu tiên đúng và dễ hiểu, không tối ưu sớm**.
Nghiệp vụ cốt lõi: mở lượt khách trên bàn (cho phép ghép bàn) → gọi món nhiều lượt, in tem xuống bếp/quầy → in tạm tính → thu tiền (mặt/chuyển khoản, thu nhiều lần) → đóng ca và đối soát tiền két.
Phase 1 (đang làm) là bán hàng và đối soát ca. Phase 3 sẽ là trừ kho theo định lượng — schema đã chừa chỗ, **không được đụng vào trước khi tới Phase 3**.

---

## 2. STACK

| Thành phần | Phiên bản chốt | Ghi chú |
|---|---|---|
| PHP | 8.2 | Bắt buộc `declare(strict_types=1);` ở đầu mọi file PHP tự viết. Chốt theo PHP thật có trên máy dev (XAMPP), không dùng Sail |
| Laravel | 12.x | Cấu hình ở `bootstrap/app.php`, không phải `app/Http/Kernel.php` |
| MySQL | 8.4 (đã kiểm chứng trên 8.4.11) | InnoDB, `utf8mb4_0900_ai_ci`. Cần MySQL 8 cho generated column + CHECK constraint |
| Pest | 3.x | Framework test duy nhất. Không viết test kiểu PHPUnit class. Chốt bản 3.x vì Pest 4 cần PHP ≥ 8.3, máy dev đang PHP 8.2 |
| Laravel Pint | 1.x | Format code duy nhất. Không dùng php-cs-fixer/ecs riêng |
| Larastan / PHPStan | level 6 | Nếu chưa cài thì hỏi trước khi cài |
| Docker Compose | MySQL 8.4 cổng **3307**, phpMyAdmin cổng **8080** | Cổng 3307 vì máy dev đang có XAMPP chiếm 3306 |
| Timezone | `Asia/Ho_Chi_Minh` | Cả PHP, MySQL và container |
| Tiền tệ | VND, `BIGINT UNSIGNED`, đơn vị **đồng** | Không có số thập phân ở bất kỳ đâu |

Trước khi khẳng định một phiên bản, kiểm tra thật: `php artisan --version`, `composer show laravel/framework pestphp/pest`.

---

## 3. CẤU TRÚC THƯ MỤC

Kiến trúc **Domain + Action**. Nghiệp vụ nằm trong `app/Domain`, Laravel chỉ là lớp vỏ HTTP bọc ngoài.

```
app/
├── Domain/
│   ├── Catalog/                  ← THỰC ĐƠN (thứ gần như không đổi trong đêm)
│   │   ├── Models/               Category, Product, ProductVariant, OptionGroup, Option
│   │   ├── Actions/              CreateProduct, UpdateVariantPrice, DeactivateProduct...
│   │   ├── DTO/                  Dữ liệu đầu vào cho Action
│   │   ├── Enums/                Station (kitchen|bar)
│   │   └── Queries/              Truy vấn đọc phức tạp: dựng thực đơn cho máy POS
│   │
│   ├── Ordering/                 ← BÀN, LƯỢT KHÁCH, GỌI MÓN (trái tim hệ thống)
│   │   ├── Models/               DiningTable, TableSession, TableSessionTable,
│   │   │                         Order, OrderItem, OrderItemOption
│   │   ├── Actions/              OpenTableSession, AttachTable, DetachTable, MoveTable,
│   │   │                         SubmitOrder, CancelOrderItem, SplitOrderItem, MarkServed
│   │   ├── DTO/
│   │   ├── Enums/                TableSessionStatus, OrderStatus, OrderItemStatus
│   │   └── Exceptions/           TableAlreadyOccupied, SessionNotOpen...
│   │
│   ├── Billing/                  ← TÍNH TIỀN, THU TIỀN, IN BILL
│   │   ├── Models/               Payment
│   │   ├── Actions/              RecalculateSessionTotals, ApplyDiscount,
│   │   │                         PrintProvisionalBill, RecordPayment, VoidPayment,
│   │   │                         CloseTableSession
│   │   ├── DTO/
│   │   └── Enums/                PaymentMethod, PaymentStatus
│   │
│   └── Staffing/                 ← NGƯỜI VÀ CA LÀM VIỆC
│       ├── Models/               User, Shift, CashMovement
│       ├── Actions/              OpenShift, CloseShift, RecordCashMovement,
│       │                         CalculateExpectedCash
│       ├── DTO/
│       └── Enums/                UserRole, ShiftStatus, CashDirection
│
├── Support/
│   └── Money.php                 ← MỌI số tiền đi qua đây. Không có ngoại lệ.
│
├── Http/
│   ├── Controllers/Api/          Mỏng. Chỉ validate → gọi Action → trả Resource
│   ├── Requests/                 FormRequest, một class cho một endpoint
│   ├── Resources/                API Resource, quyết định hình dạng JSON trả về
│   └── Middleware/
│
└── Providers/

database/
├── migrations/                   Dịch nguyên văn DDL trong docs/schema.md
├── factories/                    Factory cho mọi Model, dùng trong test
└── seeders/                      Dữ liệu demo: thực đơn quán nhậu, 12 bàn

tests/
├── Feature/                      Test qua HTTP, có database thật. ĐÂY LÀ CHỦ LỰC
└── Unit/                         Chỉ cho Money và các hàm tính toán thuần

docker/mysql/init/01-schema.sql   DDL cho môi trường Docker demo (xem cảnh báo mục 7)
docs/                             Tài liệu thiết kế
```

**Cái gì đặt ở đâu — quy tắc quyết định trong 5 giây:**

| Câu hỏi | Đặt vào |
|---|---|
| Nó *làm* một việc thay đổi dữ liệu? | `Domain/<Nhóm>/Actions/` |
| Nó là *hình dạng dữ liệu đầu vào* của một Action? | `Domain/<Nhóm>/DTO/` |
| Nó là *tập giá trị cố định* (trạng thái, loại)? | `Domain/<Nhóm>/Enums/` |
| Nó chỉ *đọc* dữ liệu để hiển thị, câu truy vấn dài? | `Domain/<Nhóm>/Queries/` |
| Nó dính tới *số tiền*? | `Support/Money.php` |
| Nó dính tới *HTTP*? | `Http/` — và chỉ ở đây |

Món hàng nào không rõ thuộc nhóm nào thì hỏi, đừng tự đoán.

---

## 4. QUY ƯỚC BẮT BUỘC

Đọc như luật giao thông: ngắn, dứt khoát, không có "tuỳ trường hợp".

### Kiến trúc

1. **Controller chỉ làm ba việc**: nhận FormRequest đã validate → gọi đúng **một** Action → trả về Resource. Không `if` nghiệp vụ, không truy vấn database, không tính tiền, không `DB::transaction` trong Controller.
2. **Một Action class làm đúng một việc**, tên là động từ (`RecordPayment`, `OpenTableSession`), có **đúng một** public method `handle()`. Cần bước phụ thì tách thành Action khác và gọi vào, không thêm public method thứ hai.
3. **Action nhận DTO, không nhận `Request`, không nhận array.** DTO là `final readonly class`, có `public static function fromRequest(FormRequest $r): self`.
4. **Model chỉ chứa**: quan hệ, scope, cast, accessor đơn giản, `$fillable`. **Không** chứa logic nghiệp vụ, không tính tiền, không tự đổi trạng thái, không gọi Action.
5. **Không dùng Eloquent Observer, Model event, hay booted() hook** để chạy nghiệp vụ. Nghiệp vụ chạy ngầm là nguyên nhân số một của lỗi khó tìm. Mọi thứ xảy ra phải nhìn thấy trong Action.
6. **Validate chỉ ở FormRequest.** Action tin dữ liệu đã đúng *hình dạng*, nhưng vẫn tự kiểm tra *quy tắc nghiệp vụ* (bàn còn trống không, ca còn mở không) và ném Exception của domain khi vi phạm.

### Tiền

7. **Mọi số tiền là số nguyên đơn vị đồng, kiểu `BIGINT UNSIGNED` trong DB, `int` trong PHP.** Tuyệt đối **không bao giờ** dùng `float`, `double`, `decimal`, không nhân chia ra số lẻ.
8. **Mọi phép tính tiền đi qua `App\Support\Money`.** Không `+`, `-`, `*` trực tiếp trên biến tiền trong Action hay Controller. Money tự chặn kết quả âm.
9. **Mọi thay đổi dữ liệu liên quan tiền phải nằm trong `DB::transaction()`**: thu tiền, huỷ phiếu thu, giảm giá, đóng lượt khách, đóng ca, tính lại tổng tiền. Trong transaction phải `lockForUpdate()` dòng `table_sessions` hoặc `shifts` liên quan trước khi đọc để tính.
10. **Số tiền trên hoá đơn được chốt, không tính lại về sau.** `order_items` luôn lưu bản sao `product_name`, `variant_name`, `unit_price`. Sửa giá trong thực đơn **không** được làm đổi một chữ nào trên hoá đơn cũ.
11. **Mọi Action đụng tiền khoá theo đúng một thứ tự chung: `Shift` → `TableSession` → `Payment`.** Khoá ngược thứ tự nhau giữa hai Action là kẹt chéo (deadlock) — MySQL tự phát hiện và huỷ một bên, nhưng thu ngân nhận lỗi khó hiểu. Cần khoá nhiều dòng `Shift` cùng lúc thì khoá theo `id` tăng dần, giống quy tắc khoá nhiều bàn ở luật 17.

### Dữ liệu

12. **Không xoá cứng dữ liệu giao dịch.** Với `table_sessions`, `table_session_tables`, `orders`, `order_items`, `order_item_options`, `payments`, `shifts`, `cash_movements`: không `delete()`, không `truncate()`, không `forceDelete()`. Huỷ = đổi trạng thái + ghi **ai huỷ, lúc nào, vì sao**. Thiếu một trong ba thì database từ chối.
13. **Danh mục không xoá, chỉ tắt cờ `is_active`**: nhân viên nghỉ việc, bàn dẹp đi, món ngưng bán, biến thể bỏ.
14. **Tên bảng: số nhiều, snake_case** (`table_sessions`, `order_items`). **Tên cột: snake_case** (`opened_by_user_id`, `total_amount`). Khoá ngoại: `<bảng_số_ít>_id`. Tiền: hậu tố `_amount`. Thời điểm: hậu tố `_at`. Cờ: tiền tố `is_`. Người thực hiện: `<động_từ>_by_user_id`.
15. **Mọi khoá ngoại là `ON DELETE RESTRICT`.** Không `CASCADE`, không `SET NULL`.
16. **Trạng thái luôn là PHP Enum backed by string**, cast trong Model. Không so sánh chuỗi trần `=== 'open'` rải rác trong code.
17. **Giữ chỗ nhiều bàn thì luôn khoá theo `dining_table_id` tăng dần** — đây là quy tắc chống kẹt chéo (deadlock) đã chốt ở `docs/schema.md` Phần 6. Không có ngoại lệ.

### Code và test

18. **Mọi Action mới phải có feature test.** Test viết bằng Pest, tiếng Việt trong phần mô tả, đặt trong `tests/Feature/<Nhóm>/`.
19. **Test phải phủ cả đường thất bại**, không chỉ đường thành công: bàn đã có khách, ca đã đóng, thu thiếu tiền, huỷ mà không ghi lý do.
20. **Không viết comment giải thích cú pháp PHP.** Comment chỉ để giải thích *quyết định nghiệp vụ* và viết bằng tiếng Việt.
21. **Chạy `./vendor/bin/pint` trước khi coi một việc là xong.**

---

## 5. FILE MẪU

Sáu file dưới đây là **một lát cắt hoàn chỉnh của cùng một tính năng**: thu tiền cho một lượt khách. Chép mẫu này cho mọi tính năng khác.

### 5.0. `app/Support/Money.php` — nền tảng của mọi phép tính tiền

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

    public static function fromInt(int $amount): self
    {
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

### 5.1. DTO đầu vào — `app/Domain/Billing/DTO/RecordPaymentData.php`

```php
<?php

declare(strict_types=1);

namespace App\Domain\Billing\DTO;

use App\Domain\Billing\Enums\PaymentMethod;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;

final readonly class RecordPaymentData
{
    public function __construct(
        /** Vân tay do máy POS sinh trước khi gửi — chống thu trùng khi bấm hai lần */
        public string $uuid,
        public int $tableSessionId,
        public PaymentMethod $method,
        public Money $amount,
        /** Tiền mặt khách đưa ra. NULL khi chuyển khoản. */
        public ?Money $tenderedAmount,
        public ?string $reference,
        public int $receivedByUserId,
    ) {}

    public static function fromRequest(FormRequest $request): self
    {
        $tendered = $request->integer('tendered_amount');

        return new self(
            uuid: $request->string('uuid')->toString(),
            tableSessionId: (int) $request->route('table_session')->id,
            method: PaymentMethod::from($request->string('method')->toString()),
            amount: Money::fromInt($request->integer('amount')),
            tenderedAmount: $request->filled('tendered_amount')
                ? Money::fromInt($tendered)
                : null,
            reference: $request->input('reference'),
            receivedByUserId: (int) $request->user()->id,
        );
    }
}
```

### 5.2. Action — `app/Domain/Billing/Actions/RecordPayment.php`

```php
<?php

declare(strict_types=1);

namespace App\Domain\Billing\Actions;

use App\Domain\Billing\DTO\RecordPaymentData;
use App\Domain\Billing\Enums\PaymentMethod;
use App\Domain\Billing\Enums\PaymentStatus;
use App\Domain\Billing\Models\Payment;
use App\Domain\Ordering\Enums\TableSessionStatus;
use App\Domain\Ordering\Models\TableSession;
use App\Domain\Staffing\Enums\ShiftStatus;
use App\Domain\Staffing\Models\Shift;
use App\Exceptions\DomainException;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Ghi nhận MỘT lần khách trả tiền cho một lượt khách.
 *
 * Cho phép thu nhiều lần: khách đưa 500k tiền mặt, phần còn lại chuyển khoản.
 * Khi tổng đã thu đủ tiền, lượt khách tự động đóng và mọi bàn được nhả ra ngay.
 */
final class RecordPayment
{
    public function __construct(
        private readonly CloseTableSession $closeTableSession,
    ) {}

    public function handle(RecordPaymentData $data): Payment
    {
        return DB::transaction(function () use ($data): Payment {
            // Bấm thu hai lần vì mạng lag: trả về đúng phiếu thu cũ, không ghi nhận lần hai.
            $existing = Payment::query()->where('uuid', $data->uuid)->first();
            if ($existing !== null) {
                return $existing;
            }

            // Khoá dòng lượt khách TRƯỚC KHI đọc số tiền, để hai thu ngân bấm cùng lúc
            // không cùng đọc được "còn thiếu 200k" rồi cùng ghi nhận.
            $session = TableSession::query()
                ->lockForUpdate()
                ->findOrFail($data->tableSessionId);

            if (! in_array($session->status, [TableSessionStatus::Open, TableSessionStatus::Billing], true)) {
                throw new DomainException('Lượt khách này đã đóng hoặc đã huỷ, không thu tiền được.');
            }

            $shift = Shift::query()->where('status', ShiftStatus::Open)->lockForUpdate()->first();
            if ($shift === null) {
                throw new DomainException('Chưa mở ca. Phải mở ca trước khi thu tiền.');
            }

            $total = Money::fromInt($session->total_amount);
            $paid = Money::fromInt($session->paid_amount);
            $remaining = $total->minus($paid);

            if ($remaining->isZero()) {
                throw new DomainException('Lượt khách này đã thu đủ tiền.');
            }

            if (! $remaining->isAtLeast($data->amount)) {
                throw new DomainException(
                    "Thu quá số còn thiếu. Còn thiếu {$remaining->format()}, đang thu {$data->amount->format()}."
                );
            }

            $change = Money::zero();
            if ($data->method === PaymentMethod::Cash) {
                if ($data->tenderedAmount === null) {
                    throw new DomainException('Thu tiền mặt phải ghi số tiền khách đưa.');
                }
                // Ràng buộc ck_payments_cash: khách đưa = ghi nhận + thối lại
                $change = $data->tenderedAmount->minus($data->amount);
            }

            $payment = Payment::query()->create([
                'uuid' => $data->uuid,
                'table_session_id' => $session->id,
                'shift_id' => $shift->id,
                'method' => $data->method,
                'amount' => $data->amount->amount,
                'tendered_amount' => $data->tenderedAmount?->amount,
                'change_amount' => $change->amount,
                'reference' => $data->reference,
                'status' => PaymentStatus::Completed,
                'received_by_user_id' => $data->receivedByUserId,
                'paid_at' => now(),
            ]);

            $session->update([
                'paid_amount' => $paid->plus($data->amount)->amount,
                'status' => TableSessionStatus::Billing,
            ]);

            // Thu đủ thì đóng lượt khách và nhả bàn — bàn trống ngay cho khách sau.
            if (Money::fromInt($session->paid_amount)->isAtLeast($total)) {
                $this->closeTableSession->handle($session, $data->receivedByUserId);
            }

            return $payment;
        });
    }
}
```

### 5.3. FormRequest — `app/Http/Requests/StorePaymentRequest.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Billing\Enums\PaymentMethod;
use App\Domain\Staffing\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()->role, [UserRole::Owner, UserRole::Cashier], true);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'uuid' => ['required', 'uuid'],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'amount' => ['required', 'integer', 'min:1'],
            'tendered_amount' => ['required_if:method,cash', 'nullable', 'integer', 'min:1', 'gte:amount'],
            'reference' => ['nullable', 'string', 'max:100'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'uuid.required' => 'Thiếu mã vân tay của phiếu thu.',
            'amount.min' => 'Số tiền thu phải lớn hơn 0.',
            'tendered_amount.required_if' => 'Thu tiền mặt phải ghi số tiền khách đưa.',
            'tendered_amount.gte' => 'Tiền khách đưa không được ít hơn số tiền thu.',
        ];
    }

    /** Chuẩn hoá: chuyển khoản thì không có "tiền khách đưa" (ràng buộc ck_payments_cash) */
    protected function prepareForValidation(): void
    {
        if ($this->input('method') === PaymentMethod::Transfer->value) {
            $this->merge(['tendered_amount' => null]);
        }
    }
}
```

### 5.4. API Resource — `app/Http/Resources/PaymentResource.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Domain\Billing\Models\Payment */
final class PaymentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'method' => $this->method->value,
            'method_label' => $this->method->label(),

            // Trả cả số nguyên (để máy tính) và chuỗi đã định dạng (để in ra màn hình).
            // Không bao giờ trả số thực.
            'amount' => $this->amount,
            'amount_text' => Money::fromInt($this->amount)->format(),
            'tendered_amount' => $this->tendered_amount,
            'change_amount' => $this->change_amount,
            'change_amount_text' => Money::fromInt($this->change_amount)->format(),

            'reference' => $this->reference,
            'status' => $this->status->value,
            'paid_at' => $this->paid_at->toIso8601String(),
            'received_by' => $this->whenLoaded('receivedBy', fn () => [
                'id' => $this->receivedBy->id,
                'name' => $this->receivedBy->name,
            ]),

            'session' => $this->whenLoaded('tableSession', fn () => [
                'id' => $this->tableSession->id,
                'code' => $this->tableSession->code,
                'status' => $this->tableSession->status->value,
                'total_amount' => $this->tableSession->total_amount,
                'paid_amount' => $this->tableSession->paid_amount,
                'remaining_amount' => $this->tableSession->total_amount - $this->tableSession->paid_amount,
            ]),
        ];
    }
}
```

### 5.5. Controller — `app/Http/Controllers/Api/PaymentController.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Billing\Actions\RecordPayment;
use App\Domain\Billing\DTO\RecordPaymentData;
use App\Domain\Ordering\Models\TableSession;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentRequest;
use App\Http\Resources\PaymentResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class PaymentController extends Controller
{
    /**
     * POST /api/table-sessions/{table_session}/payments
     *
     * Cả Controller này chỉ có ba dòng, và đó là chuẩn: validate đã xong ở
     * StorePaymentRequest, nghiệp vụ nằm trong RecordPayment, hình dạng JSON
     * nằm trong PaymentResource.
     */
    public function store(
        StorePaymentRequest $request,
        TableSession $tableSession,
        RecordPayment $action,
    ): JsonResponse {
        $payment = $action->handle(RecordPaymentData::fromRequest($request));

        return PaymentResource::make($payment->load(['tableSession', 'receivedBy']))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
```

> Toàn bộ Controller trong dự án này phải ngắn cỡ đó. **Thấy mình bắt đầu viết `if`, `DB::`, hay tính toán trong Controller nghĩa là việc đó thuộc Action.**
> Exception nghiệp vụ (`App\Exceptions\DomainException`) được đổi thành HTTP 422 ở một chỗ duy nhất: phần `withExceptions` trong `bootstrap/app.php`. Không `try/catch` trong Controller.

### 5.6. Feature test (Pest) — `tests/Feature/Billing/RecordPaymentTest.php`

```php
<?php

declare(strict_types=1);

use App\Domain\Billing\Enums\PaymentMethod;
use App\Domain\Billing\Models\Payment;
use App\Domain\Ordering\Enums\TableSessionStatus;
use App\Domain\Ordering\Models\TableSession;
use App\Domain\Staffing\Models\Shift;
use App\Domain\Staffing\Models\User;

beforeEach(function () {
    $this->cashier = User::factory()->cashier()->create();
    $this->shift = Shift::factory()->open()->create(['opened_by_user_id' => $this->cashier->id]);

    $this->session = TableSession::factory()
        ->for($this->shift)
        ->withTable()
        ->create([
            'subtotal_amount' => 500_000,
            'discount_amount' => 0,
            'total_amount' => 500_000,
            'paid_amount' => 0,
            'status' => TableSessionStatus::Billing,
        ]);
});

function thuTien(TableSession $session, array $payload = []): \Illuminate\Testing\TestResponse
{
    return test()->postJson("/api/table-sessions/{$session->id}/payments", array_merge([
        'uuid' => (string) Str::uuid(),
        'method' => PaymentMethod::Cash->value,
        'amount' => 500_000,
        'tendered_amount' => 500_000,
    ], $payload));
}

it('thu tiền mặt đủ thì đóng lượt khách và nhả bàn ra ngay', function () {
    $this->actingAs($this->cashier);

    thuTien($this->session)
        ->assertCreated()
        ->assertJsonPath('data.amount', 500_000)
        ->assertJsonPath('data.change_amount', 0);

    $this->session->refresh();
    expect($this->session->paid_amount)->toBe(500_000)
        ->and($this->session->status)->toBe(TableSessionStatus::Closed)
        ->and($this->session->closed_at)->not->toBeNull()
        ->and($this->session->tables()->whereNull('detached_at')->count())->toBe(0);
});

it('khách đưa 600k thì thối lại 100k, doanh thu vẫn ghi 500k', function () {
    $this->actingAs($this->cashier);

    thuTien($this->session, ['tendered_amount' => 600_000])->assertCreated();

    $payment = Payment::query()->sole();
    expect($payment->amount)->toBe(500_000)
        ->and($payment->change_amount)->toBe(100_000)
        ->and($payment->tendered_amount)->toBe(600_000);
});

it('thu hai lần với cùng mã vân tay chỉ ghi nhận một phiếu thu', function () {
    $this->actingAs($this->cashier);
    $uuid = (string) Str::uuid();

    thuTien($this->session, ['uuid' => $uuid, 'amount' => 200_000, 'tendered_amount' => 200_000])->assertCreated();
    thuTien($this->session, ['uuid' => $uuid, 'amount' => 200_000, 'tendered_amount' => 200_000])->assertSuccessful();

    expect(Payment::query()->count())->toBe(1);
    expect($this->session->refresh()->paid_amount)->toBe(200_000);
});

it('thu thiếu tiền thì lượt khách vẫn mở, chưa đóng', function () {
    $this->actingAs($this->cashier);

    thuTien($this->session, ['amount' => 300_000, 'tendered_amount' => 300_000])->assertCreated();

    $this->session->refresh();
    expect($this->session->paid_amount)->toBe(300_000)
        ->and($this->session->status)->toBe(TableSessionStatus::Billing);
});

it('không cho thu quá số tiền còn thiếu', function () {
    $this->actingAs($this->cashier);

    thuTien($this->session, ['amount' => 700_000, 'tendered_amount' => 700_000])
        ->assertUnprocessable();

    expect(Payment::query()->count())->toBe(0);
});

it('chuyển khoản không có tiền khách đưa và không có tiền thối', function () {
    $this->actingAs($this->cashier);

    thuTien($this->session, [
        'method' => PaymentMethod::Transfer->value,
        'tendered_amount' => null,
        'reference' => 'FT26073012345',
    ])->assertCreated();

    $payment = Payment::query()->sole();
    expect($payment->tendered_amount)->toBeNull()
        ->and($payment->change_amount)->toBe(0)
        ->and($payment->reference)->toBe('FT26073012345');
});

it('chưa mở ca thì không thu được tiền', function () {
    $this->shift->update(['status' => \App\Domain\Staffing\Enums\ShiftStatus::Closed, /* ... */]);
    $this->actingAs($this->cashier);

    thuTien($this->session)->assertUnprocessable();
});

it('phục vụ không có quyền thu tiền', function () {
    $this->actingAs(User::factory()->waiter()->create());

    thuTien($this->session)->assertForbidden();
});
```

### 5.7. Migration — `database/migrations/2026_07_30_000015_create_payments_table.php`

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bảng 15 trong docs/schema.md — CÁC LẦN THU TIỀN.
 *
 * Ba ràng buộc CHECK ở cuối file phải viết bằng SQL thô vì Laravel Blueprint
 * chưa hỗ trợ CHECK. Chúng là luật cứng, không phải trang trí:
 *   - ck_payments_amount : không có phiếu thu 0 đồng
 *   - ck_payments_cash   : khách đưa = ghi nhận + thối lại (T7, T8)
 *   - ck_payments_void   : huỷ phiếu thu phải đủ ai/khi nào/vì sao (H2)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            // Vân tay do máy POS sinh trước khi gửi — chống thu trùng (T9)
            $table->char('uuid', 36)->charset('ascii')->collation('ascii_bin')->unique('uq_payments_uuid');

            $table->foreignId('table_session_id')->constrained('table_sessions')->restrictOnDelete();
            $table->foreignId('shift_id')->comment('Thu trong ca nào — để đối soát cuối ca')
                ->constrained('shifts')->restrictOnDelete();

            $table->enum('method', ['cash', 'transfer']);
            $table->unsignedBigInteger('amount')->comment('Số tiền GHI NHẬN vào doanh thu (đồng)');
            $table->unsignedBigInteger('tendered_amount')->nullable()->comment('Tiền mặt khách đưa (đồng)');
            $table->unsignedBigInteger('change_amount')->default(0)->comment('Tiền thối lại (đồng)');
            $table->string('reference', 100)->nullable()->comment('Mã giao dịch chuyển khoản');

            $table->enum('status', ['completed', 'voided'])->default('completed');
            $table->foreignId('received_by_user_id')->constrained('users')->restrictOnDelete();
            $table->dateTime('paid_at');

            $table->foreignId('voided_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('voided_at')->nullable();
            $table->string('void_reason', 255)->nullable();

            $table->timestamps();

            $table->index(['table_session_id', 'status'], 'idx_payments_session');
            $table->index(['shift_id', 'status', 'method'], 'idx_payments_shift_recon');
            $table->index('paid_at', 'idx_payments_paid_at');
        });

        DB::statement('ALTER TABLE payments ADD CONSTRAINT ck_payments_amount CHECK (amount > 0)');

        DB::statement(<<<'SQL'
            ALTER TABLE payments ADD CONSTRAINT ck_payments_cash CHECK (
                (method = 'cash'     AND tendered_amount IS NOT NULL AND tendered_amount = amount + change_amount)
             OR (method = 'transfer' AND tendered_amount IS NULL     AND change_amount = 0)
            )
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE payments ADD CONSTRAINT ck_payments_void CHECK (
                status <> 'voided'
                OR (voided_at IS NOT NULL AND void_reason IS NOT NULL AND voided_by_user_id IS NOT NULL)
            )
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
```

**Ghi chú về generated column** (dùng ở `table_session_tables.occupied_table_id`, `shifts.open_guard`, `order_items.line_amount`):

```php
$table->unsignedBigInteger('occupied_table_id')
    ->storedAs('IF(detached_at IS NULL, dining_table_id, NULL)')
    ->nullable();
$table->unique('occupied_table_id', 'uq_tst_one_session_per_table');
```

---

## 6. LỆNH THƯỜNG DÙNG

```bash
# ── DATABASE (Docker) ────────────────────────────────────────────────
docker compose up -d                  # Bật MySQL 8.4 (cổng 3307) + phpMyAdmin (cổng 8080)
docker compose ps                     # Xem container còn sống không
docker compose logs -f mysql          # Xem log MySQL
docker compose down                   # Tắt, GIỮ dữ liệu
# docker compose down -v              # XOÁ LUÔN DỮ LIỆU — xem mục 7, phải hỏi trước

# ── CHẠY DEV ─────────────────────────────────────────────────────────
composer install
php artisan serve                     # API ở http://localhost:8000
npm install && npm run dev            # Giao diện (Vite)
php artisan queue:listen --tries=1    # Nếu có job in tem chạy nền

# ── MIGRATION & DỮ LIỆU MẪU ──────────────────────────────────────────
php artisan migrate                   # Chạy migration mới
php artisan migrate:status            # Cái nào đã chạy, cái nào chưa
php artisan db:seed                   # Nạp thực đơn + bàn demo
# php artisan migrate:fresh --seed    # XOÁ SẠCH rồi dựng lại — chỉ dùng ở máy dev

# ── TEST ─────────────────────────────────────────────────────────────
./vendor/bin/pest                             # Chạy toàn bộ
./vendor/bin/pest tests/Feature/Billing       # Chạy một nhóm
./vendor/bin/pest --filter="thu tiền mặt"     # Chạy một test theo tên
./vendor/bin/pest --parallel                  # Chạy song song cho nhanh
./vendor/bin/pest --coverage                  # Xem độ phủ

# ── CHẤT LƯỢNG CODE ──────────────────────────────────────────────────
./vendor/bin/pint                     # Format code (BẮT BUỘC trước khi báo xong)
./vendor/bin/pint --test              # Chỉ kiểm tra, không sửa
./vendor/bin/phpstan analyse          # Phân tích tĩnh, nếu đã cài

# ── TRA CỨU NHANH ────────────────────────────────────────────────────
php artisan route:list --path=api     # Danh sách endpoint
php artisan tinker                    # Thử câu lệnh trực tiếp
php artisan about                     # Phiên bản, cấu hình đang dùng
```

Kết nối MySQL trong `.env`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3307
DB_DATABASE=quan_pos
DB_USERNAME=quanpos
DB_PASSWORD=quanpos_secret
```

---

## 7. ĐIỀU KHÔNG ĐƯỢC LÀM

Những việc dưới đây **Claude Code không được tự ý làm**. Gặp là **dừng, hỏi chủ dự án, chờ trả lời**.

### Về schema và migration

1. **Không sửa file migration đã chạy trên production.** Đã chạy rồi thì sửa file đó vô nghĩa và nguy hiểm. Cần đổi thì tạo migration mới.
2. **Không đổi schema mà không hỏi** — thêm/xoá/đổi tên bảng, cột, index, ràng buộc, enum. Schema đã được thiết kế, kiểm chứng thật trên MySQL 8.4.11 và ghi thành tài liệu. Muốn đổi thì trình bày lý do trước, được đồng ý mới làm, và **cập nhật đồng thời cả ba nơi**: `docs/schema.md`, `database/migrations/`, `docker/mysql/init/01-schema.sql`.
3. **Không chạy `php artisan migrate:fresh`, `migrate:refresh`, `migrate:rollback`, `db:wipe` trên bất kỳ database nào không phải máy dev của chính mình.**
4. **Không bỏ ràng buộc CHECK, khoá UNIQUE hay khoá ngoại cho "code chạy được".** Test đỏ vì vướng ràng buộc nghĩa là code sai, không phải ràng buộc sai. Đặc biệt không được đụng tới `uq_tst_one_session_per_table` và `uq_shifts_only_one_open` — đó là hai chốt chặn quan trọng nhất hệ thống.
5. **Không đụng vào ba cột chừa cho Phase 3** (`tracks_inventory`, `stock_unit`, `stock_factor`) và không tạo bảng kho (`ingredients`, `recipes`, `stock_entries`) trước khi Phase 3 chính thức bắt đầu.

### Về dữ liệu

6. **Không chạy lệnh xoá dữ liệu**: `DELETE`, `TRUNCATE`, `DROP`, `docker compose down -v`, xoá volume `quanpos_mysql_data`, `Model::truncate()`, `forceDelete()`. Cần dữ liệu sạch để test thì dùng database test riêng.
7. **Không xoá cứng dữ liệu giao dịch trong code**, kể cả khi đề bài nói "xoá". Trong quán, "xoá" luôn có nghĩa là **huỷ có ghi lý do**.
8. **Không viết seeder hay script ghi vào database production.**
9. **Không tự sửa dữ liệu bằng SQL tay để "chữa" một lỗi.** Báo lỗi cho chủ dự án, đề xuất cách sửa.

### Về phụ thuộc và cấu hình

10. **Không cài package mới mà không hỏi** — không `composer require`, không `npm install <tên package>`. Mỗi package là một thứ phải bảo trì mãi mãi. Đề xuất kèm lý do và nói rõ nếu Laravel đã có sẵn tính năng đó.
11. **Không nâng phiên bản** PHP, Laravel, MySQL, hay bất kỳ dependency nào.
12. **Không sửa `.env`, `docker-compose.yml`, `composer.json`, `package.json`, cấu hình CI** mà không hỏi.
13. **Không đưa mật khẩu, token, mã bí mật vào code.** Chỉ đọc từ `.env` qua `config()`.

### Về cách làm việc

14. **Không tự mở rộng phạm vi.** Được nhờ sửa chức năng thu tiền thì đừng nhân tiện tái cấu trúc luôn phần gọi món. Thấy chỗ khác có vấn đề thì **báo**, đừng tự sửa.
15. **Không xoá hay tắt (`skip`, comment) test đang đỏ** để mọi thứ trông xanh.
16. **Không dùng `float` cho tiền, không `config(['app.debug' => ...])` để lách lỗi, không `@` để chặn cảnh báo, không `try/catch` rỗng.**
17. **Không đẩy code lên remote** (`git push`), không tạo pull request, không tag release mà không được yêu cầu rõ ràng.
18. **Không nói "đã xong" khi chưa chạy test và chưa chạy Pint.** Test đỏ thì nói thẳng là đỏ, dán nguyên văn thông báo lỗi.

---

## 8. GHI CHÚ CHO AI

**Chủ dự án không phải lập trình viên.** Người đọc kết quả của bạn là chủ một quán nhậu, hiểu rất rõ nghiệp vụ quán nhưng không đọc được code, không biết "dependency injection" hay "transaction isolation" là gì. Cách bạn giải thích quyết định việc anh ấy có kiểm soát được dự án của mình hay không.

**Sau mỗi việc, bắt buộc báo cáo bằng tiếng Việt đơn giản, theo đúng bốn phần:**

```
✅ ĐÃ LÀM GÌ
   Nói bằng ngôn ngữ quán, không bằng ngôn ngữ code.
   Nên: "Giờ thu ngân bấm thu tiền hai lần vì mạng lag thì máy chỉ ghi nhận
         một lần, không cộng tiền hai lượt."
   Không nên: "Đã implement idempotency key trong RecordPayment action."

📁 SỬA NHỮNG FILE NÀO
   Liệt kê từng file kèm một câu nó làm gì.

🔍 KIỂM TRA THẾ NÀO
   Hướng dẫn từng bước bấm chuột/gõ lệnh để anh tự thấy kết quả.
   Nên: "1. Mở http://localhost:8000, đăng nhập bằng tài khoản thungan
         2. Mở bàn 3, gọi 2 lon Tiger
         3. Bấm Thu tiền, nhập 100.000
         4. Bấm Thu tiền lần nữa — phải thấy báo 'Bàn này đã thu đủ tiền'"
   Kèm cả cách chạy test: `./vendor/bin/pest --filter="thu tiền"` — 8 test phải xanh.

⚠️ CẦN ANH QUYẾT
   Chỗ nào bạn phải tự đoán, chỗ nào có nhiều cách làm, chỗ nào thấy rủi ro.
   Không có gì thì ghi "Không có".
```

**Nguyên tắc giao tiếp:**

- **Viết tiếng Việt.** Tên biến, tên class, tên bảng giữ tiếng Anh; mọi câu giải thích viết tiếng Việt.
- **Dùng ẩn dụ của quán**, không dùng ẩn dụ của máy tính. "Lượt khách" chứ không phải "session entity". "Sổ ghi thêm không tẩy xoá" chứ không phải "append-only log".
- **Không dùng thuật ngữ mà không giải thích.** Buộc phải dùng thì thêm một câu ngoặc: "transaction (nghĩa là: hoặc làm trọn cả gói, hoặc không làm gì cả — không có nửa vời)".
- **Báo thất bại thẳng thắn và ngay lập tức.** Test đỏ, một phần việc chưa làm được, có chỗ đang đoán — nói ra, kèm nguyên văn thông báo lỗi. Không tô hồng, không "đã xong về cơ bản".
- **Không kê thành tích.** Không cần khen thiết kế, không cần tóm tắt lại yêu cầu. Vào thẳng việc.
- **Thấy yêu cầu sai nghiệp vụ thì nói ngay, một hai câu, rồi hỏi.** Ví dụ được nhờ "xoá món khách trả lại" — phải nói ngay là quán không xoá, quán huỷ có ghi lý do, và hỏi có đúng ý không. Chủ dự án hiểu nghiệp vụ hơn bạn; bạn hiểu hệ quả kỹ thuật hơn. Nói ra để anh ấy quyết.
- **Nghi ngờ thì hỏi, đừng đoán.** Một câu hỏi mất 30 giây. Một giả định sai về tiền bạc có thể mất cả buổi để dò ra.
