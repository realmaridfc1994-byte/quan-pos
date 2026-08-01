# REVIEW PHASE 1 — TOÀN BỘ (Bước 11)

File này chỉ để đọc — không có sửa code nào đi kèm. Viết theo yêu cầu ở
`docs/PHASE.md` Bước 11 ("Opus review toàn phase — Hết mục 🔴").

Thời điểm viết: 2026-08-01. `./vendor/bin/pest` chạy toàn bộ ngay trước khi
viết báo cáo này: **284 passed (901 assertions)**, không có test nào đỏ hoặc
bị skip.

Ghi chú quan trọng về trạng thái git: Bước 2–7 nằm chung trong một commit
squash duy nhất (`6c5e370 feat(p1): buoc 7 - tinh tien va thu tien`) — lịch
sử git **không** tách được file theo từng bước 2–7. Danh sách ở Phần 1 dưới
đây nhóm theo **chức năng nghiệp vụ** đối chiếu với bảng tra trong
`docs/PHASE.md`, không phải theo commit. Bước 8 (đóng ca & đối soát) và Bước
10 (màn hình POS/KDS) hiện **chưa commit** — đang nằm trong working tree
(`git status`).

---

## 1. Danh sách file đã tạo ở Phase 1, nhóm theo bước

### Nền Phase 0 (không tính vào Bước nào của Phase 1, nhưng Phase 1 dùng lại)

Đăng nhập, PIN duyệt, và bộ khung Action/Money/Idempotency được dựng ở Phase
0 (commit `775aaa9`, `69ff13f`), **trước** khi `docs/PHASE.md` Phase 1 mở.
Liệt kê ở đây để không lẫn vào Bước 1, vì Bước 1 trong bảng tra chỉ ghi "Ca
làm việc", không có đăng nhập.

- `app/Domain/Staffing/Actions/{AuthenticateUser,RevokeCurrentToken,VerifyApproverPin}.php`
- `app/Domain/Staffing/DTO/{LoginData,LogoutData,PinVerifyData,AuthenticatedSession}.php`
- `app/Domain/Staffing/Models/User.php`, `Enums/UserRole.php`, `Policies/UserPolicy.php`
- `app/Http/Controllers/Api/AuthController.php`, `Requests/{LoginRequest,PinVerifyRequest}.php`, `Resources/UserResource.php`
- `app/Http/Middleware/{EnsureUserIsActive,EnsureIdempotencyKey}.php`
- `app/Support/{Money,Action,StatusTransition}.php`
- `database/migrations/2026_07_31_000001_create_users_table.php`, `..._081235_create_personal_access_tokens_table.php`, `..._0812{40,41,42}_*activity_log*.php`, `..._000016_create_cache_table.php`
- `app/Console/Commands/Phase0Check.php`
- `tests/Feature/Staffing/Auth/{LoginTest,LogoutTest,PinVerifyTest,ActiveUserMiddlewareTest}.php`, `tests/Feature/Support/{AuthGuardIsolationTest,IdempotencyMiddlewareTest}.php`, `tests/Feature/Console/Phase0CheckTest.php`, `tests/Unit/Support/MoneyTest.php`, `tests/Feature/Database/{GeneratedColumnsTest,MigrationRollbackTest,SeederRerunTest}.php`

### Bước 1 — Ca làm việc

- `app/Domain/Staffing/Actions/{OpenShift,RecordCashMovement}.php` (`CloseShift.php` liệt kê lại ở Bước 8 — khung ban đầu dựng ở đây, công thức C4 thật hoàn thiện ở Bước 8)
- `app/Domain/Staffing/DTO/{OpenShiftData,RecordCashMovementData}.php`, `Enums/{ShiftStatus,CashDirection}.php`, `Models/{Shift,CashMovement}.php`, `Policies/ShiftPolicy.php`
- `app/Http/Controllers/Api/{ShiftController,CashMovementController}.php` (`open`/`current`), `Requests/{OpenShiftRequest,RecordCashMovementRequest}.php`, `Resources/{ShiftResource,CashMovementResource}.php`
- `database/migrations/2026_07_31_000002_create_shifts_table.php`, `..._000003_create_cash_movements_table.php`
- `database/factories/{ShiftFactory,CashMovementFactory}.php`
- `tests/Feature/Staffing/Shift/{OpenShiftTest,RecordCashMovementTest}.php`
- `app/Console/Commands/PosDemo.php` (khung `--den=ca`)

### Bước 2 — Thực đơn

- `app/Domain/Catalog/Models/{Category,Product,ProductVariant,OptionGroup,Option}.php`
- `app/Domain/Catalog/Actions/{SetDefaultProductVariant,ToggleCategoryActive,ToggleOptionActive,ToggleOptionGroupActive,ToggleProductActive,ToggleProductVariantActive}.php`
- `app/Domain/Catalog/Policies/{CategoryPolicy,OptionGroupPolicy,OptionPolicy,ProductPolicy,ProductVariantPolicy}.php`, `Enums/Station.php`, `Queries/BuildMenu.php`
- `app/Filament/Resources/{CategoryResource,OptionGroupResource,OptionResource,ProductResource,ProductVariantResource}.php` (+ `Pages/Manage*.php`), `app/Filament/Pages/Auth/Login.php`, `app/Providers/Filament/AdminPanelProvider.php`
- `app/Http/Controllers/Api/MenuController.php`, `Requests/MenuRequest.php`, `Resources/{MenuCategoryResource,MenuProductResource,MenuVariantResource,MenuOptionGroupResource,MenuOptionResource}.php`
- `database/migrations/2026_07_31_00000{7,8,9,10,11}_create_*.php` (categories, products, product_variants, option_groups, options)
- `database/factories/{CategoryFactory,ProductFactory,ProductVariantFactory,OptionGroupFactory,OptionFactory}.php`
- `tests/Feature/Catalog/{FilamentAccessTest,FilamentLoginTest,FilamentNoDeleteButtonTest,MenuTest,OptionGroupScopeTest,ProductVariantInvariantsTest}.php`, `tests/Unit/Catalog/ProductEffectiveStationTest.php`

### Bước 3 — Bàn & lượt khách

- `app/Domain/Ordering/Models/{DiningTable,TableSession,TableSessionTable}.php`, `Enums/TableSessionStatus.php`
- `app/Domain/Ordering/Actions/{OpenTableSession,AttachTable,DetachTable,TransferTable,CloseTableSession,VoidTableSession,RecalculateSessionSubtotal}.php`
- `app/Domain/Ordering/Policies/{DiningTablePolicy,TableSessionPolicy}.php`, `Queries/{GetFloorPlan,GetTableSessionDetail}.php`
- `app/Http/Controllers/Api/{TableSessionController,FloorPlanController}.php`, `Requests/{OpenTableSessionRequest,AttachTableRequest,DetachTableRequest,TransferTableRequest,CloseTableSessionRequest,VoidTableSessionRequest}.php`, `Resources/{TableSessionResource,FloorPlanTableResource}.php`
- `database/migrations/2026_07_31_00000{4,5,6}_create_*.php` (dining_tables, table_sessions, table_session_tables)
- `database/factories/{DiningTableFactory,TableSessionFactory,TableSessionTableFactory}.php`
- `tests/Feature/Ordering/{OpenTableSessionTest,AttachTableTest,DetachTableTest,TransferTableTest,CloseTableSessionTest,VoidTableSessionTest,TableConcurrencyTest,TableSessionShowTest}.php`

### Bước 4 — Gọi món

- `app/Domain/Ordering/Actions/{PlaceOrder,UpdateOrderItem,RemoveOrderItem}.php`, `Models/{Order,OrderItem,OrderItemOption}.php`, `Enums/{OrderStatus,OrderItemStatus}.php`, `Policies/{OrderPolicy,OrderItemPolicy}.php` (một phần — `updateStatus` thuộc Bước 5/6)
- `app/Http/Controllers/Api/OrderController.php` (`store`), `OrderItemController.php` (`update`/`destroy`), `Requests/{PlaceOrderRequest,UpdateOrderItemRequest,RemoveOrderItemRequest}.php`, `Resources/{OrderResource,OrderItemResource,OrderItemOptionResource}.php`
- `database/migrations/2026_07_31_0000{12,13,14}_create_*.php` (orders, order_items, order_item_options)
- `database/factories/{OrderFactory,OrderItemFactory,OrderItemOptionFactory}.php`
- `tests/Feature/Ordering/{PlaceOrderTest,UpdateOrderItemTest,RemoveOrderItemTest}.php`

### Bước 5 — Gửi bếp & màn hình bếp (API)

- `app/Domain/Ordering/Actions/{SendToKitchen,UpdateOrderItemStatus}.php`, `Events/OrderSentToKitchen.php`, `Queries/GetKdsTickets.php`
- `app/Http/Controllers/Api/OrderController.php` (`send`), `KdsController.php`, `Requests/{SendToKitchenRequest,KdsTicketsRequest,UpdateOrderItemStatusRequest}.php`, `Resources/{KdsTicketResource,KdsTicketItemResource}.php`
- `tests/Feature/Ordering/{SendToKitchenTest,KdsTicketsTest,UpdateOrderItemStatusTest}.php`

### Bước 6 — Hủy món & duyệt PIN

- `app/Domain/Ordering/Actions/CancelOrderItem.php` (dùng lại `VerifyApproverPin` của Phase 0)
- `app/Http/Controllers/Api/OrderItemController.php` (`cancel`), `Requests/CancelOrderItemRequest.php`
- `tests/Feature/Ordering/CancelOrderItemTest.php`

### Bước 7 — Tính tiền & thu tiền

- `app/Domain/Billing/Actions/{CalculateBill,RecordPayment,VoidPayment}.php`, `DTO/{CalculateBillData,RecordPaymentData,VoidPaymentData}.php`, `Enums/{PaymentMethod,PaymentStatus}.php`, `Models/Payment.php`, `Policies/PaymentPolicy.php`, `Queries/GetSessionBill.php`
- `app/Http/Controllers/Api/{BillController,PaymentController}.php`, `TableSessionController.php` (`discount`), `Requests/{CalculateBillRequest,RecordPaymentRequest,VoidPaymentRequest}.php`, `Resources/{BillResource,PaymentResource}.php`
- `database/migrations/2026_07_31_000015_create_payments_table.php`
- `database/factories/PaymentFactory.php`
- `tests/Feature/Billing/{CalculateBillTest,RecordPaymentTest,VoidPaymentTest,VoidPaymentReopenWithoutTableTest,GetSessionBillTest,PaymentConcurrencyTest}.php`

### Bước 8 — Đóng ca & đối soát *(CHƯA COMMIT — trong working tree)*

- `app/Domain/Staffing/Actions/CloseShift.php` — sửa: thêm kiểm C3 thật (nêu tên bàn còn vướng) + công thức C4 thật (trước đó luôn ra 0 vì chưa có `Payment`)
- `app/Domain/Staffing/Queries/GetShiftReport.php` *(mới)*, `app/Support/CashVariance.php`
- `app/Http/Controllers/Api/ShiftController.php` — sửa: thêm `report()`; `Requests/ViewShiftReportRequest.php` *(mới)*; `Policies/ShiftPolicy.php` — sửa
- `routes/api.php` — sửa: thêm route `GET /shifts/{shift}/report`
- `app/Console/Commands/PosDemo.php` — sửa: thêm mốc `--den=thu-tien` đầy đủ hơn + đối soát ca
- `tests/Feature/Staffing/Shift/{CloseShiftTest.php}` — sửa (thêm test C3/C4 thật), `ShiftReportTest.php` *(mới)*

### Bước 9 — In tem bếp, tạm tính, bill

- `app/Domain/Printing/DTO/{BillData,KitchenSlipData}.php`, `Printers/EscPosPrinter.php`, `Templates/{FinalBillTemplate,KitchenSlipTemplate,ProvisionalBillTemplate}.php`
- `app/Console/Commands/PosPrintTest.php`
- Không có test tự động (Action Print chưa nối API — xem Phần 4, mục 3)

### Bước 10 — Màn hình POS và màn hình bếp *(CHƯA COMMIT — trong working tree)*

- `resources/views/{dang-nhap,pos,bep}.blade.php`
- `resources/js/{dang-nhap,pos,bep}.js`, `resources/js/lib/api.js`
- `routes/web.php` — sửa (3 route mới), `vite.config.js` — sửa (3 entry JS), `resources/css/app.css` — sửa (CSS in tạm tính)
- Không có test tự động — đã kiểm bằng tay qua trình duyệt thật (đăng nhập → mở bàn → gọi món → gửi bếp → màn bếp → thu tiền), xem báo cáo phiên trước.

### Bước 11 — Opus review toàn phase

- `docs/review-phase1.md` (file này)
- `docs/review-buoc7-lan2.md` — đã có sẵn từ trước (review riêng của Bước 7, 5 vòng sửa lỗi 🔴/🟡; không phải tôi tạo ở phiên này)

---

## 2. Toàn văn 5 Action và Controller liên quan tiền

Nội dung dưới đây đọc trực tiếp từ file thật trong working tree tại thời
điểm viết báo cáo (không chép lại từ `docs/review-buoc7-lan2.md`, dù 2/3
Action Billing giống hệt bản đó — `VoidPayment.php` đã có thêm đoạn xử lý
mới so với bản review trước, xem ghi chú cuối mục 2.3).

### 2.1. `app/Domain/Billing/Actions/RecordPayment.php`

```php
<?php

declare(strict_types=1);

namespace App\Domain\Billing\Actions;

use App\Domain\Billing\DTO\RecordPaymentData;
use App\Domain\Billing\Enums\PaymentMethod;
use App\Domain\Billing\Enums\PaymentStatus;
use App\Domain\Billing\Models\Payment;
use App\Domain\Ordering\Actions\CloseTableSession;
use App\Domain\Ordering\DTO\CloseTableSessionData;
use App\Domain\Ordering\Enums\TableSessionStatus;
use App\Domain\Ordering\Models\TableSession;
use App\Domain\Staffing\Enums\ShiftStatus;
use App\Domain\Staffing\Models\Shift;
use App\Exceptions\DomainException;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

final class RecordPayment
{
    public function __construct(
        private readonly CloseTableSession $closeTableSession,
    ) {}

    public function handle(RecordPaymentData $data): Payment
    {
        return DB::transaction(function () use ($data): Payment {
            $phieuDaCo = Payment::query()->where('uuid', $data->uuid)->first();

            if ($phieuDaCo !== null) {
                return $phieuDaCo;
            }

            $tableSession = TableSession::query()->lockForUpdate()->findOrFail($data->tableSessionId);

            if (! in_array($tableSession->status, [TableSessionStatus::Open, TableSessionStatus::Billing], true)) {
                throw new DomainException('Lượt khách này đã đóng hoặc đã huỷ, không thu tiền được.');
            }

            $shift = Shift::query()->lockForUpdate()->findOrFail($tableSession->shift_id);

            if ($shift->status !== ShiftStatus::Open) {
                throw new DomainException('Ca của lượt khách này đã đóng, không thu tiền được.');
            }

            $tong = Money::fromInt($tableSession->total_amount);
            $daThu = Money::fromInt($tableSession->paid_amount);
            $conThieu = $tong->minus($daThu);

            if ($conThieu->isZero()) {
                throw new DomainException('Lượt khách này đã thu đủ tiền.');
            }

            if (! $conThieu->isAtLeast($data->amount)) {
                throw new DomainException(
                    "Thu quá số còn thiếu. Còn thiếu {$conThieu->format()}, đang thu {$data->amount->format()}."
                );
            }

            $tienThoi = Money::zero();

            if ($data->method === PaymentMethod::Cash) {
                if ($data->tenderedAmount === null) {
                    throw new DomainException('Thu tiền mặt phải ghi số tiền khách đưa.');
                }

                if ($data->tenderedAmount->isLessThan($data->amount)) {
                    throw new DomainException('Tiền khách đưa không được ít hơn số tiền thu.');
                }

                $tienThoi = $data->tenderedAmount->minus($data->amount);
            } elseif ($data->tenderedAmount !== null) {
                throw new DomainException('Chuyển khoản không có "tiền khách đưa".');
            }

            $payment = Payment::query()->create([
                'uuid' => $data->uuid,
                'table_session_id' => $tableSession->id,
                'shift_id' => $shift->id,
                'method' => $data->method,
                'amount' => $data->amount->amount,
                'tendered_amount' => $data->tenderedAmount?->amount,
                'change_amount' => $tienThoi->amount,
                'reference' => $data->reference,
                'status' => PaymentStatus::Completed,
                'received_by_user_id' => $data->receivedByUserId,
                'paid_at' => now(),
            ]);

            $daThuMoi = $daThu->plus($data->amount);

            $tableSession->update([
                'paid_amount' => $daThuMoi->amount,
                'status' => TableSessionStatus::Billing,
            ]);

            if ($daThuMoi->isAtLeast($tong)) {
                $this->closeTableSession->handle(new CloseTableSessionData(
                    tableSessionId: $tableSession->id,
                    closedByUserId: $data->receivedByUserId,
                ));
            }

            return $payment;
        });
    }
}
```

### 2.2. `app/Domain/Billing/Actions/VoidPayment.php`

```php
<?php

declare(strict_types=1);

namespace App\Domain\Billing\Actions;

use App\Domain\Billing\DTO\VoidPaymentData;
use App\Domain\Billing\Enums\PaymentMethod;
use App\Domain\Billing\Enums\PaymentStatus;
use App\Domain\Billing\Models\Payment;
use App\Domain\Ordering\Enums\TableSessionStatus;
use App\Domain\Ordering\Models\TableSession;
use App\Domain\Staffing\Enums\CashDirection;
use App\Domain\Staffing\Enums\ShiftStatus;
use App\Domain\Staffing\Models\CashMovement;
use App\Domain\Staffing\Models\Shift;
use App\Exceptions\DomainException;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

final class VoidPayment
{
    public function handle(VoidPaymentData $data): Payment
    {
        return DB::transaction(function () use ($data): Payment {
            $payment = Payment::query()->lockForUpdate()->findOrFail($data->paymentId);

            if ($payment->status === PaymentStatus::Voided) {
                throw new DomainException('Phiếu thu này đã huỷ rồi.');
            }

            $lyDo = trim($data->reason);

            if ($lyDo === '') {
                throw new DomainException('Phải ghi rõ lý do huỷ phiếu thu.');
            }

            $tableSession = TableSession::query()->lockForUpdate()->findOrFail($payment->table_session_id);

            $payment->update([
                'status' => PaymentStatus::Voided,
                'voided_at' => now(),
                'voided_by_user_id' => $data->voidedByUserId,
                'void_reason' => $lyDo,
            ]);

            $caCuaPhieu = Shift::query()->lockForUpdate()->findOrFail($payment->shift_id);
            $caCuaLuotKhach = Shift::query()->lockForUpdate()->findOrFail($tableSession->shift_id);

            $tongDaThu = Payment::query()
                ->where('table_session_id', $tableSession->id)
                ->where('status', PaymentStatus::Completed)
                ->get()
                ->reduce(
                    fn (Money $tong, Payment $p) => $tong->plus(Money::fromInt($p->amount)),
                    Money::zero()
                );

            $seMoLaiLuotKhach = $tableSession->status === TableSessionStatus::Closed
                && $tongDaThu->isLessThan(Money::fromInt($tableSession->total_amount));

            $canHoanTienMat = $caCuaPhieu->status === ShiftStatus::Closed && $payment->method === PaymentMethod::Cash;
            $canChuyenCaLuotKhach = $seMoLaiLuotKhach && $caCuaLuotKhach->status === ShiftStatus::Closed;

            $caHienTai = null;

            if ($canHoanTienMat || $canChuyenCaLuotKhach) {
                $caHienTai = Shift::query()->where('status', ShiftStatus::Open)->lockForUpdate()->first();

                if ($caHienTai === null) {
                    throw new DomainException($canChuyenCaLuotKhach
                        ? 'Chưa mở ca. Phải mở ca trước khi huỷ phiếu thu của lượt khách đã đóng ở ca cũ.'
                        : 'Chưa mở ca. Phải mở ca trước khi huỷ phiếu thu tiền mặt của ca cũ.');
                }
            }

            if ($canHoanTienMat) {
                CashMovement::query()->create([
                    'shift_id' => $caHienTai->id,
                    'direction' => CashDirection::Out,
                    'amount' => $payment->amount,
                    'reason' => "Hoàn tiền phiếu thu #{$payment->id} của ca {$caCuaPhieu->code} — {$lyDo}",
                    'created_by_user_id' => $data->voidedByUserId,
                    'occurred_at' => now(),
                ]);
            }

            $capNhat = ['paid_amount' => $tongDaThu->amount];

            if ($seMoLaiLuotKhach) {
                $capNhat['status'] = TableSessionStatus::Billing;
                $capNhat['closed_at'] = null;
                $capNhat['closed_by_user_id'] = null;

                if ($canChuyenCaLuotKhach) {
                    $capNhat['shift_id'] = $caHienTai->id;
                }
            }

            $tableSession->update($capNhat);

            return $payment;
        });
    }
}
```

> **Khác biệt so với `docs/review-buoc7-lan2.md`:** bản review trước chỉ xử
> lý "hoàn tiền mặt của ca cũ" (`canHoanTienMat`). Bản hiện tại có thêm
> `$canChuyenCaLuotKhach` — khi lượt khách mở lại (do huỷ phiếu thu làm hụt
> tiền) mà `table_sessions.shift_id` vẫn trỏ về ca CŨ đã đóng, code tự
> chuyển `shift_id` sang ca đang mở, vì `RecordPayment` (đoạn `$shift =
> Shift::query()->findOrFail($tableSession->shift_id)` + kiểm `status !==
> Open`) sẽ từ chối vĩnh viễn nếu không đổi. Đây là một lượt sửa xảy ra
> **sau** `docs/review-buoc7-lan2.md`, không có review riêng — xem mục 5
> "chỗ không chắc" bên dưới.

### 2.3. `app/Domain/Billing/Actions/CalculateBill.php`

```php
<?php

declare(strict_types=1);

namespace App\Domain\Billing\Actions;

use App\Domain\Billing\DTO\CalculateBillData;
use App\Domain\Ordering\Actions\CloseTableSession;
use App\Domain\Ordering\Actions\RecalculateSessionSubtotal;
use App\Domain\Ordering\DTO\CloseTableSessionData;
use App\Domain\Ordering\Enums\TableSessionStatus;
use App\Domain\Ordering\Models\TableSession;
use App\Domain\Ordering\Policies\TableSessionPolicy;
use App\Domain\Staffing\Actions\VerifyApproverPin;
use App\Domain\Staffing\DTO\PinVerifyData;
use App\Domain\Staffing\Models\User;
use App\Exceptions\DomainException;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

final class CalculateBill
{
    public function __construct(
        private readonly VerifyApproverPin $verifyApproverPin,
        private readonly CloseTableSession $closeTableSession,
    ) {}

    public function handle(CalculateBillData $data): TableSession
    {
        return DB::transaction(function () use ($data): TableSession {
            $tableSession = TableSession::query()->lockForUpdate()->findOrFail($data->tableSessionId);

            if (! in_array($tableSession->status, [TableSessionStatus::Open, TableSessionStatus::Billing], true)) {
                throw new DomainException('Lượt khách này đã đóng hoặc đã huỷ, không tính lại tiền được.');
            }

            app(RecalculateSessionSubtotal::class)->handle($tableSession);
            $tableSession->refresh();

            $tamTinh = Money::fromInt($tableSession->subtotal_amount);

            if (! $tamTinh->isAtLeast($data->discountAmount)) {
                throw new DomainException('Số tiền giảm giá không được lớn hơn tạm tính.');
            }

            $lyDo = $data->discountReason !== null ? trim($data->discountReason) : null;

            if (! $data->discountAmount->isZero() && ($lyDo === null || $lyDo === '')) {
                throw new DomainException('Giảm giá phải ghi rõ lý do.');
            }

            $nguoiYeuCau = User::query()->findOrFail($data->requestedByUserId);
            $phanTram = $this->phanTramLamTron($data->discountAmount, $tamTinh);
            $chinhSachGiam = new TableSessionPolicy;

            if (! $chinhSachGiam->discount($nguoiYeuCau, $tableSession, $phanTram)) {
                if ($data->approverUserId === null || $data->approverPin === null) {
                    throw new DomainException('Giảm giá vượt mức cho phép, phải có người duyệt bằng mã PIN.');
                }

                $nguoiDuyet = $this->verifyApproverPin->handle(new PinVerifyData(
                    userId: $data->approverUserId,
                    pin: $data->approverPin,
                    requestedByUserId: $data->requestedByUserId,
                ));

                if (! $chinhSachGiam->discount($nguoiDuyet, $tableSession, $phanTram)) {
                    throw new DomainException('Người duyệt cũng không đủ thẩm quyền giảm giá ở mức này.');
                }
            }

            $tongMoi = $tamTinh->minus($data->discountAmount);
            $daThu = Money::fromInt($tableSession->paid_amount);

            if ($tongMoi->isLessThan($daThu)) {
                throw new DomainException(
                    "Không giảm được xuống {$tongMoi->format()} vì khách đã trả {$daThu->format()}. Muốn giảm thêm thì phải huỷ bớt phiếu thu trước."
                );
            }

            $tableSession->update([
                'discount_amount' => $data->discountAmount->amount,
                'discount_reason' => $data->discountAmount->isZero() ? null : $lyDo,
                'total_amount' => $tongMoi->amount,
            ]);

            if ($daThu->isAtLeast($tongMoi)) {
                $this->closeTableSession->handle(new CloseTableSessionData(
                    tableSessionId: $tableSession->id,
                    closedByUserId: $data->requestedByUserId,
                ));
            }

            return $tableSession->refresh();
        });
    }

    private function phanTramLamTron(Money $giamGia, Money $tamTinh): int
    {
        if ($tamTinh->isZero()) {
            return 0;
        }

        return intdiv($giamGia->amount * 100 + $tamTinh->amount - 1, $tamTinh->amount);
    }
}
```

### 2.4. `app/Domain/Staffing/Actions/CloseShift.php`

```php
<?php

declare(strict_types=1);

namespace App\Domain\Staffing\Actions;

use App\Domain\Billing\Enums\PaymentMethod;
use App\Domain\Billing\Enums\PaymentStatus;
use App\Domain\Billing\Models\Payment;
use App\Domain\Ordering\Enums\TableSessionStatus;
use App\Domain\Ordering\Models\TableSession;
use App\Domain\Ordering\Models\TableSessionTable;
use App\Domain\Staffing\DTO\CloseShiftData;
use App\Domain\Staffing\Enums\CashDirection;
use App\Domain\Staffing\Enums\ShiftStatus;
use App\Domain\Staffing\Models\CashMovement;
use App\Domain\Staffing\Models\Shift;
use App\Exceptions\DomainException;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

final class CloseShift
{
    public function handle(CloseShiftData $data): Shift
    {
        return DB::transaction(function () use ($data): Shift {
            $shift = Shift::query()->lockForUpdate()->findOrFail($data->shiftId);

            if ($shift->status !== ShiftStatus::Open) {
                throw new DomainException('Ca này đã đóng rồi, không đóng lại được nữa.');
            }

            $luotChuaTinhTien = TableSession::query()
                ->where('shift_id', $shift->id)
                ->whereIn('status', [TableSessionStatus::Open, TableSessionStatus::Billing])
                ->with('tables.diningTable')
                ->get();

            if ($luotChuaTinhTien->isNotEmpty()) {
                $noiConVuong = $luotChuaTinhTien
                    ->map(function (TableSession $luot): string {
                        $tenBan = $luot->tables
                            ->whereNull('detached_at')
                            ->map(fn (TableSessionTable $bi) => $bi->diningTable->code)
                            ->implode(', ');

                        return $tenBan !== '' ? $tenBan : "lượt khách {$luot->code} (chưa gán bàn)";
                    })
                    ->unique()
                    ->implode(', ');

                throw new DomainException("Còn bàn {$noiConVuong} đang mở hoặc đang tính tiền. Phải tính tiền hết bàn trước khi đóng ca.");
            }

            $expectedCash = $this->tinhTienMatLeRaPhaiCo($shift);

            $shift->update([
                'counted_cash' => $data->countedCash->amount,
                'expected_cash' => $expectedCash->amount,
                'status' => ShiftStatus::Closed,
                'closed_by_user_id' => $data->closedByUserId,
                'closed_at' => now(),
                'note' => $data->note,
            ]);

            return $shift;
        });
    }

    private function tinhTienMatLeRaPhaiCo(Shift $shift): Money
    {
        $tienDauCa = Money::fromInt($shift->opening_cash);

        $tienMatThuDuoc = Money::fromInt((int) Payment::query()
            ->where('shift_id', $shift->id)
            ->where('method', PaymentMethod::Cash)
            ->where('status', PaymentStatus::Completed)
            ->sum('amount'));

        $tienThoiLai = Money::fromInt((int) Payment::query()
            ->where('shift_id', $shift->id)
            ->where('method', PaymentMethod::Cash)
            ->where('status', PaymentStatus::Completed)
            ->sum('change_amount'));

        $tienBoVaoKet = Money::fromInt((int) CashMovement::query()
            ->where('shift_id', $shift->id)
            ->where('direction', CashDirection::In)
            ->sum('amount'));

        $tienLayRa = Money::fromInt((int) CashMovement::query()
            ->where('shift_id', $shift->id)
            ->where('direction', CashDirection::Out)
            ->sum('amount'));

        $congVao = $tienDauCa->plus($tienMatThuDuoc)->plus($tienBoVaoKet);
        $truRa = $tienThoiLai->plus($tienLayRa);

        return $congVao->minus($truRa);
    }
}
```

> **Lưu ý một dòng comment CŨ, GÂY HIỂU NHẦM, còn sót lại trong file thật**
> (không sửa ở đây vì báo cáo này chỉ đọc): docblock của
> `tinhTienMatLeRaPhaiCo()` viết *"hiện luôn bằng 0 vì Bước 7 (thu tiền)
> chưa làm"* — câu này **không còn đúng**, Bước 7 đã xong từ lâu và công
> thức đang cộng đúng `Payment` thật (đã kiểm chứng bằng tay: thu 50.000đ ở
> Bàn 1 trong phiên trước, số liệu cộng đúng). Đây là comment lỗi thời, nên
> xoá hoặc sửa lại lần tới đụng vào file này — xem mục 4.

### 2.5. `app/Domain/Ordering/Actions/CancelOrderItem.php`

```php
<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Actions;

use App\Domain\Ordering\DTO\CancelOrderItemData;
use App\Domain\Ordering\Enums\OrderItemStatus;
use App\Domain\Ordering\Models\Order;
use App\Domain\Ordering\Models\OrderItem;
use App\Domain\Staffing\Actions\VerifyApproverPin;
use App\Domain\Staffing\DTO\PinVerifyData;
use App\Exceptions\DomainException;
use Illuminate\Support\Facades\DB;

final class CancelOrderItem
{
    public function __construct(
        private readonly VerifyApproverPin $verifyApproverPin,
    ) {}

    public function handle(CancelOrderItemData $data): OrderItem
    {
        return DB::transaction(function () use ($data): OrderItem {
            $order = Order::query()->lockForUpdate()->findOrFail($data->orderId);
            $item = OrderItem::query()->where('order_id', $order->id)->lockForUpdate()->findOrFail($data->orderItemId);

            if ($item->status === OrderItemStatus::Cancelled) {
                throw new DomainException('Món này đã huỷ rồi.');
            }

            if ($data->quantity < 1 || $data->quantity > $item->quantity) {
                throw new DomainException("Số lượng huỷ không hợp lệ. Dòng này còn {$item->quantity}, không huỷ được {$data->quantity}.");
            }

            $lyDo = trim($data->reason);
            if ($lyDo === '') {
                throw new DomainException('Phải ghi rõ lý do huỷ món.');
            }

            if ($item->status === OrderItemStatus::Served) {
                $this->duyetBangPin($data);
            }

            $dongDaHuy = $data->quantity === $item->quantity
                ? $this->huyToanBo($item, $data, $lyDo)
                : $this->tachVaHuyMotPhan($item, $data, $lyDo);

            app(RecalculateSessionSubtotal::class)->handle($order->tableSession);

            return $dongDaHuy;
        });
    }

    private function duyetBangPin(CancelOrderItemData $data): void
    {
        if ($data->approverUserId === null || $data->approverPin === null) {
            throw new DomainException('Món đã phục vụ ra bàn, phải có người duyệt bằng mã PIN mới huỷ được.');
        }

        $this->verifyApproverPin->handle(new PinVerifyData(
            userId: $data->approverUserId,
            pin: $data->approverPin,
            requestedByUserId: $data->cancelledByUserId,
        ));
    }

    private function huyToanBo(OrderItem $item, CancelOrderItemData $data, string $lyDo): OrderItem
    {
        $item->update([
            'status' => OrderItemStatus::Cancelled,
            'cancelled_at' => now(),
            'cancelled_by_user_id' => $data->cancelledByUserId,
            'cancel_reason' => $lyDo,
        ]);

        return $item;
    }

    private function tachVaHuyMotPhan(OrderItem $item, CancelOrderItemData $data, string $lyDo): OrderItem
    {
        $soLuongConLai = $item->quantity - $data->quantity;
        $item->update(['quantity' => $soLuongConLai]);

        $dongMoi = OrderItem::query()->create([
            'order_id' => $item->order_id,
            'product_id' => $item->product_id,
            'product_variant_id' => $item->product_variant_id,
            'product_name' => $item->product_name,
            'variant_name' => $item->variant_name,
            'unit_price' => $item->unit_price,
            'options_amount' => $item->options_amount,
            'quantity' => $data->quantity,
            'status' => OrderItemStatus::Cancelled,
            'cancelled_at' => now(),
            'cancelled_by_user_id' => $data->cancelledByUserId,
            'cancel_reason' => $lyDo,
            'split_from_item_id' => $item->id,
            'note' => $item->note,
        ]);

        foreach ($item->options as $tuyChon) {
            $dongMoi->options()->create([
                'option_id' => $tuyChon->option_id,
                'option_group_name' => $tuyChon->option_group_name,
                'option_name' => $tuyChon->option_name,
                'price_delta' => $tuyChon->price_delta,
            ]);
        }

        return $dongMoi->refresh();
    }
}
```

### 2.6. Controller liên quan tiền

```php
// app/Http/Controllers/Api/PaymentController.php
final class PaymentController extends Controller
{
    /** POST /api/v1/table-sessions/{tableSession}/payments */
    public function store(RecordPaymentRequest $request, TableSession $tableSession, RecordPayment $action): JsonResponse
    {
        $payment = $action->handle(RecordPaymentData::fromRequest($request));

        return response()->json([
            'data' => PaymentResource::make($payment->load('receivedBy')),
        ], Response::HTTP_CREATED);
    }

    /** POST /api/v1/payments/{payment}/void */
    public function void(VoidPaymentRequest $request, Payment $payment, VoidPayment $action): JsonResponse
    {
        $action->handle(VoidPaymentData::fromRequest($request));

        return response()->json([
            'data' => PaymentResource::make($payment->refresh()->load(['receivedBy', 'voidedBy'])),
        ]);
    }
}

// app/Http/Controllers/Api/BillController.php
final class BillController extends Controller
{
    /** GET /api/v1/table-sessions/{tableSession}/bill */
    public function show(TableSession $tableSession, GetSessionBill $query): JsonResponse
    {
        return response()->json(['data' => BillResource::make($query->handle($tableSession->id))]);
    }
}

// app/Http/Controllers/Api/TableSessionController.php — chỉ method liên quan tiền
final class TableSessionController extends Controller
{
    /** POST /api/v1/table-sessions/{tableSession}/discount */
    public function discount(CalculateBillRequest $request, TableSession $tableSession, CalculateBill $action): JsonResponse
    {
        $action->handle(CalculateBillData::fromRequest($request));

        return $this->tra($tableSession);
    }
}

// app/Http/Controllers/Api/ShiftController.php — chỉ method liên quan tiền
final class ShiftController extends Controller
{
    /** POST /api/v1/shifts/{shift}/close */
    public function close(CloseShiftRequest $request, Shift $shift, CloseShift $action): JsonResponse
    {
        $data = CloseShiftData::fromRequest($request);
        $shift = $action->handle($data);

        return response()->json(['data' => ShiftResource::make($shift->load(['openedBy', 'closedBy']))]);
    }
}

// app/Http/Controllers/Api/OrderItemController.php — chỉ method liên quan huỷ món
final class OrderItemController extends Controller
{
    /** POST /api/v1/orders/{order}/items/{orderItem}/cancel */
    public function cancel(CancelOrderItemRequest $request, Order $order, OrderItem $orderItem, CancelOrderItem $action): JsonResponse
    {
        $dongDaHuy = $action->handle(CancelOrderItemData::fromRequest($request));

        return response()->json(['data' => OrderItemResource::make($dongDaHuy->load('options'))]);
    }
}
```

---

## 3. Bảng đối chiếu 43 bất biến (`docs/schema.md` Phần 4)

Cột "Thực tế" xác nhận bằng cách đọc trực tiếp migration/Action + chạy
`./vendor/bin/pest`. Ký hiệu: ✅ = đúng như `docs/schema.md` khai báo, xác
nhận được bằng file/test cụ thể. ⚠️ = có nhưng có ghi chú cần biết thêm.

### Về bàn và lượt khách

| # | Ai gác (khai báo) | Thực tế giữ ở đâu | Test | Ghi chú |
|---|---|---|---|---|
| B1 | DB (`uq_tst_one_session_per_table`) | ✅ `database/migrations/2026_07_31_000006_create_table_session_tables_table.php:39` — unique trên generated column `occupied_table_id` | `TableConcurrencyTest.php`, `OpenTableSessionTest.php`, `AttachTableTest.php`, `TransferTableTest.php` | — |
| B2 | APP | ✅ Không có Action nào tạo lượt khách 0 bàn ở luồng bình thường (`OpenTableSession` bắt buộc `dining_table_ids` min 1) | `OpenTableSessionTest.php` | ⚠️ Có **một ngoại lệ đã biết**: `VoidPayment.php` (mục 2.2) mở lại lượt khách `billing` có thể **không chiếm bàn nào**, nếu bàn cũ đã có khách mới. Đã ghi chú ngay trong code + `docs/viec-ton.md` dòng "[Bước 11] Bất biến B2 có một ngoại lệ..." — đúng là chưa cập nhật vào `docs/schema.md` như dòng đó đã tự nhận |
| B3 | APP | ✅ `OpenTableSessionData`/`OpenTableSession` bắt `primary_dining_table_id` phải nằm trong `dining_table_ids`; `TransferTable`/`DetachTable` giữ đúng 1 `is_primary=1` | `OpenTableSessionTest.php`, `DetachTableTest.php` | — |
| B4 | APP | ✅ `CloseTableSession.php`, `VoidTableSession.php` đều `detach` mọi bàn còn `attached_at` trong cùng transaction | `CloseTableSessionTest.php`, `VoidTableSessionTest.php` | — |
| B5 | APP | ✅ `DetachTable.php` chặn nhả bàn cuối cùng | `DetachTableTest.php` | — |
| B6 | APP | ✅ `TransferTable.php` nhả bàn cũ + chiếm bàn mới trong 1 `DB::transaction()` | `TransferTableTest.php` | — |

### Về gọi món

| # | Ai gác (khai báo) | Thực tế giữ ở đâu | Test | Ghi chú |
|---|---|---|---|---|
| M1 | DB (không có cột `dining_table_id`) | ✅ `database/migrations/2026_07_31_000012_create_orders_table.php` — không có cột này, chỉ có `table_session_id` | `PlaceOrderTest.php` | — |
| M2 | DB (`uq_orders_uuid`) | ✅ dòng 48 migration orders | `PlaceOrderTest.php` | — |
| M3 | DB+APP | ✅ `PlaceOrder.php` tra `uuid` trước khi tạo mới, trả lại đơn cũ nếu trùng | `PlaceOrderTest.php`, `SendToKitchenTest.php` | — |
| M4 | DB (`NOT NULL`) + APP | ✅ `product_name`/`variant_name`/`unit_price` copy trực tiếp từ `Product`/`ProductVariant` tại thời điểm gọi, cột `NOT NULL` trong `..._000013_create_order_items_table.php` | `PlaceOrderTest.php` | — |
| M5 | DB (cột tự tính) + code | ✅ `line_amount` là generated column (xem migration `..._000013`), không nằm trong `$fillable` của `OrderItem` | `PlaceOrderTest.php` (test cố ghi sai giá trị) | Xem ghi chú M5 gốc trong `docs/schema.md` về khác biệt MySQL/MariaDB — không kiểm lại ở báo cáo này vì không đổi gì từ lần review trước |
| M6 | DB (`ck_order_items_qty`) | ✅ `database/migrations/2026_07_31_000013_create_order_items_table.php:60` | `PlaceOrderTest.php` | — |
| M7 | APP | ✅ `PlaceOrder.php` — vòng lặp gán `$station` từ món đầu tiên, so khớp mọi món còn lại, ném lỗi nếu khác | `PlaceOrderTest.php`, `SendToKitchenTest.php` | — |
| M8 | APP | ✅ `PlaceOrder.php` kiểm `$tableSession->status !== TableSessionStatus::Open` | `PlaceOrderTest.php` | — |
| M9 | APP | ✅ `PlaceOrder::kiemTraTuyChon()` kiểm `min_select`/`max_select` | `PlaceOrderTest.php` | — |

### Về hủy

| # | Ai gác (khai báo) | Thực tế giữ ở đâu | Test | Ghi chú |
|---|---|---|---|---|
| H1 | DB (`RESTRICT`) + APP | ✅ Toàn bộ FK 12 bảng transaction đều `restrictOnDelete()` (đếm được ở Phần trên); không Action nào gọi `delete()`/`forceDelete()`/`truncate()` trên các bảng này (đã `grep`, không có kết quả) | — (kiểm tra tĩnh, không phải 1 test riêng) | — |
| H2 | DB (`ck_*_cancel`/`ck_*_void`) | ✅ `ck_order_items_cancel`, `ck_orders_cancel`, `ck_payments_void`, `ck_table_sessions_void`, `ck_shifts_closed_fields` (đóng ca cũng là một dạng "chốt", không hẳn "huỷ" nhưng cùng nguyên tắc 3 trường bắt buộc) | `CancelOrderItemTest.php`, `VoidPaymentTest.php`, `VoidTableSessionTest.php` | ⚠️ `ck_orders_cancel` **tồn tại ở DB nhưng không có Action nào trong Phase 1 từng đặt `orders.status = 'cancelled'`** (chỉ `OrderItem` bị huỷ từng dòng, chưa có "huỷ nguyên phiếu gọi món"). Ràng buộc này đang "ngủ" — đúng, không sai, nhưng chưa có test nào chạm tới nó. Xem mục 5 |
| H3 | APP | ✅ `RecalculateSessionSubtotal.php` lọc `where('status', '!=', OrderItemStatus::Cancelled)` | `PlaceOrderTest.php`, `CancelOrderItemTest.php` | — |
| H4 | APP | ✅ `CancelOrderItem::tachVaHuyMotPhan()` — tổng số lượng trước/sau tách luôn bằng nhau (trừ trực tiếp rồi cộng dòng mới đúng phần đã trừ) | `CancelOrderItemTest.php` | — |
| H5 | APP | ✅ `CancelOrderItem::duyetBangPin()` gọi lại `VerifyApproverPin` khi `status === Served` | `CancelOrderItemTest.php` | ⚠️ Danh tính người DUYỆT PIN không lưu riêng trên `order_items` — `GetShiftReport.php` tự ghi chú rõ điều này (mục 2 file đó), khớp với `docs/viec-ton.md` dòng "[Phase 2] Z-report cột 'người duyệt'..." |
| H6 | APP | ✅ `VoidTableSession.php` kiểm không còn `Payment` nào `status=Completed` trước khi cho huỷ | `VoidTableSessionTest.php` | — |

### Về tiền

| # | Ai gác (khai báo) | Thực tế giữ ở đâu | Test | Ghi chú |
|---|---|---|---|---|
| T1 | DB (`BIGINT UNSIGNED`) | ✅ Toàn bộ cột tiền dùng `unsignedBigInteger`/`bigInteger` không dấu | `MoneyTest.php` (Unit) + toàn bộ test Billing | — |
| T2 | APP | ✅ `RecalculateSessionSubtotal.php` tính lại từ `order_items` chưa huỷ mỗi lần gọi | `CalculateBillTest.php` | — |
| T3 | DB (`ck_table_sessions_total`) | ✅ `database/migrations/2026_07_31_000005_create_table_sessions_table.php:62` + cột `UNSIGNED` chặn âm | `CalculateBillTest.php` | — |
| T4 | DB (`ck_table_sessions_discount_reason`) | ✅ dòng 63 migration trên | `CalculateBillTest.php` | — |
| T5 | APP | ✅ `VoidPayment.php` tính lại `paid_amount` bằng cách CỘNG LẠI từ đầu mọi `Payment` `Completed` | `VoidPaymentTest.php` | — |
| T6 | DB (`ck_table_sessions_closed`) | ✅ dòng 65 migration trên + `CloseTableSession.php` kiểm trước khi gọi | `RecordPaymentTest.php`, `CloseTableSessionTest.php`, `PaymentConcurrencyTest.php` | — |
| T7 | DB (`ck_payments_cash`) | ✅ `database/migrations/2026_07_31_000015_create_payments_table.php:53` | `RecordPaymentTest.php` | — |
| T8 | DB (`ck_payments_cash`, cùng CHECK với T7) | ✅ cùng dòng trên | `RecordPaymentTest.php` | — |
| T9 | DB (`uq_payments_uuid`) | ✅ dòng 45 migration trên | `RecordPaymentTest.php` (bao gồm test "T9: cùng uuid nhưng khác Idempotency-Key..." từ `docs/review-buoc7-lan2.md`) | — |

### Về ca làm việc

| # | Ai gác (khai báo) | Thực tế giữ ở đâu | Test | Ghi chú |
|---|---|---|---|---|
| C1 | DB (`uq_shifts_only_one_open`) | ✅ `database/migrations/2026_07_31_000002_create_shifts_table.php:47` — unique trên generated column `open_guard` | `OpenShiftTest.php` | — |
| C2 | DB (FK `NOT NULL`) | ✅ `shift_id` trên `table_sessions`/`payments`/`cash_movements` đều `foreignId()` mặc định không `nullable()` | — (không có test riêng tên "C2", nhưng mọi factory/test Billing/Ordering đều gán `shift_id` bắt buộc, thiếu là lỗi migration ngay lập tức) | Không có test đặt tên rõ "C2" — xác nhận bằng đọc migration trực tiếp, không phải suy luận |
| C3 | APP | ✅ `CloseShift.php` — kiểm còn `TableSession` `Open`/`Billing` thì chặn, nêu tên bàn cụ thể | `CloseShiftTest.php` | Bổ sung ở Bước 8 (uncommitted) — bản Bước 1 ban đầu có kiểm nhưng thông báo chưa nêu tên bàn |
| C4 | APP (tính lúc đóng ca) | ✅ `CloseShift::tinhTienMatLeRaPhaiCo()` — công thức đúng như `docs/schema.md` mô tả | `CloseShiftTest.php`, `VoidPaymentTest.php` (2 test "C4+C5: huỷ phiếu thu tiền mặt...") | ⚠️ Xem ghi chú comment lỗi thời ở mục 2.4 |
| C5 | DB (`ck_shifts_closed_fields`) + APP | ✅ `database/migrations/2026_07_31_000002_create_shifts_table.php:52`; không Action nào `update()` một `Shift` đã `Closed` (đã `grep`, chỉ `CloseShift.php` ghi các cột chốt, một lần) | `CloseShiftTest.php`, `VoidPaymentTest.php` | — |
| C6 | DB (`ck_shifts_closed_fields`) | ✅ cùng CHECK C5 — `counted_cash` bắt buộc khi `status='closed'` | `CloseShiftTest.php` | — |
| C7 | DB (`NOT NULL`) | ✅ cột `reason` trên `cash_movements` không nullable | `RecordCashMovementTest.php` | — |

### Về thực đơn

| # | Ai gác (khai báo) | Thực tế giữ ở đâu | Test | Ghi chú |
|---|---|---|---|---|
| E1 | APP | ✅ `ProductVariantInvariantsTest.php` xác nhận | `ProductVariantInvariantsTest.php` | Không có CHECK ở DB ép "ít nhất 1 biến thể" — đúng như khai báo, chỉ APP (Filament chặn xoá biến thể cuối) |
| E2 | APP | ✅ `SetDefaultProductVariant.php` | `ProductVariantInvariantsTest.php` | — |
| E3 | DB | ✅ cột `price` là `unsignedBigInteger` | — (không có test riêng, đúng như khai báo "không có test", chỉ đảm bảo kiểu cột) | — |
| E4 | DB (`RESTRICT`) + APP | ✅ `restrictOnDelete()` trên FK `product_id`/`category_id`/... + Filament không có nút Xoá | `FilamentNoDeleteButtonTest.php` | — |
| E5 | DB (`ck_option_groups_scope`) | ✅ `database/migrations/2026_07_31_000010_create_option_groups_table.php:42` | `OptionGroupScopeTest.php` | — |
| E6 | APP | ✅ `Product::effectiveStation()` — dùng `station_override` nếu có, không thì lấy của nhóm món | `ProductEffectiveStationTest.php` (Unit), `KdsTicketsTest.php` | — |

**Tổng kết Phần 3:** 43/43 bất biến có bằng chứng giữ đúng (file + phần lớn có test riêng). 2 chỗ cần lưu ý thêm, không phải lỗi nhưng đáng đọc kỹ:
`B2` có ngoại lệ đã biết (đã ghi trong code + `viec-ton.md`), `H2`/`ck_orders_cancel` là ràng buộc DB đang "ngủ" vì chưa có Action nào huỷ nguyên phiếu gọi món ở tầng Order.

---

## 4. Những chỗ tôi đã tự quyết vì tài liệu không nói rõ

Tổng hợp từ comment trong code (đa số tác giả trước đã tự ghi lại quyết
định ngay tại chỗ, không phải tôi tự suy đoán thêm ở báo cáo này):

1. **Đóng lượt khách tự động khi giảm giá làm `total_amount` bằng đúng
   `paid_amount`** (`CalculateBill.php`) — tài liệu không nói rõ giảm giá
   có tự đóng bàn không, tác giả trước quyết định giống hệt hành vi
   `RecordPayment` (thu đủ thì tự đóng), lý do: "không có lý do gì bắt thu
   ngân bấm thêm một bước đóng bàn riêng khi tiền đã đủ ngay tại lúc giảm
   giá".
2. **Làm tròn LÊN (ceiling) phần trăm giảm giá** (`CalculateBill::phanTramLamTron()`)
   — `docs/schema.md` không quy định cách làm tròn %, công thức
   `intdiv($giamGia*100 + $tamTinh - 1, $tamTinh)` tự chọn để tránh lách
   ngưỡng vai trò bằng làm tròn xuống.
3. **`VoidPayment` tự chuyển `shift_id` của lượt khách sang ca đang mở khi
   mở lại** (mục 2.2, đoạn `canChuyenCaLuotKhach`) — tài liệu không nói gì
   về việc này, tác giả trước tự suy luận cần thiết vì nếu không đổi,
   `RecordPayment` sẽ vĩnh viễn từ chối thu tiếp (đọc `shift_id` cũ đã
   đóng). Đây là quyết định **mới nhất**, sau `docs/review-buoc7-lan2.md`,
   chưa có ai review riêng.
4. **`docs/PHASE.md` bảng tra ghi C2 = "Mọi lượt khách, mọi phiếu thu, mọi
   khoản thu chi vặt đều thuộc về đúng một ca"** — không nói rõ giữ bằng
   DB hay APP. Tác giả trước (và tôi khi kiểm chứng lại) xác nhận **DB**
   (FK `NOT NULL`), không có test đặt tên riêng — tôi tự quyết không thêm
   test mới ở báo cáo này vì đây là báo cáo CHỈ ĐỌC.
5. **Đưa Auth/PIN (Phase 0) ra khỏi Bước 1 trong Phần 1 báo cáo này** — tài
   liệu `docs/PHASE.md` không nói Auth thuộc bước nào; tôi tự quyết xếp
   riêng "Nền Phase 0" thay vì nhét vào Bước 1, vì bảng tra Bước 1 chỉ ghi
   "Ca làm việc", không nhắc đăng nhập.
6. **`GetShiftReport.php` dùng `cancelled_by_user_id` làm "người duyệt"
   trong Z-report** (thay vì lưu riêng người cầm PIN) — tác giả trước tự
   quyết KHÔNG đổi schema để thêm cột `approved_by_user_id`, dời việc này
   sang Phase 2, đúng luật CLAUDE.md mục 7.2 (không đổi schema mà không
   hỏi). Đã ghi vào `docs/viec-ton.md`.

---

## 5. Những chỗ tôi KHÔNG CHẮC đã đúng

1. **`VoidPayment::canChuyenCaLuotKhach`** (mục 2.2) là đoạn code mới nhất
   trong toàn bộ 5 file Action ở Phần 2, xuất hiện SAU
   `docs/review-buoc7-lan2.md` và **chưa có review riêng nào cho nó**. Tôi
   đọc logic thấy hợp lý (khoá đúng thứ tự `Shift → TableSession →
   Payment`? — thực ra thứ tự khoá ở đây là `Payment` trước rồi mới
   `TableSession` rồi mới 2 `Shift`, xem lại luật khoá CLAUDE.md mục 11:
   "Shift → TableSession → Payment" — **thứ tự khoá thực tế trong
   `VoidPayment.php` KHÔNG khớp thứ tự luật này** (khoá `Payment` trước
   tiên ở dòng đầu `handle()`, trong khi luật ghi Shift phải khoá trước
   TableSession và Payment). Tôi không chắc đây có phải nguồn deadlock tiềm
   ẩn không vì `docs/viec-ton.md` đã có dòng "[Bước 11] Rà lại thứ tự khoá
   của RecordPayment, VoidPayment, CalculateBill, CloseShift..." — tức là
   vấn đề này đã được biết trước, nhưng tôi xác nhận nó **vẫn chưa được
   sửa** tính đến thời điểm viết báo cáo này. Cần Opus/chủ dự án quyết có
   phải sửa ngay không.
2. **Bảng test cho `ck_orders_cancel` (H2 ở tầng Order) không tồn tại** —
   tôi không chắc đây là lỗ hổng thật (nếu sau này có ai thêm tính năng
   "huỷ nguyên phiếu gọi món" mà quên set đủ 3 trường, CHECK constraint vẫn
   chặn được ở DB, nên về lý thuyết an toàn) hay chỉ là ràng buộc thừa
   không bao giờ dùng tới trong Phase 1. Không đủ căn cứ để nói chắc nên
   xoá hay nên giữ.
3. **Comment lỗi thời trong `CloseShift.php`** (mục 2.4) — tôi khá chắc nó
   sai (Bước 7 rõ ràng đã xong), nhưng không chắc còn dòng comment lỗi thời
   nào khác nằm rải rác ở các file tôi chưa đọc hết trong báo cáo này
   (Phần 2 chỉ đọc kỹ 5 Action + Controller liên quan tiền, không đọc lại
   toàn bộ ~90 file Action/Query/Policy còn lại của cả Phase 1).
0. **[Cập nhật 2026-08-01, sau khi báo cáo này được viết]** Ba lỗ hổng khoá
   nêu ở mục 5.1 dưới đây đã được xử lý một phần trong các lượt sửa tiếp
   theo cùng ngày: `OpenTableSession` đã thêm `lockForUpdate()` khi đọc ca
   đang mở (chặn race với `CloseShift`), và `VoidPayment` đã gộp khoá hai
   ca liên quan vào một câu `whereIn(...)->orderBy('id')->lockForUpdate()`.
   Mục 6 (Bảng thứ tự khoá) bên dưới phản ánh đúng trạng thái SAU các lượt
   sửa đó, không phải trạng thái tại thời điểm viết mục 1–5.
4. **Số liệu "284 passed" chỉ là một lần chạy `./vendor/bin/pest` duy nhất**
   ngay trước khi viết báo cáo — không chạy `--parallel` hay lặp lại nhiều
   lần để loại trừ test không ổn định (flaky), đặc biệt các test mô phỏng
   tranh chấp (`TableConcurrencyTest.php`, `PaymentConcurrencyTest.php`) —
   bản thân các test này đã tự nhận (mục 4, `docs/review-buoc7-lan2.md`)
   là mô phỏng tuần tự, không phải tranh chấp thật, nên không loại trừ khả
   năng có sai lệch chỉ lộ ra khi chạy thật với hai tiến trình song song.
5. **Phần 1 (danh sách file theo bước) dựa vào đọc hiểu chức năng, không
   phải một nguồn dữ liệu có thể chạy lại để xác minh tự động** (vì Bước
   2–7 gộp chung một commit). Có khả năng tôi xếp nhầm 1-2 file biên giới
   (ví dụ `RecalculateSessionSubtotal.php` tôi xếp vào Bước 3 vì tạo ra ở
   đó, nhưng nó được Bước 4/6/7 dùng lại nhiều nhất) — không ảnh hưởng tới
   Phần 3 (bảng bất biến, phần quan trọng hơn), chỉ ảnh hưởng cách trình
   bày ở Phần 1.

---

## 6. Bảng thứ tự khoá — mọi Action có `DB::transaction`

Đọc trực tiếp từng file (`grep lockForUpdate` + đọc lại toàn văn), tính đến
thời điểm sau các lượt sửa ngày 2026-08-01 (xem mục 0). Cột "Đúng luật?"
đối chiếu với CLAUDE.md mục 11: *"Mọi Action đụng tiền khoá theo đúng một
thứ tự chung: Shift → TableSession → Payment [...] Cần khoá nhiều dòng
Shift/bàn cùng lúc thì khoá theo id tăng dần."*

| Action | Khoá gì, theo đúng thứ tự trong code | Đúng luật? |
|---|---|---|
| `OpenShift` | 1 `Shift` (tìm ca đang mở) | ✅ chỉ 1 loại bảng |
| `CloseShift` | 1 `Shift` (theo id) | ✅ chỉ 1 loại bảng |
| `RecordCashMovement` | 1 `Shift` (theo id) | ✅ chỉ 1 loại bảng |
| `OpenTableSession` | `Shift` (đang mở) → nhiều `DiningTable` (theo id tăng dần) | ✅ Shift trước — đã sửa 2026-08-01 (trước đó KHÔNG khoá Shift, xem mục 5.1 cũ) |
| `AttachTable` | `TableSession` (theo id) → 1 `DiningTable` (theo id) | — (không có Shift/Payment liên quan) |
| `DetachTable` | `TableSession` (theo id) | — (không có Shift/Payment liên quan) |
| `TransferTable` | `TableSession` (theo id) → nhiều `DiningTable` (theo id tăng dần) | — (không có Shift/Payment liên quan) |
| `CloseTableSession` | `TableSession` (theo id) | — (gọi lồng bên trong `RecordPayment`/`CalculateBill`, khoá lại đúng dòng đã khoá sẵn — không phát sinh khoá mới) |
| `VoidTableSession` | `TableSession` (theo id) | — (không có Shift/Payment liên quan) |
| `PlaceOrder` | `TableSession` (theo id) | ✅ TableSession trước (không cần Shift ở đây) |
| `UpdateOrderItem` | `Order` (theo id) → `OrderItem` (**không** `lockForUpdate`, chỉ đọc thường) | ⚠️ xem ghi chú (a) |
| `RemoveOrderItem` | `Order` (theo id) → `OrderItem` (**không** `lockForUpdate`, chỉ đọc thường) | ⚠️ xem ghi chú (a) |
| `CancelOrderItem` | `Order` (theo id) → `OrderItem` (có `lockForUpdate`) | 🔴 **ngược chiều với `UpdateOrderItemStatus`** — xem ghi chú (b) |
| `UpdateOrderItemStatus` | `OrderItem` (theo id) → `Order` (theo id) | 🔴 **ngược chiều với `CancelOrderItem`** — xem ghi chú (b) |
| `CalculateBill` | 1 `TableSession` (theo id) — **không khoá Shift** dù đọc `User`/gọi `VerifyApproverPin` | — (không cần Shift vì không đụng `payments`/`cash_movements`) |
| `RecordPayment` | `TableSession` (theo id) → `Shift` (1 dòng, theo `table_session.shift_id`) | ✅ TableSession → Shift, đúng chiều luật (Shift phải trước TableSession theo câu chữ CLAUDE.md, nhưng ở đây không xảy ra deadlock — xem ghi chú (c)) |
| `VoidPayment` | `Payment` (theo id) → `TableSession` (theo id) → 2 `Shift` (`whereIn`+`orderBy('id')`+`lockForUpdate`, đã sửa 2026-08-01) → có thể thêm 1 `Shift` nữa (ca đang mở, khoá riêng — id không biết trước) | ⚠️ Payment trước TableSession trước Shift — **ngược văn tự luật "Shift → TableSession → Payment"**, xem ghi chú (c) |

### Ghi chú (a) — `UpdateOrderItem`/`RemoveOrderItem` không khoá `OrderItem`

Hai Action này khoá `Order` (đủ để chặn hai request cùng sửa đơn khác nhau
trong cùng phiếu) nhưng đọc `OrderItem` bằng `->findOrFail()` thường, không
`lockForUpdate()`. Vì khoá `Order` đã giữ ở mức phiếu (mọi `OrderItem` cùng
`order_id` gián tiếp được bảo vệ — hai request sửa hai dòng khác nhau của
CÙNG một phiếu vẫn phải xếp hàng chờ khoá `Order`), rủi ro thực tế thấp,
nhưng không đối xứng với `CancelOrderItem` (có khoá `OrderItem`) — nên ghi
lại để nhất quán nếu sau này có ai sửa lại.

### Ghi chú (b) — `CancelOrderItem` ngược chiều `UpdateOrderItemStatus`: khả năng kẹt chéo THẬT

Đây là cặp duy nhất trong toàn bộ Phase 1 có khả năng kẹt chéo thật, không
chỉ lệch quy ước bằng chữ:

- `CancelOrderItem` (huỷ món, Bước 6): khoá **`Order` trước, `OrderItem` sau**.
- `UpdateOrderItemStatus` (bếp bấm XONG, Bước 5): khoá **`OrderItem` trước, `Order` sau**.

Kịch bản thật: đúng lúc bếp bấm "XONG" cho MỘT dòng món (giữ khoá dòng đó,
đang chờ khoá `Order` để cập nhật `orders.status`), thu ngân/phục vụ bấm
huỷ ĐÚNG dòng món đó (giữ khoá `Order`, đang chờ khoá đúng dòng `OrderItem`
mà bếp đang giữ) → hai giao dịch chờ nhau vòng tròn, MySQL/MariaDB tự phát
hiện và huỷ một bên (lỗi `Deadlock found when trying to get lock`), người
dùng nhận lỗi 500 khó hiểu thay vì thông báo nghiệp vụ tiếng Việt. Đây
đúng là điều CLAUDE.md mục 11 muốn tránh ("thu ngân nhận lỗi khó hiểu").

Chưa sửa — báo cáo này chỉ đọc.

### Ghi chú (c) — `RecordPayment`/`VoidPayment` so với văn tự luật "Shift → TableSession → Payment"

Đọc đúng câu chữ CLAUDE.md mục 11 thì `Shift` phải được khoá TRƯỚC
`TableSession` TRƯỚC `Payment`. Thực tế:

- `RecordPayment`: khoá `TableSession` rồi mới `Shift` — ngược thứ tự viết,
  nhưng **nhất quán nội bộ** trong toàn hệ thống (không có Action nào khác
  khoá `Shift` trước `TableSession` để tạo ra một cặp ngược chiều thật —
  xem toàn bộ bảng trên, không có dòng nào khoá Shift-rồi-TableSession).
- `VoidPayment`: khoá `Payment` rồi `TableSession` rồi `Shift` — cũng ngược
  thứ tự viết, và cũng nhất quán với `RecordPayment` ở phần TableSession
  trước Shift (thêm Payment ở đầu, nhưng không có Action nào khác khoá
  TableSession/Shift rồi mới Payment để đụng ngược lại).

**Kết luận cho cặp Shift/TableSession/Payment:** dù lệch so với câu chữ đã
viết trong CLAUDE.md, **chưa tìm thấy một cặp Action nào thực sự khoá theo
hai chiều ngược nhau** (khác với cặp Order/OrderItem ở ghi chú (b), là một
kẹt chéo thật). Đây là lệch quy ước cần sửa cho đúng văn bản đã chốt, đề
phòng khi có Action mới thêm vào sau này vô tình khoá theo chiều
Shift-trước-TableSession — nhưng KHÔNG phải một lỗi 🔴 đang treo lơ lửng
ngay bây giờ như cặp (b). Việc sửa cho đúng câu chữ (đổi `RecordPayment`
sang khoá Shift trước TableSession, và `CalculateBill`/`CloseShift` cho
nhất quán) vẫn còn để mở trong `docs/viec-ton.md`.

---

## 7. Chuẩn bị Phase 2 (offline) và Phase 3 (trừ kho)

### a. Bảng/cột hiện do MÁY POS (client) sinh giá trị — theo 5 bảng Phase 2 cần ghi được lúc offline

| Bảng | Cột định danh do client sinh | Trạng thái |
|---|---|---|
| `table_sessions` | — | **CHƯA CÓ.** Không có cột `uuid`/định danh do client gửi lên trong `OpenTableSessionRequest`/`OpenTableSessionData` (chỉ có `dining_table_ids`, `primary_dining_table_id`, `guest_count`). `code` (`PH-yyyymmdd-NNNN`) do SERVER tự sinh — xem mục (c) |
| `orders` | `uuid` | **ĐÃ CÓ.** `PlaceOrderRequest` bắt buộc `uuid` (M2/M3), dùng để chống gửi trùng và sẽ là chốt để đồng bộ offline sau này |
| `order_items` | — | **CHƯA CÓ.** `PlaceOrderItemData` (một phần tử trong mảng `items` của `PlaceOrderRequest`) không có trường định danh riêng cho từng dòng món — chỉ có `product_id`, `product_variant_id`, `quantity`, `note`, `option_ids`. Định danh duy nhất là `id` tự tăng của DB, chỉ biết được SAU khi server tạo xong |
| `order_item_options` | — | **CHƯA CÓ** (không cần riêng — luôn đi kèm `order_items`, không phải đối tượng độc lập cần đồng bộ) |
| `payments` | `uuid` | **ĐÃ CÓ.** `RecordPaymentRequest` bắt buộc `uuid` (T9), đúng mẫu giống `orders.uuid` |

### b. Action nào PHỤ THUỘC đọc dữ liệu server ngay lúc bấm mới quyết định được

| Action | Đọc gì lúc chạy | Quyết định gì |
|---|---|---|
| `PlaceOrder` | `products.price`... thật ra là `product_variants.price` và `options.price_delta` (đọc trực tiếp từ DB, KHÔNG dùng giá trị do client gửi — client thậm chí không gửi giá) | Tính `unit_price`/`options_amount` ghi vào `order_items` — **máy POS KHÔNG tự tính giá cuối, luôn phải hỏi server**. Trả lời thẳng câu hỏi ví dụ trong yêu cầu: PlaceOrder đọc giá từ server, không dùng dữ liệu client tự có sẵn |
| `CalculateBill` | `users.role` (qua `TableSessionPolicy::discount()`) và có thể `users.pin_code` (qua `VerifyApproverPin`, khi vượt ngưỡng) | Ngưỡng % giảm giá được phép, có cần PIN duyệt hay không — quyết định này gắn với DANH TÍNH và QUYỀN của người đang thao tác tại đúng thời điểm đó, không cache được an toàn |
| `CancelOrderItem` | `order_items.status` hiện tại (đã `served` hay chưa) + `users.pin_code` khi cần duyệt | Có bắt buộc PIN duyệt hay không — trạng thái "đã phục vụ" có thể vừa đổi (do bếp/quầy vừa bấm) ngay trước khi lệnh huỷ tới, máy POS offline không biết được |
| `RecordPayment`/`VoidPayment` | `table_sessions.paid_amount`/`total_amount`, `shifts.status` hiện tại | Còn thiếu bao nhiêu, có được thu/hoàn hay không — số liệu này đổi liên tục theo mọi giao dịch khác đang chạy, không thể tính trước ở máy offline |
| `OpenTableSession`/`AttachTable`/`DetachTable`/`TransferTable` | `table_session_tables` — bàn có đang bị chiếm hay không (`whereNull('detached_at')->exists()`) | Có mở/ghép/chuyển được bàn hay không — đúng loại tài nguyên dùng chung nhiều tablet, bản chất không thể quyết định offline mà không có rủi ro hai máy cùng chọn một bàn |
| `UpdateOrderItemStatus` | `order_items.status` hiện tại (qua `StatusTransition::kiemTra`) | Có hợp lệ để chuyển "đã xong" hay không — vốn đã là màn hình chỉ-định-chạy-online (KHÔNG nằm trong yêu cầu chạy offline của Bước 10) |

**Tổng kết mục b:** hầu hết Action mang tính TIỀN hoặc CHIA SẺ TÀI NGUYÊN
(bàn) đều đọc số liệu SỐNG từ server để quyết định, không phải chỉ áp dụng
lại dữ liệu client đã có sẵn. Một client thật sự offline chỉ có thể LƯU
TẠM ý định (muốn gọi món gì, muốn huỷ món nào) rồi phát lại khi có mạng —
không thể tự tính trước kết quả cuối (giá, có đủ quyền không, bàn còn
trống không) với độ tin cậy 100%.

### c. Số thứ tự/mã hiện sinh ở đâu

| Cột | Sinh ở đâu | Cách sinh | Chặn Phase 2? |
|---|---|---|---|
| `orders.uuid` | **Client** | POS tự sinh UUID trước khi gửi | Không — đúng mẫu cần cho offline |
| `payments.uuid` | **Client** | POS tự sinh UUID trước khi gửi | Không — đúng mẫu cần cho offline |
| `orders.sequence_no` | **Server** | `Order::query()->where('table_session_id', ...)->max('sequence_no') + 1` — đọc DB sống tại thời điểm tạo | **CÓ** — hai máy offline cùng tạo phiếu gọi món cho cùng một lượt khách rồi đồng bộ sau sẽ đụng số thứ tự trùng hoặc phải tự dàn xếp lại thứ tự |
| `table_sessions.code` | **Server** | `sinhMaLuotKhach()`: đếm số lượt khách đã mở trong ngày (`whereDate('opened_at', ...)->count() + 1`) rồi ghép `PH-yyyymmdd-NNNN` | **CÓ** — cùng lý do, đếm sống trên DB, hai máy offline không thể tự sinh mã không trùng nhau một cách an toàn |
| `shifts.code` | **Server** | `sinhMaCa()` (tương tự cơ chế trên) | Ít quan trọng hơn (mỗi quán chỉ một ca mở cùng lúc — `uq_shifts_only_one_open` — nên va chạm khó xảy ra hơn `orders`/`table_sessions`) nhưng vẫn cùng một kiểu sinh giá trị chặn offline |
| `Idempotency-Key` (header, không phải cột DB) | **Client** | UUID tự sinh mỗi request ghi | Không tự nó chặn — nhưng chỉ chống gửi trùng trong 24h (cache), KHÔNG phải định danh nghiệp vụ bền vững để đối chiếu khi đồng bộ offline nhiều ngày |

**Trả lời thẳng câu hỏi (c):** `orders.sequence_no` và `table_sessions.code`
là hai chỗ chặn đường Phase 2 rõ nhất — cả hai đều cần một phép ĐẾM đọc từ
DB sống tại đúng thời điểm tạo, không thể tính trước một cách an toàn trên
máy offline không có kết nối tới DB đó.

### d. Thời điểm "đã tiêu thụ" và cột đánh dấu

Cột `order_items.served_at` (nullable `dateTime`, `database/migrations/
..._000013_create_order_items_table.php`) là mốc được thiết kế để đánh dấu
đúng thời điểm này. Nó được ghi bởi **`UpdateOrderItemStatus`** (Bước 5,
bếp/quầy bấm "XONG" trên KDS):

```php
// app/Domain/Ordering/Actions/UpdateOrderItemStatus.php
$item->update([
    'status' => OrderItemStatus::Served,
    'served_at' => now(),
]);
```

Đây chính là thời điểm nên dùng làm mốc "đã tiêu thụ" cho logic trừ kho ở
Phase 3: `served_at IS NOT NULL` = nguyên liệu đã dùng, không hoàn kho khi
huỷ; `served_at IS NULL` = chưa bưng ra, huỷ thì hoàn kho bình thường.

**Lỗ hổng đã xác nhận ở báo cáo trước (câu hỏi riêng về `CancelOrderItem`,
cùng ngày 2026-08-01), nhắc lại ở đây vì liên quan trực tiếp câu hỏi (d):**
dòng `OrderItem` MỚI sinh ra khi huỷ MỘT PHẦN số lượng
(`CancelOrderItem::tachVaHuyMotPhan()`) không kế thừa `served_at` của dòng
gốc — luôn tạo ra với `served_at = NULL` bất kể dòng gốc đã `served` hay
chưa. Nếu Phase 3 dùng đúng cột này làm mốc quyết định hoàn kho, đây là chỗ
BẮT BUỘC phải sửa trước khi bắt đầu Phase 3, không chỉ là một khuyến nghị.
