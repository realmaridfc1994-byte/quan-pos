# KIỂM TOÁN CHUẨN BỊ KHO — Phase 3 Bước 0

> Chỉ báo cáo hiện trạng. Không đề xuất cách sửa, không sửa code.

---

## 1. MỌI ĐƯỜNG LÀM `order_items.served_at` ĐƯỢC ĐẶT HOẶC ĐỔI

| File + dòng | Đặt trong tình huống nào | Có thể đặt HAI LẦN cho cùng một dòng món không |
|---|---|---|
| `app/Domain/Ordering/Actions/UpdateOrderItemStatus.php:40-43` | Bếp/quầy bấm "món này xong" trên KDS — dòng món đang `ordered` chuyển sang `served`, `served_at = now()`. Đây là ĐƯỜNG DUY NHẤT đặt `served_at` trên một dòng món có sẵn. | Không. `StatusTransition::kiemTra(['ordered','served'], ...)` ở dòng 38 chặn: dòng đã ở `served` gọi lại action này sẽ ném lỗi chuyển trạng thái không hợp lệ trước khi chạm tới `update()`. |
| `app/Domain/Ordering/Actions/CancelOrderItem.php:130` | Huỷ MỘT PHẦN số lượng (`tachVaHuyMotPhan`) tách dòng gốc thành dòng mới mang trạng thái `cancelled`. Dòng mới **kế thừa** `served_at` của dòng gốc (copy giá trị cũ, không gọi `now()`). | Không phải "đặt lần hai" theo nghĩa trừ kho — đây là COPY giá trị đã có sang một `order_items.id` MỚI, không UPDATE lại dòng gốc. Dòng gốc giữ nguyên `served_at` cũ, không đổi. |
| `app/Domain/Ordering/Actions/MoveOrderItem.php:131` | Đổi bàn: dòng món **không đổi `order_items.served_at`** — chỉ đổi `order_items.order_id` (dòng 135). Dòng 131 đặt `served_at` của **`orders`** (phiếu bao ngoài, bảng khác), không phải `order_items`. | Không áp dụng — file này không ghi vào cột `order_items.served_at`. |

**Trả lời câu cuối:** Không có đường nào UPDATE lại `served_at` một dòng `order_items` đã có giá trị `served_at`. `UpdateOrderItemStatus` bị `StatusTransition` chặn chuyển `served → served`. `CancelOrderItem` (huỷ một phần) không update dòng gốc, chỉ copy giá trị sang dòng mới tách ra (`id` khác). Không tìm thấy Action, Job, Listener, Observer hay Console Command nào khác ghi vào cột này (đã quét toàn bộ `app/` bằng grep `served_at`, chỉ ra đúng 5 vị trí liệt kê ở trên cộng khai báo `$fillable`/cast trong `Order.php` và `OrderItem.php`).

---

## 2. MỌI ĐƯỜNG LÀM `order_items.quantity` THAY ĐỔI

| File + dòng | Đổi trong tình huống nào | Món đó đã served chưa |
|---|---|---|
| `app/Domain/Ordering/Actions/PlaceOrder.php:172` (`OrderItem::create`) | Tạo dòng món mới khi gọi món — đây là lần GHI ĐẦU TIÊN, không phải "đổi". `quantity` = số lượng máy POS gửi lên. | Chưa — dòng mới luôn `status = ordered`, `served_at = null`. |
| `app/Domain/Ordering/Actions/UpdateOrderItem.php:27-28` | Sửa số lượng một dòng món — `kiemTraChuaGuiBep()` (dòng 44-49) CHẶN trước: chỉ cho sửa khi `order.status === sent` VÀ `order_item.status === ordered`. | Chưa — luôn chưa `served` (yêu cầu `status === ordered` là điều kiện bắt buộc để vào được nhánh sửa). |
| `app/Domain/Ordering/Actions/CancelOrderItem.php:112-113` | Huỷ MỘT PHẦN: `$item->update(['quantity' => $item->quantity - $data->quantity])` — dòng GỐC bị **giảm** `quantity`. | Dòng gốc có thể đã `served` (huỷ một phần dòng đã phục vụ bắt buộc PIN — dòng 60-62) hoặc còn `ordered`. `served_at` của dòng gốc KHÔNG bị xoá/đổi khi giảm `quantity` — vẫn giữ nguyên timestamp cũ dù số lượng đã giảm. |

**Trả lời câu đặc biệt (CancelOrderItem tách dòng):** Đúng như mô tả — dòng gốc giảm `quantity` xuống còn phần giữ lại, dòng mới (id khác) mang đúng phần `quantity` bị huỷ và trạng thái `cancelled` ngay. Hiện KHÔNG có bảng/cột kho nào tồn tại để "hoàn lại phần chênh" (chưa có `stock_entries`/`stock_movements` — Phase 3 Bước 1 trở đi mới tạo). Ở thời điểm kiểm toán này, `served_at` của dòng gốc vẫn còn nguyên sau khi giảm `quantity`, và dòng mới tách ra cũng kế thừa đúng `served_at` đó (xem mục 1) — tức là hiện trạng dữ liệu có đủ thông tin (`served_at` + `quantity` đã giảm trên dòng gốc, `served_at` + `quantity` bị huỷ trên dòng mới) để bất kỳ Action Phase 3 nào sau này tự tính phần chênh, nhưng CHƯA CÓ Action nào đọc hai con số này để hoàn kho — vì chưa tới Phase 3 Bước 5.

---

## 3. THỨ TỰ CÁC BƯỚC KHI MỘT MÓN ĐI TỪ GỌI ĐẾN PHỤC VỤ

1. **`PlaceOrder::handle()`** (`app/Domain/Ordering/Actions/PlaceOrder.php`) — trong MỘT `DB::transaction`:
   - Khoá `table_session` (`lockForUpdate`).
   - Kiểm tra lượt khách đang `open`, kiểm tra từng món cùng một trạm (bếp/quầy), kiểm tra tuỳ chọn.
   - Tạo `Order` (`status = sent`, `sent_at = now()`).
   - Tạo từng `OrderItem` qua `taoDongMon()` (`status = ordered`, `served_at = null`), copy `unit_price`/`options_amount` từ thực đơn hiện tại.
   - Gọi `RecalculateSessionSubtotal::handle($tableSession)` (cùng transaction).
2. **In tem bếp/quầy** — nằm ngoài phạm vi quét ở Bước 0 này (thuộc `App\Domain\Printing`, không đụng `served_at`/`quantity`).
3. **`UpdateOrderItemStatus::handle()`** (`app/Domain/Ordering/Actions/UpdateOrderItemStatus.php`) — bếp/quầy bấm xong trên KDS, MỘT `DB::transaction` riêng, KHÁC transaction với bước 1:
   - Khoá `OrderItem` (`lockForUpdate`), kiểm tra chuyển trạng thái `ordered → served` hợp lệ qua `StatusTransition`.
   - `update(['status' => served, 'served_at' => now()])`.
   - Khoá `Order` (`lockForUpdate`), gọi `capNhatTrangThaiPhieu()`:
     - Nếu `order.status === sent` → chuyển `preparing`.
     - Nếu không còn dòng nào chưa xong (loại trừ `served`/`cancelled`) → chuyển `order.status = served`, `order.served_at = now()` (đây là `served_at` của bảng `orders`, khác cột `order_items.served_at`).

Không có event/listener nào chen giữa hai bước (dự án cấm Model event/Observer theo CLAUDE.md mục 4.5, đã xác nhận đúng thực tế qua quét — không thấy `booted()` nghiệp vụ nào đụng `served_at`).

---

## 4. ĐƯỜNG ĐỒNG BỘ

- **Món đến muộn qua `SyncBatch`** (`app/Domain/Sync/Actions/SyncBatch.php`, hàm `xuLyPlaceOrder` dòng ~360-437): dựng lại `PlaceOrderData` từ payload máy POS gửi lên rồi gọi thẳng `PlaceOrder::handle()` — **CÙNG MỘT ĐƯỜNG** với món gọi lúc online. Kết quả: `OrderItem` luôn tạo với `status = ordered`, `served_at = null`. Không khác gì món gọi trực tuyến.
- **`match ($op->type)` trong `SyncBatch.php` dòng 261-277** liệt kê đủ các loại thao tác được đồng bộ: `open_session`, `attach_table`, `detach_table`, `place_order`, `send_to_kitchen`, `cancel_order_item`, `record_payment`, `close_session`. **KHÔNG có loại thao tác nào đổi `order_items.status`/`served_at` sang `served`.** Nghĩa là việc bếp bấm "món xong" trên KDS không đi qua hàng đợi đồng bộ offline — chỉ chạy khi online trực tiếp qua `UpdateOrderItemStatus`.
- **`ResolveSyncConflict`** (`app/Domain/Sync/Actions/ResolveSyncConflict.php`): danh sách thao tác áp dụng lại ở `match` dòng 890-925 gồm `place_order` (qua `xayItems`), `send_to_kitchen`, `cancel_order_item`, `attach_table`, `detach_table`, `record_payment`, `close_session` — **cùng tập với `SyncBatch`, không có `served`**. File này chỉ ĐỌC `OrderItemStatus::Served` một chỗ duy nhất (dòng 313, trong `cumCanPinDuyet()`) để quyết định có cần PIN duyệt trước khi huỷ món hay không — không GHI `served_at`/`status = served` ở bất kỳ đâu.
- **Kết luận mục 4:** không có đường nào (đồng bộ hàng loạt hay xử lý xung đột) tạo ra một `order_items` đã `served` ngay từ đầu. Mọi dòng món luôn khởi tạo `ordered`, và chỉ chuyển sang `served` qua đúng một Action (`UpdateOrderItemStatus`), luôn chạy online, không đi qua `SyncBatch`/`ResolveSyncConflict`.

---

## 5. CHỖ NỐI VỚI TIỀN

- **Giá bán của từng dòng món:** cột `order_items.unit_price` (bản sao giá tại thời điểm gọi, ghi trong `PlaceOrder::taoDongMon()` dòng 170 — luôn lấy từ `product_variants.price` hiện tại của server, không tin giá máy POS gửi lên) cộng `order_items.options_amount` (tổng tiền tuỳ chọn cho một đơn vị). Thành tiền dòng = cột sinh tự động `line_amount = (unit_price + options_amount) * quantity` (`docs/schema.md` dòng 524-526).
- **Giá vốn:** hiện KHÔNG có cột nào lưu giá vốn ở `order_items`, `product_variants`, hay bất kỳ bảng nào khác. Đã quét `docs/schema.md` toàn văn — không có `cost_price`/`cost_amount`/`gia_von`/`purchase_price`. Ba cột duy nhất chừa sẵn cho Phase 3 là `product_variants.tracks_inventory`, `stock_unit`, `stock_factor` (`docs/schema.md` dòng 392-394) — đều là thông tin ĐƠN VỊ QUY ĐỔI kho, không phải giá vốn. Giá vốn thật (giá nhập từng lô, bình quân gia quyền) là việc của Bước 4 (`docs/PHASE.md` dòng 32) — CHƯA tồn tại ở bất kỳ đâu trong schema hiện tại.
- **Giảm giá và lãi gộp theo dòng:** giảm giá chỉ tồn tại ở CẤP TỔNG BILL — hai cột `table_sessions.discount_amount` và `table_sessions.discount_reason` (`docs/schema.md` dòng 261-262), ràng buộc `ck_table_sessions_total: total_amount + discount_amount = subtotal_amount`. **Không có cột nào ở `order_items` lưu phần giảm giá phân bổ cho TỪNG DÒNG MÓN.** `CalculateBill::handle()` (`app/Domain/Billing/Actions/CalculateBill.php`) ghi thẳng `discount_amount`/`total_amount` vào `table_sessions`, không đụng `order_items`. `ApplyPromotion::handle()` (`app/Domain/Billing/Actions/ApplyPromotion.php`) tính `tamTinhDuocApDung()` có thể chỉ dựa trên MỘT SẢN PHẨM hoặc MỘT DANH MỤC (`PromotionAppliesTo::Product`/`Category`, dòng 159-163, cộng `line_amount` của các dòng khớp điều kiện) để tính ra `soTienGiam`, nhưng số tiền giảm cuối cùng vẫn chỉ được ghi một cục vào `table_sessions.discount_amount` qua `CalculateBill` — không có bước nào chia ngược số tiền giảm đó về lại từng `order_items.id`.
- **Hệ quả cho Bước 8 (báo cáo lãi gộp theo món):** nếu chỉ dùng dữ liệu hiện có, tổng `line_amount` của mọi dòng món (giá bán) trừ giá vốn (chưa tồn tại) sẽ KHÔNG khớp với `table_sessions.total_amount` thật khi có giảm giá, vì phần giảm giá không được phân bổ ngược về dòng nào cả — chỉ nằm ở cấp tổng bill.
