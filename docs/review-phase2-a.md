# REVIEW PHASE 2 — PHẦN A

> Sinh ngày 05/08/2026 để đưa lên Opus soát cuối trước khi đóng Phase 2.
> File này CHỈ BÁO CÁO — không sửa code nào trong lúc soạn.
> Chia hai file: **PHẦN A** = mục 1-3 (danh sách file, nội dung đầy đủ phần đồng bộ, nội dung đầy đủ các Action khác).
> **PHẦN B** (`docs/review-phase2-b.md`) = mục 4-8 (ma trận xung đột, ma trận bất biến, ma trận khoá, chuẩn bị Phase 3, tự quyết/không chắc) + output 5 lệnh.

⚠️ **Lưu ý về cách nhóm theo Bước ở mục 1**: lịch sử git của Phase 2 chỉ có 3 commit lớn (`92f32a8` chore backup Bước 2, `1ce52ae` feat p2-b2, `ba81b46` feat p2-b5-b8), không tách theo từng bước. Việc gán file → Bước dưới đây là **suy luận** từ tên file, nội dung comment trong code, và mốc ngày trong `docs/viec-ton.md`, không phải lấy thẳng từ commit. Chỗ nào suy luận không chắc, đã ghi rõ trong mục 8 của Phần B.

---

## 1. DANH SÁCH FILE ĐÃ TẠO/SỬA Ở PHASE 2, NHÓM THEO BƯỚC 0-8

Tổng cộng (theo `git diff --shortstat bd2efb6..HEAD`, so với commit cuối Phase 1 `bd2efb6`): **146 file, +12.717/-164 dòng**.

### Bước 0 — Kiểm toán định danh (chỉ báo cáo, không sửa code)

| File | Việc |
|---|---|
| `docs/kiem-toan-offline.md` | Báo cáo kiểm toán: định danh nào cần client sinh, thứ tự khoá hiện tại |

Không có test riêng (đúng luật Bước 0: "Được phép CHỈ BÁO CÁO").

### Bước 1 — Tách bàn, chuyển món giữa hai lượt khách — **16 test**

| File | Việc |
|---|---|
| `app/Domain/Ordering/Actions/SplitTableSession.php` | Tách lượt khách: một số bàn + một số dòng món sang lượt mới |
| `app/Domain/Ordering/Actions/MoveOrderItem.php` | Chuyển dòng món đã gọi sang lượt khách khác đang mở |
| `app/Domain/Ordering/DTO/SplitTableSessionData.php` | DTO đầu vào SplitTableSession |
| `app/Domain/Ordering/DTO/MoveOrderItemData.php` | DTO đầu vào MoveOrderItem |
| `app/Http/Requests/SplitTableSessionRequest.php` | Validate + phân quyền `POST .../split` |
| `app/Http/Requests/MoveOrderItemRequest.php` | Validate + phân quyền `POST .../move-items` |
| `app/Http/Controllers/Api/TableSessionController.php` (sửa) | Thêm action `split`, `moveItems` |
| `app/Domain/Ordering/Policies/TableSessionPolicy.php` (sửa) | Thêm ability `splitTableSession`, `moveItems` |
| `routes/api.php` (sửa) | Route `split`, `move-items` |

Test: `tests/Feature/Ordering/SplitTableSessionTest.php` (9), `tests/Feature/Ordering/MoveOrderItemTest.php` (7).

### Bước 2 — Định danh do máy POS sinh cho mọi bảng ghi được offline — **63 test**

| File | Việc |
|---|---|
| `database/migrations/2026_08_02_000001_add_uuid_to_table_sessions_table.php` | Thêm cột `uuid` |
| `database/migrations/2026_08_02_000002_add_uuid_to_order_items_table.php` | Thêm cột `uuid` |
| `database/migrations/2026_08_02_000003_add_uuid_to_order_item_options_table.php` | Thêm cột `uuid` |
| `database/migrations/2026_08_04_000001_make_client_uuid_not_null.php` | Siết ba cột trên thành `NOT NULL` sau khi backfill |
| `app/Console/Commands/BackfillClientUuids.php` | Lệnh `pos:backfill-uuid`, đã chạy một lần thật trên dữ liệu cũ |
| `app/Domain/Ordering/Actions/OpenTableSession.php` (sửa) | Nhận `uuid` do client sinh thay vì tự sinh |
| `app/Domain/Ordering/Actions/CancelOrderItem.php` (sửa) | Huỷ một phần nhận `newItemUuid` + `optionUuids` do client sinh |
| `app/Domain/Ordering/DTO/OpenTableSessionData.php`, `PlaceOrderData.php`, `PlaceOrderItemData.php`, `PlaceOrderItemOptionData.php`, `CancelOrderItemData.php` (sửa) | Thêm trường uuid |
| `app/Http/Requests/OpenTableSessionRequest.php`, `PlaceOrderRequest.php`, `CancelOrderItemRequest.php` (sửa) | Validate uuid bắt buộc |
| `database/factories/*.php` (sửa hàng loạt: `CategoryFactory`, `DiningTableFactory`, `OptionFactory`, `ProductFactory`, `UserFactory`, `ShiftFactory`, `TableSessionFactory`, `OrderItemFactory`, `OrderItemOptionFactory`) | Đổi cột UNIQUE ngẫu nhiên (mã bàn, tên nhóm...) sang bộ đếm tăng dần — sửa lỗi test đỏ ngẫu nhiên (xem `docs/viec-ton.md` dòng "ĐÃ XỬ LÝ 04/08") |
| `docs/review-buoc2-phase2.md` | Báo cáo riêng của Bước 2 (đã có từ trước, không phải file mới của lượt review này) |

Test: `tests/Feature/Support/ClientUuidCoverageTest.php` (11), `tests/Feature/Support/NoUuidlessWriteTest.php` (5), `tests/Feature/Console/BackfillClientUuidsTest.php` (0 — xem ghi chú trong chính file, hai test cũ đã bị xoá vì không còn kịch bản để giả lập sau khi cột NOT NULL), `tests/Unit/Ordering/SinhMaLuotKhachTest.php` (2), `tests/Unit/Staffing/SinhMaCaTest.php` (2), `tests/Feature/Ordering/TableConcurrencyTest.php` (3), `tests/Feature/Staffing/Shift/OpenShiftTest.php` (7), `tests/Feature/Ordering/OpenTableSessionTest.php` (13, một phần là test cũ Phase 1 + test mới cho uuid), `tests/Feature/Ordering/PlaceOrderTest.php` (13, tương tự), `tests/Feature/Ordering/SendToKitchenTest.php` (5), `tests/Feature/Ordering/CancelOrderItemTest.php` (15, gồm cả test huỷ một phần với uuid mới), `tests/Feature/Api/IdempotencyCoverageTest.php` (1), `tests/Feature/Support/AuthGuardIsolationTest.php` (1).

*(Số test liệt kê ở đây là tổng số `it()` hiện có trong file — nhiều file này đã tồn tại từ Phase 1 và chỉ được BỔ SUNG test cho uuid, không phải toàn bộ số test là mới viết ở Bước 2.)*

### Bước 3 — Kho dữ liệu trên máy POS (Dexie) + hàng chờ gửi — **6 test (JS)**

| File | Việc |
|---|---|
| `resources/js/lib/db.js` | Định nghĩa Dexie DB (IndexedDB) trên trình duyệt |
| `resources/js/lib/queue.js` | Hàng chờ gửi (queue) các thao tác offline |
| `resources/js/lib/offline.js` | `duocPhepKhiMatMang()` — luật 5 việc bị chặn khi offline |
| `resources/js/lib/menuSync.js` | Đồng bộ thực đơn xuống máy để bán được khi mất mạng |
| `resources/js/pos.js` (sửa lớn, +644/-… dòng) | Nối luồng bán hàng với Dexie/queue/offline |
| `vitest.config.js`, `package.json`/`package-lock.json` (sửa) | Thêm Vitest làm test runner cho JS |

Test: `tests/js/queue.test.js` (6 test đơn vị cho lớp hàng chờ, không đụng trình duyệt thật — chưa có e2e thật, xem mục 8 Phần B).

### Bước 4 — Đồng bộ hàng loạt (`POST /sync/batch`) — **17 test**

| File | Việc |
|---|---|
| `app/Domain/Sync/Actions/SyncBatch.php` | Action chính — xem toàn văn ở mục 2 |
| `app/Domain/Sync/DTO/SyncBatchData.php`, `SyncOperationData.php` | DTO |
| `app/Domain/Sync/Enums/OperationType.php`, `OperationStatus.php`, `ConflictKind.php`, `ConflictStatus.php` | Enum |
| `app/Domain/Sync/Models/SyncAppliedOp.php`, `SyncConflict.php` | Model |
| `app/Http/Controllers/Api/SyncBatchController.php` | `POST /api/v1/sync/batch` |
| `app/Http/Requests/SyncBatchRequest.php` | Validate gói đồng bộ |
| `app/Exceptions/SyncBatchLockedException.php`, `ThaoTacGocKhongTimThayException.php` | Exception riêng |
| `database/migrations/2026_08_04_000002_create_sync_conflicts_table.php`, `..._000003_create_sync_applied_ops_table.php` | DDL |
| `app/Console/Commands/CleanupSyncAppliedOps.php` | Dọn `sync_applied_ops` cũ |
| `database/factories/SyncConflictFactory.php` | Factory |
| `docs/thiet-ke-dong-bo.md` | Thiết kế gốc (Opus 5, đã duyệt) — nguồn chân lý của ma trận mục 5 Phần B |

Test: `tests/Feature/Sync/SyncBatchHappyPathTest.php` (4), `tests/Feature/Sync/SyncBatchIdempotencyTest.php` (4), `tests/Feature/Sync/SyncBatchConflictMatrixTest.php` (9 — đúng 9 dòng của ma trận, xem Phần B mục 4).

### Bước 5 — Màn hình xử lý xung đột cần người quyết — **30 test**

| File | Việc |
|---|---|
| `app/Domain/Sync/Actions/ResolveSyncConflict.php` | Action chính — xem toàn văn ở mục 2 |
| `app/Domain/Sync/DTO/ResolveSyncConflictData.php` | DTO |
| `app/Http/Controllers/Api/SyncConflictController.php` | `GET /sync/conflicts`, `GET /sync/conflicts/pending-count`, `POST /sync/conflicts/{id}/resolve` |
| `app/Http/Requests/ResolveSyncConflictRequest.php` | Validate + phân quyền |
| `app/Http/Resources/SyncConflictResource.php` | Hình dạng JSON trả về |
| `app/Filament/Resources/SyncConflictResource.php` + `Pages/ManageSyncConflicts.php` | Panel quản trị (đường phụ, xem `docs/viec-ton.md`) |
| `app/Domain/Staffing/Actions/CloseShift.php` (sửa) | Thêm `kiemTraXungDotChuaXuLy()` — chặn đóng ca |

Test: `tests/Feature/Sync/ResolveSyncConflictTest.php` (18), `tests/Feature/Api/SyncConflictControllerTest.php` (8), `tests/Feature/Staffing/Shift/CloseShiftBlockedByConflictTest.php` (4).

### Bước 6 — Khuyến mãi — **14 test**

| File | Việc |
|---|---|
| `app/Domain/Billing/Actions/ApplyPromotion.php` | Action chính — xem toàn văn ở mục 3 |
| `app/Domain/Billing/Actions/TogglePromotionActive.php` | Bật/tắt khuyến mãi |
| `app/Domain/Billing/DTO/ApplyPromotionData.php` | DTO |
| `app/Domain/Billing/Enums/PromotionType.php`, `PromotionAppliesTo.php` | Enum |
| `app/Domain/Billing/Models/Promotion.php` | Model |
| `database/migrations/2026_08_04_000004_create_promotions_table.php`, `..._000005_add_promotion_id_to_table_sessions_table.php` | DDL |
| `app/Filament/Resources/PromotionResource.php` + `Pages/ManagePromotions.php` | Panel quản trị tạo/sửa khuyến mãi |
| `database/factories/PromotionFactory.php` | Factory |

Test: `tests/Feature/Billing/ApplyPromotionTest.php` (14). *(Chưa có endpoint/nút áp khuyến mãi trên màn bán hàng thật — xem mục 8 Phần B.)*

### Bước 7 — Thanh toán QR (VietQR) — **13 test**

| File | Việc |
|---|---|
| `app/Support/VietQr.php` | Sinh chuỗi VietQR (EMV QR) |
| `config/vietqr.php` | Cấu hình ngân hàng/số tài khoản |
| `app/Domain/Billing/Queries/GetVietQrForTableSession.php` | Query dựng QR cho một lượt khách |
| `app/Http/Controllers/Api/VietQrController.php` | `GET /table-sessions/{id}/vietqr` |
| `app/Http/Resources/VietQrResource.php` | Hình dạng JSON |

Test: `tests/Unit/Support/VietQrTest.php` (7), `tests/Feature/Billing/VietQrControllerTest.php` (6).

### Bước 8 — Bảng tổng hợp ngày + màn hình chủ quán — **10 test**

| File | Việc |
|---|---|
| `app/Domain/Reporting/Actions/SummarizeDailyReport.php` | Tổng hợp doanh thu một ngày |
| `app/Domain/Reporting/Jobs/SummarizeDailyReportJob.php` | Job chạy nền, kích hoạt sau khi đóng ca |
| `app/Domain/Reporting/Models/DailySummary.php`, `ProductSaleDaily.php` | Model |
| `app/Domain/Reporting/Queries/GetOwnerDashboard.php` | Query cho màn hình chủ quán |
| `app/Console/Commands/SummarizeDailyReportCommand.php` | Lệnh `report:summarize` chạy tay/lịch |
| `app/Filament/Pages/BaoCaoChuQuan.php` + `resources/views/filament/pages/bao-cao-chu-quan.blade.php` | Trang tổng hợp |
| `app/Filament/Widgets/DoanhThu7NgayWidget.php`, `DoanhThuTongQuanWidget.php`, `Top10MonBanChayWidget.php` + blade | Widget biểu đồ |
| `database/migrations/2026_08_04_000006_create_daily_summaries_table.php`, `..._000007_create_product_sales_daily_table.php` | DDL |
| `database/factories/DailySummaryFactory.php`, `ProductSaleDailyFactory.php` | Factory |
| `app/Domain/Staffing/Actions/CloseShift.php` (sửa, cùng file với Bước 5) | Dispatch `SummarizeDailyReportJob` sau khi đóng ca |

Test: `tests/Feature/Reporting/SummarizeDailyReportTest.php` (2), `tests/Feature/Reporting/GetOwnerDashboardTest.php` (5), `tests/Feature/Reporting/BaoCaoChuQuanAccessTest.php` (3).

### Không thuộc riêng một Bước nào (hạ tầng / tài liệu / xuyên suốt)

| File | Việc |
|---|---|
| `CLAUDE.md` (sửa) | Sửa luật mục 11 về thứ tự khoá (02/08) |
| `docs/PHASE.md` | Theo dõi bước đang mở |
| `docs/viec-ton.md` | Sổ ghi việc tồn — 30 dòng phát sinh trong Phase 2 |
| `docs/schema.md` (sửa, +235 dòng) | Thêm nhóm bảng F/G/H, bất biến S1-S2, DDL sync/promotion/reporting |
| `docker/mysql/init/01-schema.sql` (sửa, +173 dòng) | DDL cho môi trường Docker demo |
| `bootstrap/app.php` (sửa) | Đăng ký exception handler cho `SyncBatchLockedException` → 429 |
| `app/Console/Commands/PosDemo.php` (sửa lớn, +281 dòng) | Kịch bản diễn tập bao gồm tách bàn |
| `phpunit.xml` (sửa) | `DB_CONNECTION` → `mariadb` |

---

## 2. NỘI DUNG ĐẦY ĐỦ — PHẦN ĐỒNG BỘ (Bước 4-5)

### 2.1. `app/Domain/Sync/Actions/SyncBatch.php`

```php
<?php

declare(strict_types=1);

namespace App\Domain\Sync\Actions;

use App\Domain\Billing\Actions\RecordPayment;
use App\Domain\Billing\DTO\RecordPaymentData;
use App\Domain\Billing\Enums\PaymentMethod;
use App\Domain\Billing\Models\Payment;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Ordering\Actions\AttachTable;
use App\Domain\Ordering\Actions\CancelOrderItem;
use App\Domain\Ordering\Actions\CloseTableSession;
use App\Domain\Ordering\Actions\DetachTable;
use App\Domain\Ordering\Actions\OpenTableSession;
use App\Domain\Ordering\Actions\PlaceOrder;
use App\Domain\Ordering\Actions\SendToKitchen;
use App\Domain\Ordering\DTO\AttachTableData;
use App\Domain\Ordering\DTO\CancelOrderItemData;
use App\Domain\Ordering\DTO\CloseTableSessionData;
use App\Domain\Ordering\DTO\DetachTableData;
use App\Domain\Ordering\DTO\OpenTableSessionData;
use App\Domain\Ordering\DTO\PlaceOrderData;
use App\Domain\Ordering\DTO\PlaceOrderItemData;
use App\Domain\Ordering\DTO\PlaceOrderItemOptionData;
use App\Domain\Ordering\DTO\SendToKitchenData;
use App\Domain\Ordering\Enums\TableSessionStatus;
use App\Domain\Ordering\Models\Order;
use App\Domain\Ordering\Models\OrderItem;
use App\Domain\Ordering\Models\TableSession;
use App\Domain\Ordering\Models\TableSessionTable;
use App\Domain\Sync\DTO\SyncBatchData;
use App\Domain\Sync\DTO\SyncOperationData;
use App\Domain\Sync\Enums\ConflictKind;
use App\Domain\Sync\Enums\ConflictStatus;
use App\Domain\Sync\Enums\OperationStatus;
use App\Domain\Sync\Enums\OperationType;
use App\Domain\Sync\Models\SyncAppliedOp;
use App\Domain\Sync\Models\SyncConflict;
use App\Exceptions\DomainException;
use App\Exceptions\SyncBatchLockedException;
use App\Exceptions\ThaoTacGocKhongTimThayException;
use App\Support\Money;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

/**
 * Áp dụng một gói thao tác gửi lên từ máy POS offline — theo đúng
 * docs/thiet-ke-dong-bo.md.
 *
 * KHÔNG viết logic nghiệp vụ ở đây (mục 0 của thiết kế). Mọi thay đổi dữ liệu
 * đi qua đúng Action Phase 1 đã có — Action này chỉ xếp thứ tự, phát hiện va
 * chạm theo ma trận mục 5, và gọi Action tương ứng trong DB::transaction
 * riêng của chính Action đó (mục 7.3, không bọc thêm transaction ở đây).
 *
 * Suy luận thêm ngoài thiết kế (đã trình bày và được duyệt trước khi viết):
 *  - CHỐNG TRÙNG Ở TẦNG ĐỒNG BỘ theo op_uuid (bảng `sync_applied_ops`, xem
 *    ketQuaApplied()) — độc lập với uuid nghiệp vụ trên từng bảng, áp dụng
 *    cho MỌI loại thao tác. Sửa đúng lỗi thật: gói đã áp dụng xong nhưng máy
 *    POS không nhận được response (mạng rớt lúc trả kết quả) rồi gửi lại —
 *    trước đây 5 loại thao tác không có uuid riêng (attach/detach/
 *    send_to_kitchen/close_session/huỷ món toàn bộ) sẽ bị Action gốc từ chối
 *    lần hai (VD SendToKitchen báo "đã gửi rồi"), khiến máy POS hiện lỗi đỏ
 *    và nhân viên bấm lại — bếp nhận tem hai lần. Giờ tra sổ TRƯỚC khi gọi
 *    bất kỳ Action nào, có rồi thì trả "duplicate" ngay, không chạy lại.
 *  - Xung đột (`sync_conflicts`) tự chống trùng theo `uq_sync_conflicts_op`
 *    sẵn có — gửi lại một thao tác đã ghi xung đột thì trả đúng conflict_id
 *    cũ, không tạo bản ghi chờ thứ hai (xem taoConflict()).
 *  - Bộ đếm "deferred quá 5 lần" (mục 3.3) dùng Cache::increment() theo
 *    op_uuid, không thêm cột/bảng mới.
 *  - `record_payment` dòng 7: RecordPayment tra CA CỦA CHÍNH LƯỢT KHÁCH (không
 *    tự lấy ca đang mở bất kỳ) — ca gốc đã đóng thì luôn từ chối. Không có
 *    Action nào đổi shift_id để "tự gán sang ca mới", nên dòng 7 LUÔN thành
 *    conflict (đúng "Cần người: ✅" của thiết kế), auto_action='khong_lam_gi',
 *    "gán sang ca đang mở" chỉ là một lựa chọn cho người quyết, không tự làm.
 *  - Mọi thao tác trong một gói được gán chung một người thực hiện: người
 *    đang đăng nhập trên máy POS lúc gửi gói (SyncBatchData::receivedByUserId).
 */
final class SyncBatch
{
    private const SO_LAN_DEFER_TOI_DA = 5;

    public function __construct(
        private readonly OpenTableSession $openTableSession,
        private readonly AttachTable $attachTable,
        private readonly DetachTable $detachTable,
        private readonly PlaceOrder $placeOrder,
        private readonly SendToKitchen $sendToKitchen,
        private readonly CancelOrderItem $cancelOrderItem,
        private readonly RecordPayment $recordPayment,
        private readonly CloseTableSession $closeTableSession,
    ) {}

    /** @return list<array<string, mixed>> */
    public function handle(SyncBatchData $data): array
    {
        if (count($data->operations) > 200) {
            throw new DomainException('Một gói tối đa 200 thao tác.');
        }

        $lock = Cache::lock('sync:batch', 120);

        try {
            return $lock->block(5, fn () => $this->apDungCaGoi($data));
        } catch (LockTimeoutException) {
            throw new SyncBatchLockedException('Một gói đồng bộ khác đang được xử lý. Thử lại sau ít giây.');
        }
    }

    /** @return list<array<string, mixed>> */
    private function apDungCaGoi(SyncBatchData $data): array
    {
        ['xep' => $daXep, 'vong_lap' => $vongLap] = $this->sapXepThaoTac($data->operations);

        $ketQua = [];
        /** @var array<string, OperationStatus> $trangThaiTheoUuid */
        $trangThaiTheoUuid = [];
        /** @var array<string, array<string, mixed>> $ketQuaTheoUuid */
        $ketQuaTheoUuid = [];

        foreach ($vongLap as $op) {
            $chiTiet = [
                'op_uuid' => $op->opUuid,
                'status' => OperationStatus::Rejected->value,
                'reason' => 'Thao tác nằm trong một vòng lặp phụ thuộc (depends_on quay vòng) — lỗi máy POS, không phải va chạm.',
            ];
            $trangThaiTheoUuid[$op->opUuid] = OperationStatus::Rejected;
            $ketQuaTheoUuid[$op->opUuid] = $chiTiet;
            $ketQua[] = $chiTiet;
        }

        foreach ($daXep as $op) {
            $chiTiet = $this->apDungMotThaoTac($op, $data, $trangThaiTheoUuid, $ketQuaTheoUuid);
            $trangThaiTheoUuid[$op->opUuid] = OperationStatus::from($chiTiet['status']);
            $ketQuaTheoUuid[$op->opUuid] = $chiTiet;
            $ketQua[] = $chiTiet;
        }

        return $ketQua;
    }

    /**
     * Kahn's algorithm — sắp theo đồ thị phụ thuộc trong CHÍNH GÓI NÀY, cùng
     * bậc thì theo occurred_at rồi vị trí gốc (mục 4.1). Thao tác còn sót lại
     * sau vòng lặp chính là những cái nằm trong một vòng phụ thuộc (#4).
     *
     * @param  list<SyncOperationData>  $operations
     * @return array{xep: list<SyncOperationData>, vong_lap: list<SyncOperationData>}
     */
    private function sapXepThaoTac(array $operations): array
    {
        $theoUuid = [];
        foreach ($operations as $op) {
            $theoUuid[$op->opUuid] = $op;
        }

        $bacVao = [];
        $conCua = [];
        foreach ($operations as $op) {
            $bacVao[$op->opUuid] ??= 0;
            foreach ($op->dependsOn as $chaUuid) {
                if (isset($theoUuid[$chaUuid])) {
                    $bacVao[$op->opUuid]++;
                    $conCua[$chaUuid][] = $op->opUuid;
                }
            }
        }

        $sapXepHangCho = static function (array $ds): array {
            usort($ds, fn (SyncOperationData $a, SyncOperationData $b) => $a->occurredAt->timestamp <=> $b->occurredAt->timestamp
                ?: $a->viTriGoc <=> $b->viTriGoc);

            return $ds;
        };

        $sanSang = $sapXepHangCho(array_values(array_filter($operations, fn ($op) => $bacVao[$op->opUuid] === 0)));

        $daXep = [];
        while ($sanSang !== []) {
            $op = array_shift($sanSang);
            $daXep[] = $op;

            $conMoi = [];
            foreach ($conCua[$op->opUuid] ?? [] as $conUuid) {
                $bacVao[$conUuid]--;
                if ($bacVao[$conUuid] === 0) {
                    $conMoi[] = $theoUuid[$conUuid];
                }
            }

            $sanSang = $sapXepHangCho(array_merge($sanSang, $conMoi));
        }

        $uuidDaXep = array_flip(array_map(fn ($op) => $op->opUuid, $daXep));
        $vongLap = array_values(array_filter($operations, fn ($op) => ! isset($uuidDaXep[$op->opUuid])));

        return ['xep' => $daXep, 'vong_lap' => $vongLap];
    }

    /**
     * @param  array<string, OperationStatus>  $trangThaiTheoUuid
     * @param  array<string, array<string, mixed>>  $ketQuaTheoUuid
     * @return array<string, mixed>
     */
    private function apDungMotThaoTac(
        SyncOperationData $op,
        SyncBatchData $data,
        array $trangThaiTheoUuid,
        array $ketQuaTheoUuid,
    ): array {
        // Lớp chống trùng Ở TẦNG ĐỒNG BỘ, theo op_uuid — độc lập với uuid
        // nghiệp vụ trên từng bảng (mục 3.3 docs/thiet-ke-dong-bo.md). Tra
        // TRƯỚC MỌI THỨ, kể cả trước khi xét thao tác cha: một thao tác đã
        // áp dụng xong rồi thì không cần biết cha nó là gì nữa.
        $daApDung = SyncAppliedOp::query()->find($op->opUuid);
        if ($daApDung !== null) {
            return ['op_uuid' => $op->opUuid, 'status' => OperationStatus::Duplicate->value, ...$daApDung->result_payload];
        }

        foreach ($op->dependsOn as $chaUuid) {
            $trangThaiCha = $trangThaiTheoUuid[$chaUuid] ?? null;

            if ($trangThaiCha === OperationStatus::Conflict) {
                $conflictIdCuaCha = (int) $ketQuaTheoUuid[$chaUuid]['conflict_id'];
                $this->gomVaoCum($conflictIdCuaCha, $op);

                return ['op_uuid' => $op->opUuid, 'status' => OperationStatus::Conflict->value, 'conflict_id' => $conflictIdCuaCha];
            }

            if ($trangThaiCha === OperationStatus::Rejected) {
                return ['op_uuid' => $op->opUuid, 'status' => OperationStatus::Rejected->value, 'reason' => "Thao tác cha {$chaUuid} bị từ chối, thao tác này không thể áp dụng theo."];
            }
        }

        try {
            return match ($op->type) {
                OperationType::OpenSession => $this->xuLyOpenSession($op, $data),
                OperationType::AttachTable => $this->xuLyGoiActionDon($op, $data, fn () => $this->attachTable->handle(new AttachTableData(
                    tableSessionId: $this->timIdLuotKhach((string) $op->payload['table_session_uuid']),
                    diningTableId: (int) $op->payload['dining_table_id'],
                    attachedByUserId: $data->receivedByUserId,
                ))),
                OperationType::DetachTable => $this->xuLyGoiActionDon($op, $data, fn () => $this->detachTable->handle(new DetachTableData(
                    tableSessionId: $this->timIdLuotKhach((string) $op->payload['table_session_uuid']),
                    diningTableId: (int) $op->payload['dining_table_id'],
                ))),
                OperationType::PlaceOrder => $this->xuLyPlaceOrder($op, $data),
                OperationType::SendToKitchen => $this->xuLySendToKitchen($op, $data),
                OperationType::CancelOrderItem => $this->xuLyCancelOrderItem($op, $data),
                OperationType::RecordPayment => $this->xuLyRecordPayment($op, $data),
                OperationType::CloseSession => $this->xuLyGoiActionDon($op, $data, fn () => $this->closeTableSession->handle(new CloseTableSessionData(
                    tableSessionId: $this->timIdLuotKhach((string) $op->payload['table_session_uuid']),
                    closedByUserId: $data->receivedByUserId,
                ))),
            };
        } catch (ThaoTacGocKhongTimThayException) {
            return $this->xuLyThieuThaoTacGoc($op, $data);
        }
    }

    // ── open_session (dòng 2) ───────────────────────────────────────────

    /** @return array<string, mixed> */
    private function xuLyOpenSession(SyncOperationData $op, SyncBatchData $data): array
    {
        $uuid = (string) $op->payload['uuid'];

        $daCo = TableSession::query()->where('uuid', $uuid)->first();
        if ($daCo !== null) {
            return $this->ketQuaDuplicate($op, ['table_session_id' => $daCo->id, 'code' => $daCo->code]);
        }

        $idBan = array_map('intval', $op->payload['dining_table_ids']);
        $banDangBiChiem = TableSessionTable::query()
            ->whereIn('dining_table_id', $idBan)
            ->whereNull('detached_at')
            ->exists();

        if ($banDangBiChiem) {
            $conflict = $this->taoConflict(
                op: $op,
                data: $data,
                kind: ConflictKind::HaiMayMoBan,
                autoAction: 'khong_lam_gi',
                messageVi: "Máy POS số {$data->deviceId} mở bàn lúc {$op->occurredAt->format('H:i')} khi mất mạng, nhưng bàn này đã thuộc một lượt khách khác. Tem bếp có thể đã in — bếp đang nấu.",
                options: [
                    ['key' => 'gop', 'label' => 'Gộp — chuyển các món của máy này vào lượt khách đang giữ bàn'],
                    ['key' => 'tach', 'label' => 'Tách — chọn bàn khác cho lượt khách này, dựng lại từ đầu'],
                ],
                tableSessionId: null,
                isUrgent: true,
            );

            return ['op_uuid' => $op->opUuid, 'status' => OperationStatus::Conflict->value, 'conflict_id' => $conflict->id];
        }

        $luotKhach = $this->openTableSession->handle(new OpenTableSessionData(
            uuid: $uuid,
            diningTableIds: $idBan,
            primaryDiningTableId: (int) $op->payload['primary_dining_table_id'],
            guestCount: (int) $op->payload['guest_count'],
            openedByUserId: $data->receivedByUserId,
        ));

        return $this->ketQuaApplied($op, $data, ['table_session_id' => $luotKhach->id, 'code' => $luotKhach->code]);
    }

    // ── place_order (dòng 6, 8, 9) ──────────────────────────────────────

    /** @return array<string, mixed> */
    private function xuLyPlaceOrder(SyncOperationData $op, SyncBatchData $data): array
    {
        $uuid = (string) $op->payload['uuid'];

        $daCo = Order::query()->where('uuid', $uuid)->first();
        if ($daCo !== null) {
            return $this->ketQuaDuplicate($op, ['order_id' => $daCo->id]);
        }

        $session = TableSession::query()->where('uuid', (string) $op->payload['table_session_uuid'])->first();
        if ($session === null) {
            throw new ThaoTacGocKhongTimThayException;
        }

        if ($session->status === TableSessionStatus::Closed) {
            $conflict = $this->taoConflict(
                op: $op,
                data: $data,
                kind: ConflictKind::LuotDaDong,
                autoAction: 'khong_lam_gi',
                messageVi: "Máy POS số {$data->deviceId} gọi món lúc {$op->occurredAt->format('H:i')} khi mất mạng, nhưng lượt khách {$session->code} đã thanh toán xong.",
                options: [
                    ['key' => 'mo_luot_moi', 'label' => 'Mở lượt khách mới cho các món này'],
                    ['key' => 'huy_mon', 'label' => 'Huỷ các món này — gọi nhầm'],
                ],
                tableSessionId: $session->id,
                isUrgent: true,
            );

            return ['op_uuid' => $op->opUuid, 'status' => OperationStatus::Conflict->value, 'conflict_id' => $conflict->id];
        }

        if ($session->status === TableSessionStatus::Void) {
            $conflict = $this->taoConflict(
                op: $op,
                data: $data,
                kind: ConflictKind::LuotDaHuy,
                autoAction: 'khong_lam_gi',
                messageVi: "Máy POS số {$data->deviceId} gọi món lúc {$op->occurredAt->format('H:i')} khi mất mạng, nhưng lượt khách {$session->code} đã bị huỷ.",
                options: [
                    ['key' => 'mo_luot_moi', 'label' => 'Mở lượt khách mới cho các món này'],
                    ['key' => 'huy_mon', 'label' => 'Huỷ các món này'],
                ],
                tableSessionId: $session->id,
                isUrgent: true,
            );

            return ['op_uuid' => $op->opUuid, 'status' => OperationStatus::Conflict->value, 'conflict_id' => $conflict->id];
        }

        $giaLech = false;
        $items = [];
        foreach ($op->payload['items'] as $itemPayload) {
            $variant = ProductVariant::query()->find((int) $itemPayload['product_variant_id']);
            if ($variant !== null && isset($itemPayload['client_unit_price']) && (int) $itemPayload['client_unit_price'] !== $variant->price) {
                $giaLech = true;
            }

            $options = [];
            foreach ($itemPayload['options'] ?? [] as $optionPayload) {
                $options[] = new PlaceOrderItemOptionData(
                    uuid: (string) $optionPayload['uuid'],
                    optionId: (int) $optionPayload['option_id'],
                );
            }

            $items[] = new PlaceOrderItemData(
                uuid: (string) $itemPayload['uuid'],
                productId: (int) $itemPayload['product_id'],
                productVariantId: (int) $itemPayload['product_variant_id'],
                quantity: (int) $itemPayload['quantity'],
                note: $itemPayload['note'] ?? null,
                options: $options,
            );
        }

        $order = $this->placeOrder->handle(new PlaceOrderData(
            uuid: $uuid,
            tableSessionId: $session->id,
            items: $items,
            note: null,
            createdByUserId: $data->receivedByUserId,
        ));

        if ($giaLech) {
            $this->taoConflict(
                op: $op,
                data: $data,
                kind: ConflictKind::GiaLech,
                autoAction: 'dung_gia_server',
                messageVi: "Máy POS số {$data->deviceId} gọi món lúc {$op->occurredAt->format('H:i')} với giá đã thấy lúc offline khác giá hiện tại. Đã ghi theo giá hiện tại — phiếu tạm tính in offline có thể mang giá cũ.",
                options: [
                    ['key' => 'giu_gia_moi', 'label' => 'Giữ giá mới — đúng bảng giá hiện tại'],
                    ['key' => 'giam_gia_bu', 'label' => 'Giảm giá bù đúng phần chênh — giữ đúng phiếu đã đưa khách'],
                ],
                tableSessionId: $session->id,
                isUrgent: false,
            );
        }

        return $this->ketQuaApplied($op, $data, ['order_id' => $order->id]);
    }

    // ── send_to_kitchen ──────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function xuLySendToKitchen(SyncOperationData $op, SyncBatchData $data): array
    {
        $order = Order::query()->where('uuid', (string) $op->payload['order_uuid'])->first();
        if ($order === null) {
            throw new ThaoTacGocKhongTimThayException;
        }

        return $this->xuLyGoiActionDon($op, $data, fn () => $this->sendToKitchen->handle(new SendToKitchenData(orderId: $order->id)));
    }

    // ── cancel_order_item ────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function xuLyCancelOrderItem(SyncOperationData $op, SyncBatchData $data): array
    {
        $item = OrderItem::query()->where('uuid', (string) $op->payload['order_item_uuid'])->first();
        if ($item === null) {
            throw new ThaoTacGocKhongTimThayException;
        }

        return $this->xuLyGoiActionDon($op, $data, fn () => $this->cancelOrderItem->handle(new CancelOrderItemData(
            orderId: $item->order_id,
            orderItemId: $item->id,
            quantity: (int) $op->payload['quantity'],
            reason: (string) $op->payload['reason'],
            cancelledByUserId: $data->receivedByUserId,
            approverUserId: null,
            approverPin: null,
            newItemUuid: $op->payload['new_item_uuid'] ?? null,
            optionUuids: $op->payload['option_uuids'] ?? [],
        )));
    }

    // ── record_payment (dòng 4, 5, 7) ────────────────────────────────────

    /** @return array<string, mixed> */
    private function xuLyRecordPayment(SyncOperationData $op, SyncBatchData $data): array
    {
        $uuid = (string) $op->payload['uuid'];

        $daCo = Payment::query()->where('uuid', $uuid)->first();
        if ($daCo !== null) {
            return $this->ketQuaDuplicate($op, ['payment_id' => $daCo->id]);
        }

        $session = TableSession::query()->with('shift')->where('uuid', (string) $op->payload['table_session_uuid'])->first();
        if ($session === null) {
            throw new ThaoTacGocKhongTimThayException;
        }

        $amount = (int) $op->payload['amount'];
        $remaining = $session->total_amount - $session->paid_amount;

        if ($amount > $remaining) {
            if ($session->paid_amount > 0) {
                $conflict = $this->taoConflict(
                    op: $op,
                    data: $data,
                    kind: ConflictKind::ThuTienTrung,
                    autoAction: 'khong_lam_gi',
                    messageVi: "Máy POS số {$data->deviceId} thu {$this->tien($amount)} lúc {$op->occurredAt->format('H:i')} khi mất mạng, nhưng lượt khách {$session->code} đã được thu tiền ở nơi khác trước đó. Kiểm tra két: có thừa tiền không?",
                    options: [
                        ['key' => 'ket_khong_thua', 'label' => 'Két không thừa — thu trùng, bỏ phiếu này'],
                        ['key' => 'ket_co_thua', 'label' => 'Két có thừa — khách trả hai lần thật, ghi nhận rồi hoàn lại'],
                    ],
                    tableSessionId: $session->id,
                    isUrgent: false,
                );
            } else {
                $conflict = $this->taoConflict(
                    op: $op,
                    data: $data,
                    kind: ConflictKind::ThuVuotGiamGia,
                    autoAction: 'khong_lam_gi',
                    messageVi: "Máy POS số {$data->deviceId} thu {$this->tien($amount)} lúc {$op->occurredAt->format('H:i')} khi mất mạng, nhưng bàn này đã được giảm giá trong lúc đó, tổng còn {$this->tien($session->total_amount)}.",
                    options: [
                        ['key' => 'thu_du_hoan_phan_thua', 'label' => 'Thu đúng tổng mới, hoàn lại phần thừa'],
                        ['key' => 'bo_giam_gia', 'label' => 'Bỏ giảm giá, thu đủ số tiền máy POS đã ghi'],
                    ],
                    tableSessionId: $session->id,
                    isUrgent: false,
                );
            }

            return ['op_uuid' => $op->opUuid, 'status' => OperationStatus::Conflict->value, 'conflict_id' => $conflict->id];
        }

        // RecordPayment tra CA CỦA CHÍNH LƯỢT KHÁCH NÀY (tableSession->shift_id),
        // không tự lấy "ca đang mở bất kỳ" — nên khi ca gốc đã đóng, nó luôn từ
        // chối, bất kể có ca khác đang mở hay không. Việc "gán sang ca đang mở"
        // là quyết định của người (dòng 7 luôn cần người), không phải việc
        // SyncBatch tự làm — không có Action nào đổi shift_id để gọi ở đây.
        try {
            $payment = $this->recordPayment->handle(new RecordPaymentData(
                uuid: $uuid,
                tableSessionId: $session->id,
                method: PaymentMethod::Cash,
                amount: Money::fromInt($amount),
                tenderedAmount: isset($op->payload['tendered_amount']) ? Money::fromInt((int) $op->payload['tendered_amount']) : null,
                reference: null,
                receivedByUserId: $data->receivedByUserId,
            ));
        } catch (DomainException $e) {
            if (str_contains($e->getMessage(), 'Ca của lượt khách này đã đóng')) {
                $conflict = $this->taoConflict(
                    op: $op,
                    data: $data,
                    kind: ConflictKind::CaDaDong,
                    autoAction: 'khong_lam_gi',
                    messageVi: "Máy POS số {$data->deviceId} thu {$this->tien($amount)} lúc {$op->occurredAt->format('H:i')} khi mất mạng, thuộc ca {$session->shift?->code} đã đóng. Tiền chưa được ghi nhận vào két ca nào.",
                    options: [
                        ['key' => 'gan_ca_dang_mo', 'label' => 'Gán tiền này vào ca đang mở hiện tại'],
                        ['key' => 'cho_mo_ca_moi', 'label' => 'Chờ — mở ca rồi xử lý lại phiếu này'],
                    ],
                    tableSessionId: $session->id,
                    isUrgent: false,
                );

                return ['op_uuid' => $op->opUuid, 'status' => OperationStatus::Conflict->value, 'conflict_id' => $conflict->id];
            }

            return ['op_uuid' => $op->opUuid, 'status' => OperationStatus::Rejected->value, 'reason' => $e->getMessage()];
        }

        return $this->ketQuaApplied($op, $data, ['payment_id' => $payment->id]);
    }

    private function tien(int $soTien): string
    {
        return Money::fromInt($soTien)->format();
    }

    // ── dòng 10: thiếu thao tác gốc ─────────────────────────────────────

    /** @return array<string, mixed> */
    private function xuLyThieuThaoTacGoc(SyncOperationData $op, SyncBatchData $data): array
    {
        $khoaDem = "sync_deferred_count:{$op->opUuid}";
        $soLan = Cache::increment($khoaDem);

        if ($soLan < self::SO_LAN_DEFER_TOI_DA) {
            return ['op_uuid' => $op->opUuid, 'status' => OperationStatus::Deferred->value, 'reason' => 'Chưa tìm thấy dữ liệu gốc mà thao tác này cần — thử lại ở gói sau.'];
        }

        Cache::forget($khoaDem);

        $conflict = $this->taoConflict(
            op: $op,
            data: $data,
            kind: ConflictKind::ThieuThaoTacGoc,
            autoAction: 'khong_lam_gi',
            messageVi: "Máy POS số {$data->deviceId} gửi lên một thao tác tham chiếu tới dữ liệu không tìm thấy trong hệ thống, sau {$soLan} lần thử. Có thể dữ liệu trên máy đó bị mất một phần.",
            options: [
                ['key' => 'tao_luot_moi', 'label' => 'Tạo lượt khách/dữ liệu mới cho thao tác này'],
                ['key' => 'bo_qua', 'label' => 'Bỏ qua thao tác này'],
            ],
            tableSessionId: null,
            isUrgent: false,
        );

        return ['op_uuid' => $op->opUuid, 'status' => OperationStatus::Conflict->value, 'conflict_id' => $conflict->id];
    }

    // ── tiện ích chung ───────────────────────────────────────────────────

    /** Gọi một Action không có ma trận xung đột riêng — attach/detach/close/send/cancel. */
    private function xuLyGoiActionDon(SyncOperationData $op, SyncBatchData $data, Closure $goi): array
    {
        try {
            $ketQua = $goi();
        } catch (DomainException $e) {
            return ['op_uuid' => $op->opUuid, 'status' => OperationStatus::Rejected->value, 'reason' => $e->getMessage()];
        }

        return $this->ketQuaApplied($op, $data, ['id' => $ketQua->id]);
    }

    /**
     * Ghi vào sổ cái chống trùng op_uuid NGAY SAU KHI Action chạy thành công
     * — chốt chặn cho việc gửi lại một thao tác ĐÃ áp dụng xong nhưng máy
     * POS không kịp nhận response (mạng rớt đúng lúc trả kết quả). Không ghi
     * ngay trong transaction của Action (mỗi Action giữ transaction riêng
     * theo mục 7.3, SyncBatch không được bọc thêm) — ghi ngay sau, không có
     * việc gì khác chen giữa hai bước này.
     *
     * @return array<string, mixed>
     */
    private function ketQuaApplied(SyncOperationData $op, SyncBatchData $data, array $serverIds): array
    {
        SyncAppliedOp::query()->create([
            'op_uuid' => $op->opUuid,
            'op_type' => $op->type->value,
            'device_id' => $data->deviceId,
            'result_payload' => ['server_ids' => $serverIds],
            'applied_at' => now(),
        ]);

        return ['op_uuid' => $op->opUuid, 'status' => OperationStatus::Applied->value, 'server_ids' => $serverIds];
    }

    /** @return array<string, mixed> */
    private function ketQuaDuplicate(SyncOperationData $op, array $serverIds): array
    {
        return ['op_uuid' => $op->opUuid, 'status' => OperationStatus::Duplicate->value, 'server_ids' => $serverIds];
    }

    private function timIdLuotKhach(string $uuid): int
    {
        $session = TableSession::query()->where('uuid', $uuid)->first();
        if ($session === null) {
            throw new ThaoTacGocKhongTimThayException;
        }

        return $session->id;
    }

    /** Gom một thao tác con vào cụm của thao tác gốc đã bị conflict (mục 5.0). */
    private function gomVaoCum(int $conflictId, SyncOperationData $op): void
    {
        $conflict = SyncConflict::query()->find($conflictId);
        if ($conflict === null) {
            return;
        }

        $payload = $conflict->payload;
        $payload['cum'][] = $this->opThanhMang($op);
        $conflict->update(['payload' => $payload]);
    }

    /** @return array<string, mixed> */
    private function opThanhMang(SyncOperationData $op): array
    {
        return [
            'op_uuid' => $op->opUuid,
            'type' => $op->type->value,
            'occurred_at' => $op->occurredAt->toIso8601String(),
            'depends_on' => $op->dependsOn,
            'payload' => $op->payload,
        ];
    }

    /** @param list<array<string, mixed>> $options */
    private function taoConflict(
        SyncOperationData $op,
        SyncBatchData $data,
        ConflictKind $kind,
        ?string $autoAction,
        string $messageVi,
        array $options,
        ?int $tableSessionId,
        bool $isUrgent,
    ): SyncConflict {
        // Gửi lại đúng thao tác đã ghi xung đột (mạng rớt lúc trả kết quả,
        // hoặc chính op này là con trong một cụm đã xử lý ở gói trước) —
        // trả về đúng bản ghi cũ, không tạo bản ghi chờ thứ hai
        // (uq_sync_conflicts_op sẽ chặn INSERT trùng nếu không kiểm trước).
        $daCo = SyncConflict::query()->where('op_uuid', $op->opUuid)->first();
        if ($daCo !== null) {
            return $daCo;
        }

        return SyncConflict::query()->create([
            'op_uuid' => $op->opUuid,
            'batch_uuid' => $data->batchUuid,
            'device_id' => $data->deviceId,
            'op_type' => $op->type->value,
            'conflict_kind' => $kind->value,
            'is_urgent' => $isUrgent,
            'occurred_at' => $op->occurredAt,
            'received_at' => now(),
            'payload' => ['goc' => $this->opThanhMang($op), 'cum' => []],
            'server_state' => $this->trangThaiServerHienTai($tableSessionId),
            'auto_action' => $autoAction,
            'message_vi' => $messageVi,
            'options' => $options,
            'table_session_id' => $tableSessionId,
            'status' => ConflictStatus::Pending,
        ]);
    }

    /** @return array<string, mixed> */
    private function trangThaiServerHienTai(?int $tableSessionId): array
    {
        if ($tableSessionId === null) {
            return [];
        }

        $session = TableSession::query()->find($tableSessionId);
        if ($session === null) {
            return [];
        }

        return [
            'status' => $session->status->value,
            'total_amount' => $session->total_amount,
            'paid_amount' => $session->paid_amount,
        ];
    }
}
```

### 2.2. `app/Domain/Sync/Actions/ResolveSyncConflict.php`

```php
<?php

declare(strict_types=1);

namespace App\Domain\Sync\Actions;

use App\Domain\Billing\Actions\CalculateBill;
use App\Domain\Billing\Actions\RecordPayment;
use App\Domain\Billing\DTO\CalculateBillData;
use App\Domain\Billing\DTO\RecordPaymentData;
use App\Domain\Billing\Enums\PaymentMethod;
use App\Domain\Ordering\Actions\AttachTable;
use App\Domain\Ordering\Actions\CancelOrderItem;
use App\Domain\Ordering\Actions\CloseTableSession;
use App\Domain\Ordering\Actions\DetachTable;
use App\Domain\Ordering\Actions\OpenTableSession;
use App\Domain\Ordering\Actions\PlaceOrder;
use App\Domain\Ordering\Actions\SendToKitchen;
use App\Domain\Ordering\DTO\AttachTableData;
use App\Domain\Ordering\DTO\CancelOrderItemData;
use App\Domain\Ordering\DTO\CloseTableSessionData;
use App\Domain\Ordering\DTO\DetachTableData;
use App\Domain\Ordering\DTO\OpenTableSessionData;
use App\Domain\Ordering\DTO\PlaceOrderData;
use App\Domain\Ordering\DTO\PlaceOrderItemData;
use App\Domain\Ordering\DTO\PlaceOrderItemOptionData;
use App\Domain\Ordering\DTO\SendToKitchenData;
use App\Domain\Ordering\Models\Order;
use App\Domain\Ordering\Models\OrderItem;
use App\Domain\Ordering\Models\TableSession;
use App\Domain\Ordering\Models\TableSessionTable;
use App\Domain\Staffing\Actions\RecordCashMovement;
use App\Domain\Staffing\Actions\VerifyApproverPin;
use App\Domain\Staffing\DTO\PinVerifyData;
use App\Domain\Staffing\DTO\RecordCashMovementData;
use App\Domain\Staffing\Enums\CashDirection;
use App\Domain\Staffing\Enums\ShiftStatus;
use App\Domain\Staffing\Models\Shift;
use App\Domain\Sync\DTO\ResolveSyncConflictData;
use App\Domain\Sync\Enums\ConflictKind;
use App\Domain\Sync\Enums\ConflictStatus;
use App\Domain\Sync\Models\SyncConflict;
use App\Exceptions\DomainException;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Người (chủ quán/thu ngân) quyết một xung đột đồng bộ đang chờ ở màn hình
 * Bước 5 — theo đúng ma trận và câu chữ ở docs/thiet-ke-dong-bo.md mục 5.
 *
 * KHÔNG viết logic nghiệp vụ tiền/bàn/món ở đây — mọi hiệu ứng dữ liệu đi qua
 * đúng Action Phase 1 đã có, giống nguyên tắc của SyncBatch (Bước 4). Action
 * này chỉ:
 *  1. Khoá đúng một bản ghi `sync_conflicts` (chống bấm xử lý hai lần).
 *  2. Với mỗi (loại xung đột, phương án chọn), gọi ĐÚNG Action Phase 1 tương
 *     ứng — có phương án không làm gì cả (VD "Két không thừa", "Huỷ món") vì
 *     bản thân "không tạo dữ liệu gì" chính là hiệu ứng đúng.
 *  3. Chốt lại bản ghi xung đột: ai quyết, lúc nào, lý do gì.
 *
 * Khác với SyncBatch (mỗi thao tác một giao dịch riêng vì gói có thể tới 200
 * việc — hỏng một cái không được mất luôn phần còn lại), ở đây NGƯỜI đang xử
 * lý ĐÚNG MỘT xung đột nên bọc toàn bộ trong một `DB::transaction` cha: các
 * Action Phase 1 gọi bên trong dùng `DB::transaction` riêng của chúng, Laravel
 * tự biến thành SAVEPOINT lồng nhau — hỏng ở bất kỳ bước nào thì rollback hết,
 * xung đột vẫn giữ nguyên "pending" để thử lại, không bao giờ chốt nửa vời.
 */
final class ResolveSyncConflict
{
    /**
     * Xung đột dính tiền — bắt buộc người duyệt bằng mã PIN trước khi ghi
     * quyết định, kể cả khi chọn "Bỏ qua" (đây cũng là một quyết định về
     * tiền, không phải việc không ai chịu trách nhiệm).
     *
     * @var list<ConflictKind>
     */
    private const CAN_PIN_DUYET = [ConflictKind::ThuTienTrung, ConflictKind::ThuVuotGiamGia];

    public function __construct(
        private readonly OpenTableSession $openTableSession,
        private readonly AttachTable $attachTable,
        private readonly DetachTable $detachTable,
        private readonly PlaceOrder $placeOrder,
        private readonly SendToKitchen $sendToKitchen,
        private readonly CancelOrderItem $cancelOrderItem,
        private readonly RecordPayment $recordPayment,
        private readonly CloseTableSession $closeTableSession,
        private readonly CalculateBill $calculateBill,
        private readonly RecordCashMovement $recordCashMovement,
        private readonly VerifyApproverPin $verifyApproverPin,
    ) {}

    public function handle(ResolveSyncConflictData $data): SyncConflict
    {
        return DB::transaction(function () use ($data): SyncConflict {
            $conflict = SyncConflict::query()->lockForUpdate()->findOrFail($data->conflictId);

            if ($conflict->status !== ConflictStatus::Pending) {
                throw new DomainException('Xung đột này đã được xử lý rồi, không xử lý lại được nữa.');
            }

            $lyDo = trim($data->note);
            if ($lyDo === '') {
                throw new DomainException('Phải ghi rõ lý do quyết định.');
            }

            if (in_array(ConflictKind::from($conflict->conflict_kind), self::CAN_PIN_DUYET, true)) {
                if ($data->approverUserId === null || $data->approverPin === null) {
                    throw new DomainException('Xung đột liên quan tới tiền phải có người duyệt bằng mã PIN.');
                }

                $this->verifyApproverPin->handle(new PinVerifyData(
                    userId: $data->approverUserId,
                    pin: $data->approverPin,
                    requestedByUserId: $data->resolvedByUserId,
                ));
            }

            if ($data->dismiss) {
                $conflict->update([
                    'status' => ConflictStatus::Dismissed,
                    'resolution' => 'bo_qua',
                    'resolution_note' => $lyDo,
                    'resolved_by_user_id' => $data->resolvedByUserId,
                    'resolved_at' => now(),
                ]);

                return $conflict->refresh();
            }

            $phuongAn = $data->resolution !== null ? trim($data->resolution) : '';
            if ($phuongAn === '') {
                throw new DomainException('Phải chọn một phương án xử lý.');
            }

            $this->apDungHieuUng($conflict, $phuongAn, $data);

            $conflict->update([
                'status' => ConflictStatus::Resolved,
                'resolution' => $phuongAn,
                'resolution_note' => $lyDo,
                'resolved_by_user_id' => $data->resolvedByUserId,
                'resolved_at' => now(),
            ]);

            return $conflict->refresh();
        });
    }

    private function apDungHieuUng(SyncConflict $conflict, string $phuongAn, ResolveSyncConflictData $data): void
    {
        $kind = ConflictKind::from($conflict->conflict_kind);
        $goc = $conflict->payload['goc'] ?? null;
        $cum = $conflict->payload['cum'] ?? [];

        if ($goc === null) {
            throw new DomainException('Bản ghi xung đột thiếu dữ liệu thao tác gốc, không áp dụng lại được.');
        }

        match ($kind) {
            ConflictKind::HaiMayMoBan => $this->xuLyHaiMayMoBan($conflict, $goc, $cum, $phuongAn, $data),
            ConflictKind::ThuTienTrung => $this->xuLyThuTienTrung($conflict, $goc, $phuongAn, $data),
            ConflictKind::ThuVuotGiamGia => $this->xuLyThuVuotGiamGia($conflict, $goc, $phuongAn, $data),
            ConflictKind::LuotDaDong, ConflictKind::LuotDaHuy => $this->xuLyLuotDaDongHoacHuy($conflict, $goc, $cum, $phuongAn, $data),
            ConflictKind::CaDaDong => $this->xuLyCaDaDong($conflict, $goc, $phuongAn, $data),
            ConflictKind::GiaLech => $this->xuLyGiaLech($conflict, $goc, $phuongAn, $data),
            ConflictKind::ThieuThaoTacGoc => $this->xuLyThieuThaoTacGoc($conflict, $goc, $cum, $phuongAn, $data),
            // Dòng 1 (bếp báo hết món) chưa có cách sinh ra xung đột này trong hệ
            // thống hiện tại (chưa có tính năng "bếp báo hết món") — xem
            // docs/viec-ton.md. Không có phương án nào để áp dụng, chỉ có thể ghi
            // lại quyết định (đã làm ở handle() qua trường resolution/note).
            ConflictKind::MonDaHet => null,
        };
    }

    // ── dòng 2: hai máy cùng mở bàn ──────────────────────────────────────

    /**
     * @param  array<string, mixed>  $goc
     * @param  list<array<string, mixed>>  $cum
     */
    private function xuLyHaiMayMoBan(SyncConflict $conflict, array $goc, array $cum, string $phuongAn, ResolveSyncConflictData $data): void
    {
        $payload = $goc['payload'];

        if ($phuongAn === 'gop') {
            $idBan = array_map('intval', $payload['dining_table_ids']);
            $ganLuot = TableSessionTable::query()
                ->whereIn('dining_table_id', $idBan)
                ->whereNull('detached_at')
                ->first();

            if ($ganLuot === null) {
                throw new DomainException('Không tìm thấy lượt khách nào đang giữ bàn này để gộp vào — có thể bàn đã trống, xem lại tình huống trước khi chọn "Gộp".');
            }

            $this->apDungCum($cum, $ganLuot->table_session_id, $data->resolvedByUserId);

            return;
        }

        if ($phuongAn === 'tach') {
            if ($data->diningTableIds === []) {
                throw new DomainException('Phải chọn ít nhất một bàn khác cho lượt khách này khi chọn "Tách".');
            }

            $luotMoi = $this->openTableSession->handle(new OpenTableSessionData(
                uuid: (string) $payload['uuid'],
                diningTableIds: $data->diningTableIds,
                primaryDiningTableId: $data->diningTableIds[0],
                guestCount: (int) $payload['guest_count'],
                openedByUserId: $data->resolvedByUserId,
            ));

            $this->apDungCumTheoUuid($cum, $data->resolvedByUserId);

            $conflict->table_session_id = $luotMoi->id;

            return;
        }

        throw new DomainException("Phương án \"{$phuongAn}\" không áp dụng được cho loại xung đột này.");
    }

    // ── dòng 4: hai máy cùng thu tiền ────────────────────────────────────

    /** @param array<string, mixed> $goc */
    private function xuLyThuTienTrung(SyncConflict $conflict, array $goc, string $phuongAn, ResolveSyncConflictData $data): void
    {
        if ($phuongAn === 'ket_khong_thua') {
            return; // Thu trùng, bỏ phiếu này — không tạo dữ liệu gì, đây chính là hiệu ứng đúng.
        }

        if ($phuongAn === 'ket_co_thua') {
            $payload = $goc['payload'];
            $soTien = Money::fromInt((int) $payload['amount']);

            $ca = Shift::query()->where('status', ShiftStatus::Open)->lockForUpdate()->first();
            if ($ca === null) {
                throw new DomainException('Chưa có ca nào đang mở, không ghi được khoản thu chi vặt cho phần tiền thừa. Mở ca rồi xử lý lại.');
            }

            $tenBan = $this->tenBanCuaLuot($conflict->table_session_id);

            $this->recordCashMovement->handle(new RecordCashMovementData(
                shiftId: $ca->id,
                direction: CashDirection::In,
                amount: $soTien,
                reason: "Thu trùng bàn {$tenBan} - xung đột #{$conflict->id} - khách trả hai lần thật",
                createdByUserId: $data->resolvedByUserId,
            ));

            $this->recordCashMovement->handle(new RecordCashMovementData(
                shiftId: $ca->id,
                direction: CashDirection::Out,
                amount: $soTien,
                reason: "Hoàn lại bàn {$tenBan} - xung đột #{$conflict->id}",
                createdByUserId: $data->resolvedByUserId,
            ));

            return;
        }

        throw new DomainException("Phương án \"{$phuongAn}\" không áp dụng được cho loại xung đột này.");
    }

    // ── dòng 5: thu offline, giảm giá online ─────────────────────────────

    /** @param array<string, mixed> $goc */
    private function xuLyThuVuotGiamGia(SyncConflict $conflict, array $goc, string $phuongAn, ResolveSyncConflictData $data): void
    {
        $payload = $goc['payload'];
        $tableSessionId = (int) $conflict->table_session_id;
        $tendered = isset($payload['tendered_amount']) ? Money::fromInt((int) $payload['tendered_amount']) : null;

        if ($phuongAn === 'thu_du_hoan_phan_thua') {
            $session = TableSession::query()->lockForUpdate()->findOrFail($tableSessionId);
            $conThieu = Money::fromInt($session->total_amount)->minus(Money::fromInt($session->paid_amount));

            $this->recordPayment->handle(new RecordPaymentData(
                uuid: (string) $payload['uuid'],
                tableSessionId: $tableSessionId,
                method: PaymentMethod::Cash,
                amount: $conThieu,
                tenderedAmount: $tendered ?? $conThieu,
                reference: null,
                receivedByUserId: $data->resolvedByUserId,
            ));

            return;
        }

        if ($phuongAn === 'bo_giam_gia') {
            $this->calculateBill->handle(new CalculateBillData(
                tableSessionId: $tableSessionId,
                discountAmount: Money::zero(),
                discountReason: null,
                requestedByUserId: $data->resolvedByUserId,
                approverUserId: null,
                approverPin: null,
            ));

            $this->recordPayment->handle(new RecordPaymentData(
                uuid: (string) $payload['uuid'],
                tableSessionId: $tableSessionId,
                method: PaymentMethod::Cash,
                amount: Money::fromInt((int) $payload['amount']),
                tenderedAmount: $tendered,
                reference: null,
                receivedByUserId: $data->resolvedByUserId,
            ));

            return;
        }

        throw new DomainException("Phương án \"{$phuongAn}\" không áp dụng được cho loại xung đột này.");
    }

    // ── dòng 6/9: gọi món vào lượt đã đóng/đã huỷ ────────────────────────

    /**
     * @param  array<string, mixed>  $goc
     * @param  list<array<string, mixed>>  $cum
     */
    private function xuLyLuotDaDongHoacHuy(SyncConflict $conflict, array $goc, array $cum, string $phuongAn, ResolveSyncConflictData $data): void
    {
        if ($phuongAn === 'huy_mon') {
            return; // Gọi nhầm — không tạo phiếu gì, đúng như đã chọn.
        }

        if ($phuongAn === 'mo_luot_moi') {
            $idBan = $data->diningTableIds !== []
                ? $data->diningTableIds
                : $this->banCuaLuotCu((int) $conflict->table_session_id);

            if ($idBan === []) {
                throw new DomainException('Không xác định được bàn cho lượt khách mới — phải tự chọn bàn.');
            }

            $luotMoi = $this->openTableSession->handle(new OpenTableSessionData(
                uuid: (string) Str::uuid(),
                diningTableIds: $idBan,
                primaryDiningTableId: $idBan[0],
                guestCount: 1,
                openedByUserId: $data->resolvedByUserId,
            ));

            $payload = $goc['payload'];
            $this->placeOrder->handle(new PlaceOrderData(
                uuid: (string) $payload['uuid'],
                tableSessionId: $luotMoi->id,
                items: $this->xayItems($payload['items']),
                note: null,
                createdByUserId: $data->resolvedByUserId,
            ));

            $this->apDungCumTheoUuid($cum, $data->resolvedByUserId);

            $conflict->table_session_id = $luotMoi->id;

            return;
        }

        throw new DomainException("Phương án \"{$phuongAn}\" không áp dụng được cho loại xung đột này.");
    }

    /**
     * Tên bàn để ghi vào lý do thu chi vặt — tra ngược được ba tháng sau
     * (yêu cầu 04/08). Dòng 4 (thu trùng) luôn xảy ra trên một lượt khách ĐÃ
     * thu đủ tiền, tức tại lúc xử lý bàn gần như chắc chắn đã được nhả
     * (detached_at khác NULL) — khác với CloseShift (chỉ cần bàn ĐANG chiếm để
     * chỉ đường cho nhân viên), ở đây cần TÊN BÀN ĐÃ TỪNG PHỤC VỤ lượt khách
     * này, không lọc theo detached_at. Không có bàn nào (lượt khách không
     * chiếm bàn) thì dùng mã lượt khách thay.
     */
    private function tenBanCuaLuot(?int $tableSessionId): string
    {
        if ($tableSessionId === null) {
            return 'không rõ';
        }

        $session = TableSession::query()->with('tables.diningTable')->find($tableSessionId);
        if ($session === null) {
            return 'không rõ';
        }

        $tenBan = $session->tables
            ->map(fn (TableSessionTable $bi) => $bi->diningTable->code)
            ->unique()
            ->implode(', ');

        return $tenBan !== '' ? $tenBan : "lượt khách {$session->code}";
    }

    /** @return list<int> */
    private function banCuaLuotCu(?int $tableSessionId): array
    {
        if ($tableSessionId === null) {
            return [];
        }

        return TableSessionTable::query()
            ->where('table_session_id', $tableSessionId)
            ->pluck('dining_table_id')
            ->unique()
            ->values()
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    // ── dòng 7: phiếu thu thuộc ca đã đóng ───────────────────────────────

    /** @param array<string, mixed> $goc */
    private function xuLyCaDaDong(SyncConflict $conflict, array $goc, string $phuongAn, ResolveSyncConflictData $data): void
    {
        if ($phuongAn === 'cho_mo_ca_moi') {
            return; // Quyết định chờ — chưa mở ca mới, không ghi phiếu thu nào cả.
        }

        if ($phuongAn === 'gan_ca_dang_mo') {
            $caDangMo = Shift::query()->where('status', ShiftStatus::Open)->lockForUpdate()->first();
            if ($caDangMo === null) {
                throw new DomainException('Chưa có ca nào đang mở để gán tiền này vào — chọn "Chờ mở ca mới" thay.');
            }

            $tableSessionId = (int) $conflict->table_session_id;
            $session = TableSession::query()->lockForUpdate()->findOrFail($tableSessionId);
            $session->update(['shift_id' => $caDangMo->id]);

            $payload = $goc['payload'];
            $this->recordPayment->handle(new RecordPaymentData(
                uuid: (string) $payload['uuid'],
                tableSessionId: $tableSessionId,
                method: PaymentMethod::Cash,
                amount: Money::fromInt((int) $payload['amount']),
                tenderedAmount: isset($payload['tendered_amount']) ? Money::fromInt((int) $payload['tendered_amount']) : null,
                reference: null,
                receivedByUserId: $data->resolvedByUserId,
            ));

            return;
        }

        throw new DomainException("Phương án \"{$phuongAn}\" không áp dụng được cho loại xung đột này.");
    }

    // ── dòng 8: giá món đổi ──────────────────────────────────────────────

    /** @param array<string, mixed> $goc */
    private function xuLyGiaLech(SyncConflict $conflict, array $goc, string $phuongAn, ResolveSyncConflictData $data): void
    {
        if ($phuongAn === 'giu_gia_moi') {
            return; // Đã ghi theo giá server ngay lúc gọi món (SyncBatch) — không làm gì thêm.
        }

        if ($phuongAn === 'giam_gia_bu') {
            $payload = $goc['payload'];
            $order = Order::query()->with('items')->where('uuid', (string) $payload['uuid'])->first();
            if ($order === null) {
                throw new DomainException('Không tìm thấy phiếu gọi món gốc để tính phần chênh lệch.');
            }

            $chenhLech = 0;
            foreach ($payload['items'] as $itemPayload) {
                if (! isset($itemPayload['client_unit_price'])) {
                    continue;
                }

                $dongMon = $order->items->firstWhere('uuid', $itemPayload['uuid']);
                if ($dongMon === null) {
                    continue;
                }

                // Server luôn ghi giá CỦA MÌNH (dongMon->unit_price). Khách chỉ cần bù
                // phần chênh khi giá server CAO HƠN giá khách đã thấy lúc offline
                // (client_unit_price) — phiếu tạm tính in offline mang giá thấp hơn.
                // Giá server THẤP hơn thì khách không thiệt, không có gì để bù.
                $chenh = ($dongMon->unit_price - (int) $itemPayload['client_unit_price']) * $dongMon->quantity;
                if ($chenh > 0) {
                    $chenhLech += $chenh;
                }
            }

            $this->calculateBill->handle(new CalculateBillData(
                tableSessionId: (int) $conflict->table_session_id,
                discountAmount: Money::fromInt($chenhLech),
                discountReason: $chenhLech > 0
                    ? "Giảm bù chênh lệch giá — xung đột #{$conflict->id}, phiếu tạm tính offline mang giá cũ."
                    : null,
                requestedByUserId: $data->resolvedByUserId,
                approverUserId: null,
                approverPin: null,
            ));

            return;
        }

        throw new DomainException("Phương án \"{$phuongAn}\" không áp dụng được cho loại xung đột này.");
    }

    // ── dòng 10: thiếu thao tác gốc ──────────────────────────────────────

    /**
     * @param  array<string, mixed>  $goc
     * @param  list<array<string, mixed>>  $cum
     */
    private function xuLyThieuThaoTacGoc(SyncConflict $conflict, array $goc, array $cum, string $phuongAn, ResolveSyncConflictData $data): void
    {
        if ($phuongAn === 'bo_qua') {
            return;
        }

        if ($phuongAn === 'tao_luot_moi') {
            if ($goc['type'] !== 'place_order') {
                throw new DomainException('Phương án "Tạo lượt khách mới" chỉ áp dụng được khi thao tác gốc bị thiếu là gọi món.');
            }

            if ($data->diningTableIds === []) {
                throw new DomainException('Phải chọn bàn cho lượt khách mới.');
            }

            $luotMoi = $this->openTableSession->handle(new OpenTableSessionData(
                uuid: (string) Str::uuid(),
                diningTableIds: $data->diningTableIds,
                primaryDiningTableId: $data->diningTableIds[0],
                guestCount: 1,
                openedByUserId: $data->resolvedByUserId,
            ));

            $payload = $goc['payload'];
            $this->placeOrder->handle(new PlaceOrderData(
                uuid: (string) $payload['uuid'],
                tableSessionId: $luotMoi->id,
                items: $this->xayItems($payload['items']),
                note: null,
                createdByUserId: $data->resolvedByUserId,
            ));

            $this->apDungCumTheoUuid($cum, $data->resolvedByUserId);

            $conflict->table_session_id = $luotMoi->id;

            return;
        }

        throw new DomainException("Phương án \"{$phuongAn}\" không áp dụng được cho loại xung đột này.");
    }

    // ── tiện ích chung: áp dụng lại cụm thao tác đã lưu ──────────────────

    /**
     * Áp dụng lại một cụm thao tác con — ép thẳng vào MỘT lượt khách đã biết
     * trước ($tableSessionId), bỏ qua table_session_uuid trong payload (dùng
     * cho phương án "Gộp": lượt khách thắng đã tồn tại từ trước, không mang
     * uuid mà cụm này khai).
     *
     * @param  list<array<string, mixed>>  $cum
     */
    private function apDungCum(array $cum, int $tableSessionId, int $nguoiThucHien): void
    {
        foreach ($cum as $op) {
            $this->apDungMotOpDonGian($op, $tableSessionId, $nguoiThucHien);
        }
    }

    /**
     * Áp dụng lại một cụm thao tác con — tra `table_session_uuid`/`order_uuid`/
     * `order_item_uuid` trong payload để tìm đúng bản ghi (dùng khi lượt khách
     * hoặc phiếu gọi món GỐC đã được tạo lại với ĐÚNG uuid cũ ngay trước đó).
     *
     * @param  list<array<string, mixed>>  $cum
     */
    private function apDungCumTheoUuid(array $cum, int $nguoiThucHien): void
    {
        foreach ($cum as $op) {
            $this->apDungMotOpDonGian($op, null, $nguoiThucHien);
        }
    }

    /** @param array<string, mixed> $op */
    private function apDungMotOpDonGian(array $op, ?int $ganLuotId, int $nguoiThucHien): void
    {
        $payload = $op['payload'];

        match ($op['type']) {
            'place_order' => $this->placeOrder->handle(new PlaceOrderData(
                uuid: (string) $payload['uuid'],
                tableSessionId: $ganLuotId ?? $this->timIdLuotTheoUuid((string) $payload['table_session_uuid']),
                items: $this->xayItems($payload['items']),
                note: null,
                createdByUserId: $nguoiThucHien,
            )),
            'send_to_kitchen' => $this->sendToKitchen->handle(new SendToKitchenData(
                orderId: $this->timIdDonTheoUuid((string) $payload['order_uuid']),
            )),
            'cancel_order_item' => $this->apDungCancelOrderItem($payload, $nguoiThucHien),
            'attach_table' => $this->attachTable->handle(new AttachTableData(
                tableSessionId: $ganLuotId ?? $this->timIdLuotTheoUuid((string) $payload['table_session_uuid']),
                diningTableId: (int) $payload['dining_table_id'],
                attachedByUserId: $nguoiThucHien,
            )),
            'detach_table' => $this->detachTable->handle(new DetachTableData(
                tableSessionId: $ganLuotId ?? $this->timIdLuotTheoUuid((string) $payload['table_session_uuid']),
                diningTableId: (int) $payload['dining_table_id'],
            )),
            'record_payment' => $this->recordPayment->handle(new RecordPaymentData(
                uuid: (string) $payload['uuid'],
                tableSessionId: $ganLuotId ?? $this->timIdLuotTheoUuid((string) $payload['table_session_uuid']),
                method: PaymentMethod::Cash,
                amount: Money::fromInt((int) $payload['amount']),
                tenderedAmount: isset($payload['tendered_amount']) ? Money::fromInt((int) $payload['tendered_amount']) : null,
                reference: null,
                receivedByUserId: $nguoiThucHien,
            )),
            'close_session' => $this->closeTableSession->handle(new CloseTableSessionData(
                tableSessionId: $ganLuotId ?? $this->timIdLuotTheoUuid((string) $payload['table_session_uuid']),
                closedByUserId: $nguoiThucHien,
            )),
            default => throw new DomainException("Không biết áp dụng lại thao tác loại \"{$op['type']}\"."),
        };
    }

    /**
     * @param  list<array<string, mixed>>  $itemsPayload
     * @return list<PlaceOrderItemData>
     */
    private function xayItems(array $itemsPayload): array
    {
        return array_map(function (array $itemPayload): PlaceOrderItemData {
            $options = array_map(
                fn (array $optionPayload): PlaceOrderItemOptionData => new PlaceOrderItemOptionData(
                    uuid: (string) $optionPayload['uuid'],
                    optionId: (int) $optionPayload['option_id'],
                ),
                $itemPayload['options'] ?? []
            );

            return new PlaceOrderItemData(
                uuid: (string) $itemPayload['uuid'],
                productId: (int) $itemPayload['product_id'],
                productVariantId: (int) $itemPayload['product_variant_id'],
                quantity: (int) $itemPayload['quantity'],
                note: $itemPayload['note'] ?? null,
                options: $options,
            );
        }, $itemsPayload);
    }

    private function timIdLuotTheoUuid(string $uuid): int
    {
        return TableSession::query()->where('uuid', $uuid)->firstOrFail()->id;
    }

    private function timIdDonTheoUuid(string $uuid): int
    {
        return Order::query()->where('uuid', $uuid)->firstOrFail()->id;
    }

    /** @param array<string, mixed> $payload */
    private function apDungCancelOrderItem(array $payload, int $nguoiThucHien): void
    {
        $dongMon = OrderItem::query()->where('uuid', (string) $payload['order_item_uuid'])->firstOrFail();

        $this->cancelOrderItem->handle(new CancelOrderItemData(
            orderId: $dongMon->order_id,
            orderItemId: $dongMon->id,
            quantity: (int) $payload['quantity'],
            reason: (string) $payload['reason'],
            cancelledByUserId: $nguoiThucHien,
            approverUserId: null,
            approverPin: null,
            newItemUuid: $payload['new_item_uuid'] ?? null,
            optionUuids: $payload['option_uuids'] ?? [],
        ));
    }
}
```

### 2.3. Lớp phụ trợ — Enums

**`app/Domain/Sync/Enums/OperationType.php`** (8 loại — khớp mục 1 thiết kế):
```php
enum OperationType: string
{
    case OpenSession = 'open_session';
    case AttachTable = 'attach_table';
    case DetachTable = 'detach_table';
    case PlaceOrder = 'place_order';
    case SendToKitchen = 'send_to_kitchen';
    case CancelOrderItem = 'cancel_order_item';
    case RecordPayment = 'record_payment';
    case CloseSession = 'close_session';
}
```

**`app/Domain/Sync/Enums/OperationStatus.php`** (5 trạng thái — khớp mục 3.3):
```php
enum OperationStatus: string
{
    case Applied = 'applied';
    case Duplicate = 'duplicate';
    case Conflict = 'conflict';
    case Deferred = 'deferred';
    case Rejected = 'rejected';
}
```

**`app/Domain/Sync/Enums/ConflictKind.php`** (9 loại — dòng 3 không tạo conflict nên không có kind):
```php
enum ConflictKind: string
{
    case MonDaHet = 'mon_da_het'; // dòng 1 — hoãn, xem docs/viec-ton.md
    case HaiMayMoBan = 'hai_may_mo_ban'; // dòng 2
    case ThuTienTrung = 'thu_tien_trung'; // dòng 4
    case ThuVuotGiamGia = 'thu_vuot_giam_gia'; // dòng 5
    case LuotDaDong = 'luot_da_dong'; // dòng 6
    case CaDaDong = 'ca_da_dong'; // dòng 7
    case GiaLech = 'gia_lech'; // dòng 8
    case LuotDaHuy = 'luot_da_huy'; // dòng 9
    case ThieuThaoTacGoc = 'thieu_thao_tac_goc'; // dòng 10
}
```

**`app/Domain/Sync/Enums/ConflictStatus.php`**:
```php
enum ConflictStatus: string
{
    case Pending = 'pending';
    case Resolved = 'resolved';
    case Dismissed = 'dismissed';
}
```

### 2.4. Model

**`app/Domain/Sync/Models/SyncAppliedOp.php`** — khoá chính là `op_uuid` (string, không tự tăng), không có timestamps (chỉ có `applied_at` riêng):
```php
final class SyncAppliedOp extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $primaryKey = 'op_uuid';
    public $timestamps = false;
    protected $fillable = ['op_uuid', 'op_type', 'device_id', 'result_payload', 'applied_at'];
    protected function casts(): array
    {
        return ['result_payload' => 'array', 'applied_at' => 'datetime'];
    }
}
```

**`app/Domain/Sync/Models/SyncConflict.php`** — đầy đủ như thiết kế mục 6, quan hệ `tableSession()`, `resolvedBy()`.

### 2.5. DTO

`SyncBatchData`, `SyncOperationData`, `ResolveSyncConflictData` — đã đọc toàn văn, khớp đúng hợp đồng mục 3.1 của thiết kế (`op_uuid`, `type`, `occurred_at`, `depends_on`, `payload`, `viTriGoc` để phá thế cân bằng khi trùng `occurred_at`).

### 2.6. Controller + FormRequest

**`app/Http/Controllers/Api/SyncBatchController.php`** — `POST /api/v1/sync/batch`, gom `summary` 5 trạng thái, trả `results` nguyên văn từ Action.

**`app/Http/Controllers/Api/SyncConflictController.php`** — ba endpoint: `index` (mọi vai trò xem được), `resolve` (chặn quyền ở `ResolveSyncConflictRequest`), `pendingCount` (chấm đỏ, không lộ nội dung).

**`app/Http/Requests/SyncBatchRequest.php`** — `authorize()` chỉ cần đăng nhập (`$this->user() !== null`), validate `operations.*.op_uuid` là `uuid` + `distinct`, `operations` tối đa 200 (khớp `count($data->operations) > 200` phía Action — kiểm hai lớp).

**`app/Http/Requests/ResolveSyncConflictRequest.php`** — chỉ `Owner`/`Cashier` được quyết (`authorize()`), bắt buộc `note`, `resolution` bắt buộc khi `dismiss=false`.

### 2.7. Exception riêng

`SyncBatchLockedException` (429 khi không giành được khoá `sync:batch` trong 5 giây) và `ThaoTacGocKhongTimThayException` (nội bộ, không lộ ra ngoài `SyncBatch`, bắt bằng try/catch trong `apDungMotThaoTac()`).

---

## 3. NỘI DUNG ĐẦY ĐỦ — CÁC ACTION KHÁC CỦA PHASE 2

### 3.1. `app/Domain/Ordering/Actions/SplitTableSession.php` (Bước 1)

```php
<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Actions;

use App\Domain\Ordering\DTO\MoveOrderItemData;
use App\Domain\Ordering\DTO\SplitTableSessionData;
use App\Domain\Ordering\Enums\TableSessionStatus;
use App\Domain\Ordering\Models\DiningTable;
use App\Domain\Ordering\Models\TableSession;
use App\Domain\Ordering\Models\TableSessionTable;
use App\Domain\Staffing\Enums\ShiftStatus;
use App\Domain\Staffing\Models\Shift;
use App\Exceptions\DomainException;
use Illuminate\Support\Facades\DB;

final class SplitTableSession
{
    public function __construct(
        private readonly MoveOrderItem $moveOrderItem,
    ) {}

    /** @return array{source: TableSession, new: TableSession} */
    public function handle(SplitTableSessionData $data): array
    {
        if ($data->orderItemIds === []) {
            throw new DomainException('Phải chọn ít nhất một dòng món để tách.');
        }

        if ($data->diningTableIds === []) {
            throw new DomainException('Phải gán ít nhất một bàn cho lượt khách mới.');
        }

        return DB::transaction(function () use ($data): array {
            $luotMoiDaCo = TableSession::query()->where('uuid', $data->uuid)->first();
            if ($luotMoiDaCo !== null) {
                return [
                    'source' => TableSession::query()->findOrFail($data->sourceTableSessionId),
                    'new' => $luotMoiDaCo,
                ];
            }

            // Luật CLAUDE.md mục 11: Shift → TableSession → (Bàn/Món).
            $shift = Shift::query()->where('status', ShiftStatus::Open)->lockForUpdate()->first();

            if ($shift === null) {
                throw new DomainException('Chưa mở ca. Phải mở ca trước khi tách bàn.');
            }

            $luotGoc = TableSession::query()->lockForUpdate()->findOrFail($data->sourceTableSessionId);

            if ($luotGoc->status !== TableSessionStatus::Open) {
                throw new DomainException('Lượt khách này không còn mở, không tách được nữa.');
            }

            if ($luotGoc->paid_amount > 0) {
                throw new DomainException('Lượt khách này đã thu một phần tiền, không tự tách được. Đây là quyết định của con người, không phải của máy.');
            }

            $idBanTheoThuTu = collect($data->diningTableIds)->unique()->sort()->values();

            $banDuocChon = DiningTable::query()
                ->whereIn('id', $idBanTheoThuTu)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($banDuocChon->count() !== $idBanTheoThuTu->count()) {
                throw new DomainException('Có bàn không tồn tại trong danh sách đã chọn.');
            }

            $banDangChiemCuaLuotGoc = TableSessionTable::query()
                ->where('table_session_id', $luotGoc->id)
                ->whereNull('detached_at')
                ->get();

            $phanChiemDuocChon = collect();
            foreach ($banDuocChon as $ban) {
                $phanChiem = $banDangChiemCuaLuotGoc->firstWhere('dining_table_id', $ban->id);

                if ($phanChiem === null) {
                    throw new DomainException("Bàn {$ban->code} không thuộc lượt khách này, không tách được.");
                }

                $phanChiemDuocChon->push($phanChiem);
            }

            if ($phanChiemDuocChon->count() >= $banDangChiemCuaLuotGoc->count()) {
                throw new DomainException('Phải giữ lại ít nhất một bàn cho lượt khách gốc.');
            }

            $luotMoi = TableSession::query()->create([
                'uuid' => $data->uuid,
                'code' => $this->sinhMaLuotKhach(),
                'shift_id' => $shift->id,
                'guest_count' => $data->guestCount,
                'status' => TableSessionStatus::Open,
                'opened_by_user_id' => $data->actorUserId,
                'opened_at' => now(),
            ]);

            $conLaiCuaLuotGoc = $banDangChiemCuaLuotGoc->reject(
                fn (TableSessionTable $t) => $phanChiemDuocChon->contains('id', $t->id)
            );

            foreach ($phanChiemDuocChon as $phanChiem) {
                $phanChiem->update(['detached_at' => now()]);
            }

            $banChinhMoiId = $idBanTheoThuTu->first();
            foreach ($banDuocChon as $ban) {
                TableSessionTable::query()->create([
                    'table_session_id' => $luotMoi->id,
                    'dining_table_id' => $ban->id,
                    'is_primary' => $ban->id === $banChinhMoiId,
                    'attached_at' => now(),
                    'attached_by_user_id' => $data->actorUserId,
                ]);
            }

            $banChinhCuBiTach = $phanChiemDuocChon->firstWhere('is_primary', true);
            if ($banChinhCuBiTach !== null) {
                $banChinhMoiCuaLuotGoc = $conLaiCuaLuotGoc->sortBy(['attached_at', 'id'])->first();
                $banChinhMoiCuaLuotGoc->update(['is_primary' => true]);
            }

            $ketQuaChuyenMon = $this->moveOrderItem->handle(new MoveOrderItemData(
                sourceTableSessionId: $luotGoc->id,
                targetTableSessionId: $luotMoi->id,
                orderItemIds: $data->orderItemIds,
                actorUserId: $data->actorUserId,
            ));

            return ['source' => $ketQuaChuyenMon['source'], 'new' => $ketQuaChuyenMon['target']];
        });
    }

    private function sinhMaLuotKhach(): string
    {
        $homNay = now()->format('Ymd');
        $soThuTu = TableSession::query()->whereDate('opened_at', now()->toDateString())->count() + 1;

        return "PH-{$homNay}-".str_pad((string) $soThuTu, 4, '0', STR_PAD_LEFT);
    }
}
```

⚠️ Chú ý hai điểm khi soát: (1) `sinhMaLuotKhach()` ở đây vẫn dùng `count() + 1` — bản sao chưa được sửa theo cùng bản vá đua tranh mã đã áp cho `OpenTableSession`/`OpenShift` (đã tự ghi vào `docs/viec-ton.md`, xem Phần B mục 8). (2) Khoá `Shift` trước `TableSession` — xem Phần B mục 6 (bảng thứ tự khoá).

### 3.2. `app/Domain/Ordering/Actions/MoveOrderItem.php` (Bước 1)

```php
<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Actions;

use App\Domain\Ordering\DTO\MoveOrderItemData;
use App\Domain\Ordering\Enums\OrderItemStatus;
use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Enums\TableSessionStatus;
use App\Domain\Ordering\Models\Order;
use App\Domain\Ordering\Models\OrderItem;
use App\Domain\Ordering\Models\TableSession;
use App\Exceptions\DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class MoveOrderItem
{
    /** @return array{source: TableSession, target: TableSession} */
    public function handle(MoveOrderItemData $data): array
    {
        if ($data->orderItemIds === []) {
            throw new DomainException('Phải chọn ít nhất một dòng món để chuyển.');
        }

        if ($data->sourceTableSessionId === $data->targetTableSessionId) {
            throw new DomainException('Lượt khách nguồn và lượt khách đích không được trùng nhau.');
        }

        return DB::transaction(function () use ($data): array {
            $idLuotTheoThuTu = collect([$data->sourceTableSessionId, $data->targetTableSessionId])->sort()->values();

            $luotTheoId = TableSession::query()
                ->whereIn('id', $idLuotTheoThuTu)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $nguon = $luotTheoId->get($data->sourceTableSessionId);
            $dich = $luotTheoId->get($data->targetTableSessionId);

            if ($nguon === null || $dich === null) {
                throw new DomainException('Có lượt khách không tồn tại.');
            }

            if ($nguon->status !== TableSessionStatus::Open) {
                throw new DomainException('Lượt khách nguồn không còn mở, không chuyển món được nữa.');
            }

            if ($dich->status !== TableSessionStatus::Open) {
                throw new DomainException('Lượt khách đích không còn mở, không chuyển món sang được.');
            }

            if ($nguon->paid_amount > 0 || $dich->paid_amount > 0) {
                throw new DomainException('Một trong hai lượt khách đã thu tiền một phần, không tự chuyển món được. Đây là quyết định của con người, không phải của máy.');
            }

            $idMonTheoThuTu = collect($data->orderItemIds)->unique()->sort()->values();

            $dongMonDuocChon = OrderItem::query()
                ->whereIn('id', $idMonTheoThuTu)
                ->orderBy('id')
                ->lockForUpdate()
                ->with('order')
                ->get();

            if ($dongMonDuocChon->count() !== $idMonTheoThuTu->count()) {
                throw new DomainException('Có dòng món không tồn tại trong danh sách đã chọn.');
            }

            foreach ($dongMonDuocChon as $dong) {
                if ($dong->order->table_session_id !== $nguon->id) {
                    throw new DomainException('Có dòng món không thuộc lượt khách nguồn.');
                }

                if ($dong->status === OrderItemStatus::Cancelled) {
                    throw new DomainException('Không chuyển được dòng món đã huỷ.');
                }
            }

            $this->chuyenSangPhieuMoiTheoTram($dongMonDuocChon, $nguon, $dich, $data->actorUserId);

            app(RecalculateSessionSubtotal::class)->handle($nguon);
            app(RecalculateSessionSubtotal::class)->handle($dich);

            return ['source' => $nguon->refresh(), 'target' => $dich->refresh()];
        });
    }

    /** @param Collection<int, OrderItem> $dongMonDuocChon */
    private function chuyenSangPhieuMoiTheoTram(
        Collection $dongMonDuocChon,
        TableSession $nguon,
        TableSession $dich,
        int $actorUserId,
    ): void {
        $theoTram = $dongMonDuocChon->groupBy(fn (OrderItem $dong) => $dong->order->station->value);

        foreach ($theoTram as $tram => $nhomDong) {
            $daXongHet = $nhomDong->every(fn (OrderItem $dong) => $dong->status === OrderItemStatus::Served);

            $soThuTu = Order::query()->where('table_session_id', $dich->id)->max('sequence_no') + 1;

            $phieuMoi = Order::query()->create([
                'uuid' => (string) Str::uuid(),
                'table_session_id' => $dich->id,
                'sequence_no' => $soThuTu,
                'station' => $tram,
                'status' => $daXongHet ? OrderStatus::Served : OrderStatus::Sent,
                'created_by_user_id' => $actorUserId,
                'sent_at' => now(),
                'served_at' => $daXongHet ? now() : null,
                'note' => "Chuyển từ lượt khách {$nguon->code}",
            ]);

            OrderItem::query()->whereIn('id', $nhomDong->pluck('id'))->update(['order_id' => $phieuMoi->id]);
        }
    }
}
```

### 3.3. `app/Domain/Billing/Actions/ApplyPromotion.php` (Bước 6)

```php
<?php

declare(strict_types=1);

namespace App\Domain\Billing\Actions;

use App\Domain\Billing\DTO\ApplyPromotionData;
use App\Domain\Billing\DTO\CalculateBillData;
use App\Domain\Billing\Enums\PromotionAppliesTo;
use App\Domain\Billing\Enums\PromotionType;
use App\Domain\Billing\Models\Promotion;
use App\Domain\Ordering\Actions\RecalculateSessionSubtotal;
use App\Domain\Ordering\Enums\OrderItemStatus;
use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Enums\TableSessionStatus;
use App\Domain\Ordering\Models\OrderItem;
use App\Domain\Ordering\Models\TableSession;
use App\Exceptions\DomainException;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

final class ApplyPromotion
{
    public function __construct(
        private readonly CalculateBill $calculateBill,
    ) {}

    public function handle(ApplyPromotionData $data): TableSession
    {
        return DB::transaction(function () use ($data): TableSession {
            $khuyenMai = Promotion::query()
                ->where('code', $data->promotionCode)
                ->lockForUpdate()
                ->first();

            if ($khuyenMai === null) {
                throw new DomainException("Không tìm thấy khuyến mãi mã \"{$data->promotionCode}\".");
            }

            $this->kiemTraConHieuLuc($khuyenMai);

            $tableSession = TableSession::query()->lockForUpdate()->findOrFail($data->tableSessionId);

            if (! in_array($tableSession->status, [TableSessionStatus::Open, TableSessionStatus::Billing], true)) {
                throw new DomainException('Lượt khách này đã đóng hoặc đã huỷ, không áp khuyến mãi được.');
            }

            if ($tableSession->promotion_id !== null) {
                throw new DomainException('Lượt khách này đã áp một khuyến mãi rồi, không được áp thêm khuyến mãi thứ hai.');
            }

            app(RecalculateSessionSubtotal::class)->handle($tableSession);
            $tableSession->refresh();

            $tamTinhApDung = $this->tamTinhDuocApDung($tableSession, $khuyenMai);

            if ($khuyenMai->min_order_amount !== null && $tableSession->subtotal_amount < $khuyenMai->min_order_amount) {
                throw new DomainException("Đơn chưa đạt tối thiểu {$this->tien($khuyenMai->min_order_amount)} để áp khuyến mãi \"{$khuyenMai->name}\".");
            }

            $soTienGiam = $this->tinhSoTienGiam($khuyenMai, $tamTinhApDung);

            $khuyenMai->update(['used_count' => $khuyenMai->used_count + 1]);
            $tableSession->update(['promotion_id' => $khuyenMai->id]);

            $this->calculateBill->handle(new CalculateBillData(
                tableSessionId: $tableSession->id,
                discountAmount: $soTienGiam,
                discountReason: "Khuyến mãi {$khuyenMai->code} — {$khuyenMai->name}",
                requestedByUserId: $data->requestedByUserId,
                approverUserId: null,
                approverPin: null,
                skipApprovalThreshold: true,
            ));

            return $tableSession->refresh();
        });
    }

    private function kiemTraConHieuLuc(Promotion $khuyenMai): void
    {
        if (! $khuyenMai->is_active) {
            throw new DomainException("Khuyến mãi \"{$khuyenMai->name}\" đã ngưng, không áp dụng được.");
        }

        $bayGio = now();

        if ($khuyenMai->starts_at !== null && $bayGio->lt($khuyenMai->starts_at)) {
            throw new DomainException("Khuyến mãi \"{$khuyenMai->name}\" chưa bắt đầu.");
        }

        if ($khuyenMai->ends_at !== null && $bayGio->gt($khuyenMai->ends_at)) {
            throw new DomainException("Khuyến mãi \"{$khuyenMai->name}\" đã hết hạn.");
        }

        $this->kiemTraKhungGio($khuyenMai, $bayGio);

        if ($khuyenMai->max_usage !== null && $khuyenMai->used_count >= $khuyenMai->max_usage) {
            throw new DomainException("Khuyến mãi \"{$khuyenMai->name}\" đã dùng hết số lượt cho phép ({$khuyenMai->max_usage} lượt).");
        }
    }

    private function kiemTraKhungGio(Promotion $khuyenMai, CarbonInterface $bayGio): void
    {
        $luat = $khuyenMai->time_rules;
        if ($luat === null) {
            return;
        }

        $cacNgay = $luat['days'] ?? null;
        if ($cacNgay !== null && ! in_array($bayGio->dayOfWeek, $cacNgay, true)) {
            throw new DomainException("Khuyến mãi \"{$khuyenMai->name}\" không áp dụng vào hôm nay.");
        }

        $tu = $luat['from'] ?? null;
        $den = $luat['to'] ?? null;
        if ($tu !== null && $den !== null) {
            $gioHienTai = $bayGio->format('H:i');
            if ($gioHienTai < $tu || $gioHienTai >= $den) {
                throw new DomainException("Khuyến mãi \"{$khuyenMai->name}\" chỉ áp dụng từ {$tu} đến {$den}.");
            }
        }
    }

    private function tamTinhDuocApDung(TableSession $tableSession, Promotion $khuyenMai): int
    {
        if ($khuyenMai->applies_to === PromotionAppliesTo::All) {
            return $tableSession->subtotal_amount;
        }

        $truyVan = OrderItem::query()
            ->whereHas('order', fn ($q) => $q
                ->where('table_session_id', $tableSession->id)
                ->where('status', '!=', OrderStatus::Cancelled))
            ->where('status', '!=', OrderItemStatus::Cancelled);

        if ($khuyenMai->applies_to === PromotionAppliesTo::Product) {
            $truyVan->where('product_id', $khuyenMai->target_id);
        } else {
            $truyVan->whereHas('product', fn ($q) => $q->where('category_id', $khuyenMai->target_id));
        }

        return (int) $truyVan->sum('line_amount');
    }

    private function tinhSoTienGiam(Promotion $khuyenMai, int $tamTinhApDung): Money
    {
        $soTienGoc = match ($khuyenMai->type) {
            PromotionType::Amount => min($khuyenMai->value, $tamTinhApDung),
            PromotionType::Percent, PromotionType::HappyHour => intdiv($tamTinhApDung * $khuyenMai->value, 100),
        };

        if ($khuyenMai->max_discount_amount !== null && $soTienGoc > $khuyenMai->max_discount_amount) {
            $soTienGoc = $khuyenMai->max_discount_amount;
        }

        return Money::fromInt($soTienGoc);
    }

    private function tien(int $soTien): string
    {
        return Money::fromInt($soTien)->format();
    }
}
```

### 3.4. `app/Domain/Billing/Actions/CalculateBill.php` (sửa — thêm `skipApprovalThreshold` cho Bước 6)

```php
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

            if (! $data->skipApprovalThreshold) {
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

**Chốt quan trọng cần soát**: `$data->skipApprovalThreshold` chỉ được `ApplyPromotion` bật `true`. Mọi lời gọi khác (`CalculateBillController`, `ResolveSyncConflict::xuLyThuVuotGiamGia/xuLyGiaLech/xuLyCaDaDong` gián tiếp qua `RecordPayment`, không gọi `CalculateBill` với cờ này trừ `xuLyThuVuotGiamGia`/`bo_giam_gia` và `xuLyGiaLech`/`giam_gia_bu` — cả hai đều **không** truyền `skipApprovalThreshold`, tức vẫn đi qua luồng duyệt ngưỡng % bình thường, không có `approverUserId`/`approverPin` nào được `ResolveSyncConflict` truyền vào `CalculateBillData` ở hai chỗ này) — **cần soát kỹ**: nếu khoản giảm bù/bỏ giảm giá vượt ngưỡng vai trò của `resolvedByUserId`, `CalculateBill` sẽ ném `DomainException` đòi PIN duyệt ngay giữa transaction xử lý xung đột, làm rollback toàn bộ và xung đột không xử lý được — xem Phần B mục 8.

### 3.5. `app/Domain/Staffing/Actions/CloseShift.php` (sửa — chặn theo `sync_conflicts`, dispatch báo cáo)

Toàn văn — xem đã đọc, hai điểm mới của Phase 2: `kiemTraXungDotChuaXuLy()` (chặn đóng ca khi còn xung đột `pending` gắn với ca này hoặc không gắn lượt khách nào) và `SummarizeDailyReportJob::dispatch($shift->opened_at->toDateString())` sau khi transaction đóng ca đã commit (cố ý ngoài transaction).

### 3.6. `app/Domain/Staffing/Actions/OpenShift.php` (không đổi logic nghiệp vụ ở Phase 2, chỉ đã sửa đua tranh mã ca ở Bước 2 — liệt kê vì nằm trong phạm vi bản vá race condition)

Đã sửa từ `count()+1` sang dùng `id` tự tăng của chính ca vừa tạo (`sinhMaCa()`), ghi tạm bằng `Str::uuid()` cắt 30 ký tự rồi update lại — cùng kỹ thuật với `OpenTableSession::sinhMaLuotKhach()`.

---

*(Hết Phần A — tiếp tục ở `docs/review-phase2-b.md`.)*
