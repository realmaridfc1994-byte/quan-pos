# KIỂM TOÁN ĐỊNH DANH VÀ THỨ TỰ KHOÁ — PHASE 2 BƯỚC 0

> Chỉ báo cáo hiện trạng code, không đề xuất cách sửa.
> Rà tại commit hiện tại của `master` (2026-08-02).

---

## 1. BẢNG ĐỊNH DANH

| Bảng.cột | Sinh ở đâu | Cách sinh |
|---|---|---|
| `table_sessions.id` | Server (DB auto-increment) | `AUTO_INCREMENT` |
| `table_sessions.code` | ⚠️ Server | Đếm theo **ngày**: `app/Domain/Ordering/Actions/OpenTableSession.php:99-105` — `sinhMaLuotKhach()` chạy `TableSession::query()->whereDate('opened_at', now()->toDateString())->count() + 1`, ghép thành `PH-{Ymd}-{4 chữ số}` |
| `orders.id` | Server (DB auto-increment) | `AUTO_INCREMENT` |
| `orders.uuid` | **Máy POS gửi lên** | `app/Domain/Ordering/DTO/PlaceOrderData.php:37` đọc thẳng `$request->string('uuid')`, Action chỉ dùng lại để chống trùng (`PlaceOrder.php:46-49`), không tự sinh |
| `orders.sequence_no` | ⚠️ Server | Đếm theo **lượt khách**: `PlaceOrder.php:85` — `Order::query()->where('table_session_id', $tableSession->id)->max('sequence_no') + 1` |
| `order_items.id` | Server (DB auto-increment) | `AUTO_INCREMENT` |
| `order_item_options.id` | Server (DB auto-increment) | `AUTO_INCREMENT` |
| `payments.id` | Server (DB auto-increment) | `AUTO_INCREMENT` |
| `payments.uuid` | **Máy POS gửi lên** | `app/Domain/Billing/DTO/RecordPaymentData.php:28` đọc thẳng `$request->string('uuid')`; comment trong `RecordPayment.php:24-29` ghi rõ "uuid do MÁY POS sinh và gửi lên (không sinh ở server)" |
| `shifts.id` | Server (DB auto-increment) | `AUTO_INCREMENT` |
| `shifts.code` | ⚠️ Server | Đếm theo **ngày**: `app/Domain/Staffing/Actions/OpenShift.php:40-46` — `sinhMaCa()` chạy `Shift::query()->whereDate('opened_at', now()->toDateString())->count() + 1`, ghép thành `CA-{Ymd}-{2 chữ số}` |
| `cash_movements.id` | Server (DB auto-increment) | `AUTO_INCREMENT` |

**Tóm tắt:** trong 4 nhóm định danh có ý nghĩa nghiệp vụ (không tính `id` tự tăng), chỉ `orders.uuid` và `payments.uuid` là do máy POS sinh trước. `table_sessions.code`, `shifts.code`, `orders.sequence_no` đều do server tự đếm bằng một câu truy vấn `COUNT()`/`MAX()` tại thời điểm ghi — máy POS không có sẵn cách tự sinh các số này khi mất mạng.

---

## 2. THỨ TỰ KHOÁ

| Action | Khoá gì, theo thứ tự nào | Khớp luật CLAUDE.md mục 11 chưa |
|---|---|---|
| `OpenTableSession` | 1. `Shift` (ca đang mở) → 2. `DiningTable` (nhiều bàn, `orderBy('id')`) | Khớp — Shift trước, không đụng TableSession/Payment |
| `AttachTable` | 1. `TableSession` → 2. `DiningTable` | Không nằm trong 3 đối tượng của luật 11 (Shift/TableSession/Payment), không mâu thuẫn |
| `DetachTable` | 1. `TableSession` (không khoá gì thêm) | Khớp |
| `TransferTable` | 1. `TableSession` → 2. `DiningTable` (nhiều bàn, `orderBy('id')`) | Khớp |
| `PlaceOrder` | 1. `TableSession` (không khoá `Product`/`ProductVariant`) | Khớp |
| `SendToKitchen` | **Không có `DB::transaction()`, không `lockForUpdate()` nào** | Không thuộc phạm vi luật 11 (không đụng tiền) |
| `CancelOrderItem` | 1. `Order` → 2. `OrderItem` (cùng order) | Không thuộc 3 đối tượng của luật 11 |
| `VoidTableSession` | 1. `TableSession` | Khớp |
| `CloseTableSession` | 1. `TableSession` | Khớp |
| `RecordPayment` | 1. `TableSession` → 2. `Shift` → (gọi lồng `CloseTableSession` khoá lại chính `TableSession` đó trong cùng transaction) | ⚠️ **Ngược thứ tự luật 11** — luật ghi "Shift → TableSession → Payment", Action này khoá **TableSession trước, Shift sau** |
| `VoidPayment` | 1. `Payment` → 2. `TableSession` → 3. `Shift` (nhiều dòng, `orderBy('id')`) → 4. `Shift` đang mở (điều kiện) | ⚠️ Khoá `Payment` trước `Shift`, ngược thứ tự nêu ở luật 11; đồng thời khác thứ tự nội bộ so với `RecordPayment` (TableSession→Shift) |
| `CalculateBill` | 1. `TableSession` (gọi lồng `CloseTableSession` khoá lại `TableSession` cùng transaction) | Khớp |
| `OpenShift` | 1. `Shift` (ca đang mở) | Khớp |
| `CloseShift` | 1. `Shift` (không khoá `TableSession`/`Payment`/`CashMovement` khi tính `expected_cash`) | Khớp về Shift, nhưng đọc `TableSession`, tổng `Payment`, tổng `CashMovement` không khoá |
| `RecordCashMovement` | 1. `Shift` | Khớp |

**Điểm lệch thứ tự đáng chú ý:** `RecordPayment` khoá `TableSession` rồi mới khoá `Shift`; `VoidPayment` khoá `Payment` rồi mới khoá `TableSession` rồi mới khoá `Shift`. Luật 11 chốt thứ tự chung là `Shift → TableSession → Payment`. Hai Action này không theo đúng thứ tự đó (thứ tự thực tế: `RecordPayment` = TableSession→Shift; `VoidPayment` = Payment→TableSession→Shift).

---

## 3. PHỤ THUỘC MẠNG

| Action | Có bắt buộc đọc DB ngay lúc bấm mới quyết định được không? | Đọc gì → quyết định gì |
|---|---|---|
| `PlaceOrder` | **Có.** | Đọc `products`, `product_variants` (`findOrFail` dòng 64-65, 137-138) để lấy `is_active`, `effectiveStation()`, và **lấy `unit_price` trực tiếp từ `$variant->price`** (dòng 155) — máy POS không tự tính giá, phải hỏi server. Cũng đọc `option_groups`/`options` (dòng 110-116, 142) để kiểm tra `min_select`/`max_select` và `price_delta`. |
| `CalculateBill` / `RecalculateSessionSubtotal` | Không cần đọc thực đơn. | Chỉ tổng hợp `order_items.line_amount` (cột đã chốt sẵn trong DB) của các dòng chưa huỷ thuộc lượt khách đó (`RecalculateSessionSubtotal.php:26-35`). Việc duyệt giảm giá đọc thêm `users` (vai trò người duyệt) và tuỳ trường hợp gọi `VerifyApproverPin`, không đọc bảng thực đơn. |

**Kết luận mục 3:** `PlaceOrder` là Action phụ thuộc mạng rõ nhất — máy POS phải có kết nối tới server để lấy giá món, giá tuỳ chọn, trạng thái `is_active`, và nơi in tem tại đúng thời điểm gọi món. Máy POS hiện không giữ bản sao thực đơn để tự tính khi mất mạng.

---

## 4. CHỖ SERVER TỰ TÍNH

| Cột | Cơ chế | Vị trí định nghĩa |
|---|---|---|
| `order_items.line_amount` | Cột `STORED GENERATED ALWAYS AS ((unit_price + options_amount) * quantity)` | `database/migrations/2026_07_31_000013_create_order_items_table.php:37-39` |
| `table_session_tables.occupied_table_id` | Cột `STORED GENERATED ALWAYS AS (IF(detached_at IS NULL, dining_table_id, NULL))` | `database/migrations/2026_07_31_000006_create_table_session_tables_table.php:33-35` |
| `shifts.open_guard` | Cột `STORED GENERATED ALWAYS AS (IF(status = 'open', 1, NULL))` | `database/migrations/2026_07_31_000002_create_shifts_table.php:40-42` |

Cả ba cột này **MySQL/MariaDB tự tính khi ghi dòng**, không phải PHP tính rồi gửi giá trị xuống. Máy POS đang offline sẽ không tự tính ra các giá trị này — nếu ghi dữ liệu cục bộ (Dexie) trước khi đồng bộ, các cột này chỉ có giá trị đúng sau khi dòng dữ liệu thực sự chèn vào MySQL, không thể tính trước ở máy khách.

Ngoài ba cột trên, còn các cột "server tự tính bằng logic PHP trong Action" (không phải generated column, nhưng vẫn cần dữ liệu phía server mới tính được), đã liệt kê ở mục 1 và 3: `table_sessions.code`, `shifts.code`, `orders.sequence_no` (đếm bằng truy vấn), và `order_items.unit_price`/`options_amount` (lấy từ bảng thực đơn phía server).

---

## 5. MÓN TÁCH DÒNG VÀ served_at

Đọc từ `app/Domain/Ordering/Actions/CancelOrderItem.php`, hàm `tachVaHuyMotPhan()` (dòng 94-128).

**a. Dòng MỚI (`$dongMoi`) — KHÔNG kế thừa `served_at`.**
Câu lệnh `create()` (dòng 99-114) không có khoá `served_at` nào:

```php
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
```

Vì cột `served_at` mặc định `NULL` (`database/migrations/..._create_order_items_table.php:42`: `$table->dateTime('served_at')->nullable();`), dòng mới luôn có `served_at = NULL`, **bất kể dòng gốc đã được đánh dấu "served" hay chưa** trước khi tách.

**b. Dòng GỐC (`$item`) — giữ nguyên `served_at`.**
Câu lệnh cập nhật (dòng 96-97) chỉ đổi `quantity`:

```php
$soLuongConLai = $item->quantity - $data->quantity;
$item->update(['quantity' => $soLuongConLai]);
```

Không có khoá `served_at` trong `update()`, nên giá trị `served_at` cũ trên dòng gốc không đổi.

**Ghi chú hiện trạng (không đề xuất sửa):** nếu dòng gốc đã `served_at` có giá trị (món đã ra bàn) trước khi bị huỷ một phần, dòng mới tách ra để huỷ luôn mang `served_at = NULL`, dù phần số lượng đó trên thực tế đã được phục vụ (đó chính là lý do luật H5 bắt buộc PIN duyệt khi `status === Served`). Đây là dữ liệu Phase 3 sẽ cần khi tính hoàn/không hoàn kho theo `served_at`.
