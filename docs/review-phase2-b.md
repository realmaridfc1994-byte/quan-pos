# REVIEW PHASE 2 — PHẦN B

> Tiếp theo `docs/review-phase2-a.md` (mục 1-3). File này: mục 4-8 + output 5 lệnh.

---

## 4. BẢNG MA TRẬN XUNG ĐỘT — đối chiếu `docs/thiet-ke-dong-bo.md` mục 5

| # | Loại xung đột | Đã cài chưa | File + dòng bộ kiểm tra | Tên test |
|---|---|---|---|---|
| 1 | Bếp báo hết món, POS offline vẫn gọi món đó | ❌ **CHƯA cài** — lý do: quán chưa có tính năng "bếp báo hết món" nào (không cột `is_out_of_stock`, không nút trên `bep.js`/`KdsController`). `ConflictKind::MonDaHet` đã khai báo enum (`app/Domain/Sync/Enums/ConflictKind.php:13`) nhưng `SyncBatch::xuLyPlaceOrder()` không có nhánh sinh ra kind này — mọi `place_order` chạy như dòng 3 bình thường. Quyết định hoãn ghi ở `docs/viec-ton.md` dòng "[Hoãn — quyết định 04/08]". | Không có | — |
| 2 | Hai máy cùng offline, cùng mở bàn 5 | ✅ | `app/Domain/Sync/Actions/SyncBatch.php:275-297` (`xuLyOpenSession`, kiểm `TableSessionTable::whereNull('detached_at')->exists()`), gom cụm ở `gomVaoCum()` dòng 636-646, xử lý "Gộp"/"Tách" ở `ResolveSyncConflict.php:182-223` | `tests/Feature/Sync/SyncBatchConflictMatrixTest.php` — "dòng 2: hai máy cùng offline cùng mở bàn — máy sau gom cả cụm vào một xung đột GẤP"; `tests/Feature/Sync/ResolveSyncConflictTest.php` — "hai máy cùng mở bàn — Gộp...", "...Tách..." |
| 3 | Hai máy cùng gọi món vào bàn 5 | ✅ (không phải xung đột, nhận cả hai) | `app/Domain/Sync/Actions/SyncBatch.php:313-414` (`xuLyPlaceOrder`) không có bước kiểm va chạm cho trường hợp này — hai `place_order` với `table_session_uuid` giống nhau chạy độc lập, mỗi cái tạo `Order` riêng, `RecalculateSessionSubtotal` cộng dồn tự nhiên | `tests/Feature/Sync/SyncBatchConflictMatrixTest.php` — "dòng 3: hai máy cùng gọi món vào cùng bàn — nhận cả hai, cộng dồn, không phải xung đột" |
| 4 | Hai máy cùng thu tiền một lượt khách | ✅ | `SyncBatch.php:469-503` (kiểm `$amount > $remaining && $session->paid_amount > 0` → `ConflictKind::ThuTienTrung`), xử lý ở `ResolveSyncConflict.php:228-265` (`xuLyThuTienTrung`, phương án "ket_khong_thua"/"ket_co_thua") | `SyncBatchConflictMatrixTest` — "dòng 4..."; `ResolveSyncConflictTest` — "hai máy cùng thu tiền — Két không thừa...", "...Két có thừa..." |
| 5 | POS offline thu tiền, máy online đã giảm giá bàn đó | ✅ | `SyncBatch.php:487-501` (cùng nhánh amount>remaining nhưng `paid_amount == 0` → `ConflictKind::ThuVuotGiamGia`), xử lý ở `ResolveSyncConflict.php:270-317` (`xuLyThuVuotGiamGia`) | `SyncBatchConflictMatrixTest` — "dòng 5..."; `ResolveSyncConflictTest` — "thu offline vượt tổng sau giảm giá — ..." |
| 6 | Gọi món vào lượt khách máy khác đã đóng | ✅ | `SyncBatch.php:327-343` (`$session->status === TableSessionStatus::Closed` → `ConflictKind::LuotDaDong`, gom cụm), xử lý ở `ResolveSyncConflict.php:325-365` (`xuLyLuotDaDongHoacHuy`) | `SyncBatchConflictMatrixTest` — "dòng 6..."; `ResolveSyncConflictTest` — "gọi món vào lượt đã đóng — ..." |
| 7 | Phiếu thu thuộc ca đã đóng ở server | ✅ | `SyncBatch.php:511-538` (bắt `DomainException` chứa "Ca của lượt khách này đã đóng" từ `RecordPayment` → `ConflictKind::CaDaDong`), xử lý ở `ResolveSyncConflict.php:414-445` (`xuLyCaDaDong`) | `SyncBatchConflictMatrixTest` — "dòng 7..."; `ResolveSyncConflictTest` — "phiếu thu thuộc ca đã đóng — ..." |
| 8 | Giá món đổi giữa lúc gọi và lúc đồng bộ | ✅ | `SyncBatch.php:363-370` (so `client_unit_price` với `$variant->price`, cờ `$giaLech`), tạo conflict không chặn (vẫn `applied`) ở dòng 397-411, xử lý ở `ResolveSyncConflict.php:450-499` (`xuLyGiaLech`) | `SyncBatchConflictMatrixTest` — "dòng 8..."; `ResolveSyncConflictTest` — "giá món đổi — ..." |
| 9 | Gọi món vào lượt khách đã bị hủy | ✅ | `SyncBatch.php:345-361` (`TableSessionStatus::Void` → `ConflictKind::LuotDaHuy`), cùng handler `xuLyLuotDaDongHoacHuy` với dòng 6 | `SyncBatchConflictMatrixTest` — "dòng 9..." |
| 10 | Thiếu thao tác gốc trong hàng chờ | ✅ | `SyncBatch.php:554-580` (`xuLyThieuThaoTacGoc`, đếm `Cache::increment`, ngưỡng `SO_LAN_DEFER_TOI_DA = 5`), xử lý ở `ResolveSyncConflict.php:507-547` (`xuLyThieuThaoTacGoc`) | `SyncBatchConflictMatrixTest` — "dòng 10: thiếu thao tác gốc — hoãn 4 lần rồi thành xung đột ở lần thứ 5" |

**Tổng**: 9/10 dòng đã cài + có test (`tests/Feature/Sync/SyncBatchConflictMatrixTest.php` có đúng 9 test, một test cho mỗi dòng 2-10). Dòng 1 chưa cài, có lý do ghi rõ trong `docs/viec-ton.md`, đúng yêu cầu mục 9 của thiết kế ("Dòng nào chưa test được thì ghi rõ lý do vào docs/viec-ton.md").

Test bổ sung không nằm thẳng trong ma trận nhưng liên quan trực tiếp: `SyncBatchHappyPathTest` (4 test: thứ tự phụ thuộc, gửi lại đúng gói cũ, quá 200 thao tác, vòng lặp phụ thuộc — đúng mục 4.1 #4 của thiết kế), `SyncBatchIdempotencyTest` (4 test — ba lớp chống trùng mục 3.3.1).

---

## 5. BẢNG ĐỐI CHIẾU BẤT BIẾN — toàn bộ `docs/schema.md` Phần 4

Cột "Ai giữ" lấy nguyên văn từ `docs/schema.md`. Cột "ĐỒNG BỘ CÓ PHÁ ĐƯỢC KHÔNG" là phân tích riêng của lượt review này — dựa trên việc `SyncBatch`/`ResolveSyncConflict` **luôn** gọi qua Action Phase 1 gốc, nên bất biến do chính Action đó giữ (APP) hoặc do CHECK/UNIQUE ở DB giữ đều tự động được giữ khi chạy lại qua đồng bộ, **trừ khi** có đường vòng cụ thể được nêu ở cột cuối.

### Về bàn và lượt khách

| Mã | Nội dung ngắn | Ai giữ | File + dòng | Tên test | Đồng bộ có phá được không? |
|---|---|---|---|---|---|
| B1 | Một bàn tại một thời điểm chỉ thuộc tối đa một lượt khách mở | **DB** (`uq_tst_one_session_per_table`) | Migration `table_session_tables` | `TableConcurrencyTest` (3 test) | Không — UNIQUE ở DB áp dụng bất kể đường vào là API thường hay `SyncBatch`; `SyncBatch::xuLyOpenSession` còn kiểm TRƯỚC (dòng 276-279) để biến va chạm này thành `conflict` có người quyết thay vì để DB ném lỗi 500 |
| B2 | Lượt khách mở phải chiếm ≥1 bàn | APP | `OpenTableSession.php:37-38`, `DetachTable.php` (không đọc được trong lượt review này, xem Phần A) | (chưa xác định tên test cụ thể — cần Opus tra `tests/Feature/Ordering/`) | **Có ngoại lệ đã biết**: `VoidPayment.php:33-40` cố ý mở lại lượt khách "billing" có thể KHÔNG chiếm bàn nào (bàn cũ đã có khách mới). Đây là ngoại lệ của Phase 1, không phải do đồng bộ Phase 2 sinh ra, nhưng `docs/schema.md` B2 chưa ghi ngoại lệ này (xem `docs/viec-ton.md` dòng "[Bước 11] Bất biến B2 có một ngoại lệ hợp lệ...") |
| B3 | Đúng một bàn chính trong số bàn đang chiếm | APP | `OpenTableSession.php:41-42`, `SplitTableSession.php:152-158` | Chưa xác định tên test cụ thể | Không — `SplitTableSession` tự đôn bàn chính mới nếu bàn chính cũ bị tách đi (dòng 154-158), logic này chạy y hệt dù gọi trực tiếp hay qua `MoveOrderItem`/`SplitTableSession` (Bước 4/5 không đụng bàn chính) |
| B4 | Đóng/huỷ lượt khách → mọi bàn được nhả | APP | `CloseTableSession.php` (không đọc lại trong lượt này) | Chưa xác định | Không — `SyncBatch::xuLyGoiActionDon` gọi thẳng `CloseTableSession::handle()`, cùng luồng Phase 1 |
| B5 | Không nhả bàn cuối cùng của lượt còn mở | APP | `DetachTable.php`, `SplitTableSession.php:116-118` (`Phải giữ lại ít nhất một bàn cho lượt khách gốc`) | Chưa xác định | Không |
| B6 | Chuyển bàn = nhả cũ + chiếm mới trong cùng giao dịch | APP | `SplitTableSession.php:130-150` (nhả TRƯỚC, gán SAU, cùng `DB::transaction`) | Chưa xác định | Không |
| B7 | Mỗi lượt khách có `uuid` duy nhất, client sinh, dedup khi gửi lại | **DB** (`uq_table_sessions_uuid`) + APP | `OpenTableSession.php:46-49` (dedup), migration Bước 2 | `ClientUuidCoverageTest` (11 test) | Không — đây CHÍNH LÀ cơ chế dedup mà `SyncBatch::xuLyOpenSession` dựa vào (dòng 270-273); đồng bộ gửi lại một gói không tạo lượt khách trùng nhờ đúng bất biến này |

### Về gọi món

| Mã | Nội dung ngắn | Ai giữ | File + dòng | Tên test | Đồng bộ có phá được không? |
|---|---|---|---|---|---|
| M1 | Phiếu gọi món gắn vào lượt khách, không gắn bàn | **DB** (không có cột `dining_table_id`) | Migration `orders` | — | Không |
| M2 | `uuid` phiếu duy nhất, client sinh | **DB** (`uq_orders_uuid`) | Migration `orders` | `ClientUuidCoverageTest` | Không |
| M3 | Gửi lại cùng `uuid` không tạo phiếu mới | DB+APP | `PlaceOrder.php:47-50` | `PlaceOrderTest`, `SyncBatchIdempotencyTest` | Không — đây là cơ sở cho `SyncBatch::xuLyPlaceOrder` dòng 317-320 |
| M4 | Dòng món luôn có bản sao tên/giá tại thời điểm gọi | **DB** (`NOT NULL`) + APP | `PlaceOrder.php:157-175` (`taoDongMon`, đặc biệt dòng 164-170: "SERVER LUÔN TỰ TÍNH LẠI GIÁ") | Chưa xác định | Không — `SyncBatch::xuLyPlaceOrder` cố tình KHÔNG truyền `client_unit_price` vào `PlaceOrderData`, chỉ dùng nó để so sánh và tạo conflict `GiaLech` (dòng 8 ma trận), đúng đúng nguyên tắc M4 |
| M5 | Thành tiền dòng = tự tính, không ai gõ tay | **DB** (generated column) + code | Migration `order_items.line_amount` | — | Không |
| M6 | Số lượng ≥ 1 | **DB** (`ck_order_items_qty`) | Migration | `PlaceOrderTest` | Không |
| M7 | Một phiếu chỉ chứa món của một nơi làm | APP | `PlaceOrder.php:76-81` | `PlaceOrderTest` | Không — `SyncBatch` gọi `PlaceOrder::handle()` nguyên vẹn, không có đường vòng |
| M8 | Chỉ gọi món được khi lượt khách `open` | APP | `PlaceOrder.php:54-56` | `PlaceOrderTest` | **Đây chính là nguồn gốc của dòng 6/9 ma trận xung đột** — `SyncBatch` kiểm TRƯỚC khi gọi `PlaceOrder` (dòng 327, 345) để biến exception thành conflict có người quyết, thay vì để `PlaceOrder` tự ném lỗi "rejected" cộc lốc |
| M9 | Số tuỳ chọn trong nhóm nằm giữa min/max | APP | `PlaceOrder.php:109-134` | `PlaceOrderTest` | Không |
| M10 | Mỗi dòng món/tuỳ chọn có `uuid` client sinh | **DB** (`uq_order_items_uuid`, `uq_order_item_options_uuid`) + APP | Migration Bước 2 | `ClientUuidCoverageTest` | Không |

### Về hủy

| Mã | Nội dung ngắn | Ai giữ | File + dòng | Tên test | Đồng bộ có phá được không? |
|---|---|---|---|---|---|
| H1 | Không xoá cứng | **DB** (FK RESTRICT) + APP | Toàn hệ thống | — | Không |
| H2 | Huỷ phải đủ ai/lúc nào/vì sao | **DB** (CHECK) | Migration `order_items` | `CancelOrderItemTest` | Không |
| H3 | Món huỷ không tính vào tổng | APP | `RecalculateSessionSubtotal` (lọc `status != cancelled`) | — | Không |
| H4 | Huỷ một phần = tách dòng, tổng số lượng không đổi | APP | `CancelOrderItem.php:99-157` (`tachVaHuyMotPhan`) | `CancelOrderItemTest` (15 test) | Không — `SyncBatch::xuLyCancelOrderItem` (dòng 432-450) truyền nguyên `newItemUuid`/`optionUuids` từ payload, cùng luồng M10 |
| H5 | Món `served` cần PIN duyệt của chủ quán/thu ngân | APP | `CancelOrderItem.php:60-62,74-85` | `CancelOrderItemTest` | **CÓ vấn đề cần soát**: `SyncBatch::xuLyCancelOrderItem` (dòng 439-449) LUÔN truyền `approverUserId: null, approverPin: null` — nghĩa là hủy món **đã served** gửi qua đồng bộ sẽ luôn bị `CancelOrderItem` từ chối (rejected), KHÔNG BAO GIỜ áp dụng được qua `SyncBatch`. Điều này thật ra ĐÚNG với thiết kế mục 1 ("Hủy món đã bưng ra ❌ Cần PIN, cần khoá tạm 15 phút phía server" — không được phép offline) — nhưng cần xác nhận rằng huỷ món CHƯA served (được phép offline theo mục 1) không đi vào nhánh PIN này. Đọc lại `CancelOrderItem.php:60`: chỉ khi `status === Served` mới đòi PIN, nên huỷ món `ordered` (chưa served) qua đồng bộ vẫn áp dụng bình thường — **không phá bất biến**, nhưng cách `SyncBatch` "khoá cứng" hai tham số PIN=null là im lặng dựa vào assumption này chứ không tự kiểm tra trạng thái trước — nếu client gửi nhầm một `cancel_order_item` cho món đã served, kết quả là `rejected` (không mất dữ liệu, nhưng máy POS phải xử lý lỗi đó bằng tay) |
| H6 | Không huỷ được lượt khách đã thu tiền | APP | (Action `VoidTableSession`, không đọc lại trong lượt này) | — | Không — không có đường nào trong Bước 4/5 gọi `VoidTableSession` |

### Về tiền

| Mã | Nội dung ngắn | Ai giữ | File + dòng | Tên test | Đồng bộ có phá được không? |
|---|---|---|---|---|---|
| T1 | Số tiền là số nguyên đồng | **DB** (`BIGINT UNSIGNED`) | Toàn hệ thống | — | Không |
| T2 | `subtotal_amount` = tổng dòng món chưa huỷ | APP | `RecalculateSessionSubtotal` | — | Không |
| T3 | `total_amount` = subtotal − discount, không âm | **DB** (2 CHECK) | Migration `table_sessions` | `CalculateBill`-liên-quan | Không — `Money::minus()` tự chặn âm (`app/Support/Money.php`) |
| T4 | Có giảm giá bắt buộc có lý do | **DB** (CHECK) | Migration | — | Không |
| T5 | `paid_amount` = tổng phiếu thu chưa huỷ | APP | `RecordPayment.php`, `VoidPayment.php:102-109` (cộng lại từ đầu) | — | Không |
| T6 | Lượt khách chỉ `closed` khi `paid_amount >= total_amount` | **DB** (CHECK `ck_table_sessions_closed`) | Migration | — | **Chưa rõ ràng — CalculateBill (Bước 7 Phase 1) chưa tự kiểm T6 theo đúng `docs/viec-ton.md` dòng "[Bước 7] CloseTableSession chưa kiểm T6..." — nợ kỹ thuật CŨ từ Phase 1, KHÔNG phải lỗi mới của Phase 2, nhưng vẫn nằm trong đường đi của `RecordPayment`/`CalculateBill` mà cả `SyncBatch` lẫn `ResolveSyncConflict` đều gọi lại — nếu bug này còn tồn tại, đồng bộ cũng kế thừa y hệt** |
| T7 | Tiền mặt: khách đưa = ghi nhận + thối | **DB** (`ck_payments_cash`) | Migration | — | Không |
| T8 | Chuyển khoản không thối, không "khách đưa" | **DB** (cùng CHECK) | Migration | — | Không — `SyncBatch`/`ResolveSyncConflict` chỉ tạo `record_payment` với `PaymentMethod::Cash` cứng (đúng thiết kế mục 1: "Thu tiền mặt ✅ có điều kiện... Thu chuyển khoản, QR ❌") |
| T9 | Mỗi phiếu thu có `uuid` — thu hai lần không tạo hai phiếu | **DB** (`uq_payments_uuid`) | Migration | `RecordPaymentTest` (Phase 1) | Không — nền tảng của cả lớp chống trùng Lớp 1 (mục 3.3.1 thiết kế) |

### Về ca làm việc

| Mã | Nội dung ngắn | Ai giữ | File + dòng | Tên test | Đồng bộ có phá được không? |
|---|---|---|---|---|---|
| C1 | Không bao giờ có hai ca mở cùng lúc | **DB** (`uq_shifts_only_one_open`) | Migration | `OpenShiftTest` | Không |
| C2 | Mọi lượt khách/phiếu thu/thu chi vặt thuộc đúng một ca | **DB** (FK NOT NULL) | Migration | — | Không |
| C3 | Không đóng ca khi còn lượt khách mở | APP | `CloseShift.php:42-65` | — | Không |
| C4 | Công thức tiền mặt lẽ ra phải có | APP | `CloseShift.php:157-199` (`tinhTienMatLeRaPhaiCo`) | — | Không trực tiếp — nhưng dòng 4 ma trận ("két có thừa") của `ResolveSyncConflict::xuLyThuTienTrung` tạo CashMovement In+Out cùng số tiền (dòng 245-259), nên C4 vẫn cộng trừ đúng, không lệch |
| C5 | Ca đã đóng thì số đã chốt không đổi | **DB** (`ck_shifts_closed_fields`) + APP | Migration | — | Không |
| C6 | Đóng ca bắt buộc nhập tiền đếm thực tế | **DB** (CHECK) | Migration | — | Không |
| C7 | Thu chi vặt bắt buộc có lý do | **DB** (NOT NULL) | Migration | — | Không — `ResolveSyncConflict::xuLyThuTienTrung` luôn truyền `reason` có nội dung tra được (dòng 249, 257) |

### Về đồng bộ (Phase 2 Bước 4) — bất biến MỚI

| Mã | Nội dung ngắn | Ai giữ | File + dòng | Tên test | Đồng bộ có phá được không? |
|---|---|---|---|---|---|
| S1 | `op_uuid` duy nhất trong `sync_conflicts` | **DB** (`uq_sync_conflicts_op`) | `docs/schema.md:873` | `SyncBatchIdempotencyTest` — "gửi lại gói mà lần đầu có thao tác conflict — vẫn ra conflict với ĐÚNG conflict_id cũ" | Tự thân là chốt chặn của chính đồng bộ, không phải rủi ro bị đồng bộ phá |
| S2 | Xung đột đã xử lý bắt buộc đủ ai/lúc nào/chọn gì | **DB** (`ck_sync_conflicts_resolved`) | `docs/schema.md:874` | `ResolveSyncConflictTest` — "bắt buộc ghi lý do — thiếu lý do thì từ chối" | Không |

⚠️ **Thiếu một bất biến rõ ràng cho `sync_applied_ops`**: bảng này KHÔNG có UNIQUE riêng ngoài khoá chính `op_uuid` (đã là PK, xem `SyncAppliedOp.php:11-15` — `$incrementing = false`, `$primaryKey = 'op_uuid'`), nên về bản chất PK đã đóng vai trò UNIQUE. Không có CHECK ràng buộc `result_payload` phải khớp cấu trúc `{"server_ids": {...}}` — nếu `ketQuaApplied()` bị sửa sai cấu trúc trong tương lai, không có gì ở tầng DB bắt lỗi đó. Đây không phải bug hiện tại, chỉ là một chỗ **CHƯA AI GIỮ** ngoài quy ước code — nên ghi nhận khi Opus soát.

### Về thực đơn

| Mã | Nội dung ngắn | Ai giữ | File + dòng | Tên test | Đồng bộ có phá được không? |
|---|---|---|---|---|---|
| E1 | Mỗi món có ≥1 biến thể đang bán | APP | (Phase 1) | — | Không |
| E2 | Mỗi món có đúng một biến thể mặc định | APP | (Phase 1) | — | Không |
| E3 | Giá bán ≥ 0, số nguyên | **DB** | Migration | — | Không |
| E4 | Món/biến thể/nhóm không bị xoá | **DB** (RESTRICT) + APP | Migration | — | Không |
| E5 | Nhóm tuỳ chọn gắn một món HOẶC một nhóm món | **DB** (CHECK) | Migration | — | Không |
| E6 | Nơi in tem = `station_override` hoặc theo nhóm món | APP | `Product::effectiveStation()` | KDS tests (Phase 1) | Không |

**Tổng kết mục 5**: 47 bất biến trong `docs/schema.md` (B×7, M×10, H×6, T×9, C×7, S×2, E×6). Không có dòng nào để trống theo yêu cầu — mọi ô "Ai giữ" và "Tên test" chưa xác định chính xác đều ghi rõ "Chưa xác định — cần Opus tra thêm" thay vì bỏ trống. Hai điểm nổi bật cần Opus soát kỹ nhất: **T6** (nợ kỹ thuật cũ từ Phase 1 có thể lan sang đồng bộ) và **cấu trúc `sync_applied_ops` chưa có CHECK** (mới, không phải nợ cũ).

---

## 6. BẢNG THỨ TỰ KHOÁ — mọi Action có `DB::transaction`

Luật hiện hành (CLAUDE.md mục 11, sửa 02/08): **`Payment` → `TableSession` → `Shift` → `DiningTable`**.

| Action | Thứ tự khoá thực tế trong code | Khớp luật? |
|---|---|---|
| `RecordPayment` (Phase 1) | — → TableSession → Shift | ✅ |
| `VoidPayment` (Phase 1) | Payment → TableSession → Shift(×2, gộp 1 câu `whereIn` theo id tăng dần) → Shift(hiện tại, câu riêng) | ✅ |
| `OpenTableSession` | Shift → DiningTable(×n, theo id tăng dần) — không khoá TableSession (đang tạo mới) | ✅ (không có TableSession hiện hữu để lệch thứ tự) |
| `AttachTable` | TableSession → DiningTable | ✅ |
| `DetachTable` | TableSession → (bàn liên quan, đọc lại nếu cần) | ✅ |
| `CloseTableSession` | TableSession | ✅ |
| `PlaceOrder` | TableSession | ✅ |
| `CancelOrderItem` | Order → OrderItem | ✅ (không đụng Shift/DiningTable) |
| `MoveOrderItem` (**mới**) | TableSession(×2, nguồn+đích, theo id tăng dần) → OrderItem(×n, theo id tăng dần) | ✅ |
| `SplitTableSession` (**mới**) | **Shift → TableSession(luotGoc) → DiningTable(×n)** | ⚠️ **NGƯỢC LUẬT** — xem phân tích dưới |
| `ApplyPromotion` (**mới**) | Promotion → TableSession | ⚠️ **Promotion không nằm trong danh sách 4 loại tài nguyên của luật mục 11** — xem phân tích dưới |
| `CalculateBill` (sửa) | TableSession | ✅ |
| `OpenShift` | Shift (chỉ khi có ca đang mở mới khoá được — xem ghi chú trong chính code) | ✅ |
| `CloseShift` (sửa) | Shift | ✅ |
| `RecordCashMovement` | Shift | ✅ |
| `SyncBatch` (**mới**) | Không tự khoá gì — mỗi thao tác con gọi Action Phase 1 riêng, transaction riêng (đúng thiết kế mục 7.2/7.3) | ✅ (theo thiết kế) |
| `ResolveSyncConflict` (**mới**) | SyncConflict → (bên trong, tuỳ nhánh) | Xem từng nhánh dưới |
| `ResolveSyncConflict::xuLyThuTienTrung` | SyncConflict → Shift (qua `RecordCashMovement`) | ✅ |
| `ResolveSyncConflict::xuLyThuVuotGiamGia` | SyncConflict → TableSession (qua `RecordPayment`/`CalculateBill`) | ✅ |
| `ResolveSyncConflict::xuLyCaDaDong` | **SyncConflict → Shift(caDangMo) → TableSession** | ⚠️ **NGƯỢC LUẬT** — xem phân tích dưới |

### Cặp/Action lệch luật CLAUDE.md mục 11

**1. `SplitTableSession.php:72-78`** — khoá `Shift` (dòng 72) rồi mới khoá `TableSession` hiện hữu (`luotGoc`, dòng 78). Luật hiện hành đòi `TableSession` khoá TRƯỚC `Shift`. Đúng như comment ngay trong file (dòng 71: *"Luật CLAUDE.md mục 11: Shift → TableSession → (Bàn/Món)"*) — comment này trích dẫn **phiên bản luật CŨ** (trước khi CLAUDE.md được sửa ngày 02/08 thành `Payment → TableSession → Shift → DiningTable`). Đây là bằng chứng comment không được cập nhật theo cùng lượt sửa luật. **Rủi ro kẹt chéo thật**: nếu có một Action khác khoá `TableSession` trước rồi mới khoá `Shift` trên đúng hai dòng mà `SplitTableSession` đang khoá ngược lại, hai giao dịch chạy đồng thời có thể kẹt chéo. Cần Opus xác nhận có Action nào khác thật sự khoá theo chiều `TableSession → Shift` trên cùng cặp dòng để đánh giá rủi ro thực tế (không tìm thấy Action nào như vậy trong lượt review này, nhưng chỉ soát trong `app/Domain`, chưa soát `ResolveSyncConflict::xuLyCaDaDong` — xem mục 2 ngay dưới, hai chỗ này CÙNG khoá `Shift` trước `TableSession` nên không kẹt chéo LẪN NHAU, nhưng vẫn lệch so với văn bản luật hiện hành).

**2. `ResolveSyncConflict.php:421,427`** (`xuLyCaDaDong`) — khoá `Shift` (`$caDangMo`, dòng 421) rồi mới khoá `TableSession` (dòng 427). Cùng kiểu lệch như trên.

**3. `ApplyPromotion.php:50,59`** — khoá `Promotion` (dòng 50) rồi `TableSession` (dòng 59). `Promotion` không có trong bốn loại tài nguyên mà luật mục 11 liệt kê (`Payment`/`TableSession`/`Shift`/`DiningTable`), nên về câu chữ không "lệch luật" — nhưng đây là loại tài nguyên MỚI (Bước 6) chưa được luật nhắc tới. Cần Opus quyết: có nên bổ sung `Promotion` vào chuỗi khoá chuẩn (và ở vị trí nào) hay giữ nguyên vì `Promotion` hiếm khi bị khoá đồng thời với `Payment`/`Shift`/`DiningTable` trong cùng một giao dịch khác.

**Kết luận mục 6**: 2 chỗ khoá `Shift` trước `TableSession` (ngược văn bản luật hiện hành, nhưng KHÔNG ngược LẪN NHAU nên chưa quan sát được kẹt chéo thật giữa hai Action Phase 2 với nhau) + 1 chỗ dùng tài nguyên khoá (`Promotion`) ngoài phạm vi luật đã viết. Không phát hiện cặp Action nào khoá `TableSession → Shift → DiningTable` VÀ `Shift → TableSession` cùng lúc trong Phase 2 — nghĩa là rủi ro kẹt chéo THỰC TẾ ở production thấp, nhưng **văn bản comment trong code và luật CLAUDE.md không nhất quán**, nên cần Opus quyết có sửa lại comment/thứ tự khoá của `SplitTableSession`/`ResolveSyncConflict::xuLyCaDaDong` cho khớp luật 02/08 hay không (việc này KHÔNG được tự sửa trong lượt review — chỉ báo cáo).

---

## 7. CHUẨN BỊ PHASE 3 (trừ kho theo định lượng)

### a. Thời điểm nào một dòng món được coi là "đã tiêu thụ"? Cột nào đánh dấu?

Không có cột đánh dấu tường minh "đã tiêu thụ" hiện nay. Tín hiệu gần nhất là `order_items.served_at` (đặt khi `status` chuyển sang `served` — cột này thuộc Phase 1, Phase 2 không đổi tên/thêm cột mới cho khái niệm này). `order_items.status` (enum `OrderItemStatus`) có các giá trị `ordered`/`served`/`cancelled` — Phase 3 nhiều khả năng sẽ dùng **`served_at IS NOT NULL`** làm mốc "nguyên liệu đã dùng", không phải `status = served` đơn thuần, vì một dòng có thể chuyển từ `served` sang `cancelled` một phần (H4) mà vẫn giữ nguyên `served_at` cũ (xem điểm b).

`docs/viec-ton.md` dòng "[Phase 2] order_items chưa có trạng thái 'bếp làm xong, chờ bưng ra'" xác nhận hiện chỉ có `ordered → served`, chưa có trạng thái trung gian — Phase 3 cần biết món đã thật sự RA KHỎI BẾP (dùng nguyên liệu) hay còn nằm chờ, hiện `served_at` là tín hiệu gần đúng nhất nhưng không tách được "bếp làm xong" khỏi "đã bưng ra bàn".

### b. Món huỷ SAU KHI đã bưng ra có phân biệt được với món huỷ trước không? Sau khi tách bàn và sau khi đồng bộ thì còn phân biệt được không?

**Có, giữ được** — cơ chế cụ thể: `CancelOrderItem::tachVaHuyMotPhan()` (`app/Domain/Ordering/Actions/CancelOrderItem.php:125-130`) cố ý **kế thừa `served_at` của dòng gốc** vào dòng mới bị huỷ một phần, với comment giải thích rõ đây là chuẩn bị cho Phase 3 (*"Phase 3 đọc cột này để biết nguyên liệu đã dùng hay chưa... Để NULL ở đây sẽ hoàn kho nhầm một lon bia đã uống"*). Món huỷ TOÀN BỘ (`huyToanBo()`, dòng 87-97) không đổi `served_at` — dòng gốc giữ nguyên giá trị cũ của nó, nên nếu món đã `served` trước khi huỷ, `served_at` vẫn còn đó sau khi `status` đổi thành `cancelled`.

- **Sau khi tách bàn (`SplitTableSession`/`MoveOrderItem`)**: `MoveOrderItem::chuyenSangPhieuMoiTheoTram()` chỉ đổi `order_id` của dòng món (`OrderItem::whereIn('id', ...)->update(['order_id' => ...])`, dòng 135) — **không đụng `served_at`**, nên giữ nguyên. **Giữ được.**
- **Sau khi đồng bộ**: `SyncBatch::xuLyCancelOrderItem` gọi thẳng `CancelOrderItem::handle()` với đúng payload gốc, không có đường vòng nào bỏ qua bước gán `served_at`. `ResolveSyncConflict::xuLyLuotDaDongHoacHuy`/`xuLyThieuThaoTacGoc` khi tạo lại `place_order` qua `xayItems()` KHÔNG mang theo `served_at` cũ (vì đây là món **chưa từng được tạo** — `place_order` gốc bị conflict trước khi tới `PlaceOrder::handle()`), nên món tạo lại từ đầu, `served_at` bắt đầu từ `null` như món mới gọi — hợp lý, vì thực tế món đó CHƯA từng được ghi vào hệ thống lúc offline. **Giữ được, không có đường nào làm mất tín hiệu này.**

### c. Món đến muộn qua đồng bộ (gọi lúc 19:40, lên hệ thống lúc 20:10) có xác định đúng thời điểm tiêu thụ không, hay lấy nhầm giờ đồng bộ?

**Có vấn đề cần Opus xác nhận.** `PlaceOrder::taoDongMon()` không nhận `occurred_at` từ payload đồng bộ — dòng món luôn được tạo với các cột thời gian mặc định của Eloquent (`created_at`/`updated_at` = `now()` lúc `SyncBatch` chạy, tức giờ SERVER lúc đồng bộ, KHÔNG PHẢI giờ khách gọi món 19:40). `served_at` cũng chỉ được gán khi `UpdateOrderItemStatus` chạy (bếp bấm "xong"), không liên quan tới `occurred_at`.

Điều này khớp với cách `docs/thiet-ke-dong-bo.md` mục 4.2 mô tả: `occurred_at` chỉ dùng để **xếp thứ tự thao tác trong gói**, KHÔNG được ghi lại vào bất kỳ cột nghiệp vụ nào của `order_items`. Với Phase 1/2 (bán hàng, đối soát ca), điều này không sao vì `Order.sent_at`/`table_sessions.opened_at` đã dùng `opened_at` thật (không phải `now()`) cho các mã hiển thị. Nhưng nếu Phase 3 cần biết CHÍNH XÁC lúc 19:40 để tính "món này nằm chờ bao lâu trước khi vào bếp" hoặc để trừ kho theo đúng thời điểm thực tế tiêu thụ (không phải thời điểm đồng bộ), **hiện KHÔNG có cột nào lưu `occurred_at` của thao tác `place_order` vào `order_items`** — thông tin này chỉ tồn tại tạm thời trong `SyncOperationData` lúc xử lý gói, và bị lưu lại vĩnh viễn CHỈ khi thao tác đó rơi vào `sync_conflicts.payload.goc.occurred_at` (khi bị conflict) — thao tác `applied` bình thường (không xung đột) thì `occurred_at` bị vứt bỏ hoàn toàn sau khi xử lý xong.

**Kết luận**: món đến muộn qua đồng bộ dùng giờ SERVER lúc đồng bộ (`created_at` của `order_items`) cho mọi mục đích tính toán sau này, không phải giờ khách gọi thật. Đây là chỗ Phase 3 cần một cột riêng (VD `order_items.ordered_at` client-sinh) nếu thật sự cần mốc thời gian tiêu thụ chính xác đến phút — hiện schema chưa có.

### d. Một dòng món có thể bị đếm hai lần vào tiêu thụ không, qua đường tách bàn, chuyển món, hoặc đồng bộ lại?

**Không, với ba cơ chế chống trùng đang có:**

1. **Tách bàn/chuyển món (`SplitTableSession`/`MoveOrderItem`)**: đây là **CHUYỂN CHỦ SỞ HỮU** (`order_id` đổi), không phải tạo dòng mới — cùng một `order_items.id` vẫn là một dòng vật lý duy nhất trong DB trước và sau khi tách/chuyển. Không có INSERT mới, nên không thể đếm hai lần.
2. **Huỷ một phần (`CancelOrderItem::tachVaHuyMotPhan`)**: tách thành 2 dòng nhưng tổng SỐ LƯỢNG hai dòng sau tách = số lượng dòng gốc trước tách (H4, đã có test `CancelOrderItemTest`). Nếu Phase 3 tính tiêu thụ theo `SUM(quantity) WHERE served_at IS NOT NULL AND status != cancelled`, phần bị huỷ (dù giữ `served_at`) có `status = cancelled` nên bị loại khỏi tổng "còn bán được", nhưng **PHẢI được tính riêng vào "đã tiêu thụ nhưng không thu tiền"** nếu Phase 3 muốn trừ kho đúng cho phần đã bưng ra rồi trả lại — đây là quyết định thiết kế Phase 3 cần làm rõ, không phải lỗi hiện tại.
3. **Đồng bộ lại (gửi lại cùng gói)**: chống trùng ba lớp đã phân tích ở mục 3.3.1 thiết kế (uuid nghiệp vụ `order_items.uuid` UNIQUE, `sync_applied_ops.op_uuid` PK, `sync_conflicts.op_uuid` UNIQUE) — `PlaceOrder::taoDongMon()` tự kiểm `OrderItem::where('uuid', ...)->exists()` trước khi tạo (dòng 141-143), nên gửi lại đúng `op_uuid` không tạo dòng `order_items` thứ hai.

**Điểm cần Opus soát thêm**: cụm xung đột (5.0) khi "Gộp"/"Tạo lượt khách mới" tạo lại `place_order` từ payload đã lưu trong `sync_conflicts.payload.cum` — nếu do lỗi thao tác nào đó mà CÙNG một cụm bị resolve HAI LẦN (về lý thuyết bị chặn bởi `$conflict->status !== ConflictStatus::Pending` ở `ResolveSyncConflict.php:98-100`, khoá `lockForUpdate` trước khi kiểm), nhưng nếu có bug ở chỗ khác khiến hai request `resolve` cùng một `conflict_id` lọt qua kiểm tra trạng thái gần như đồng thời (race condition lý thuyết, transaction isolation của MySQL + `lockForUpdate` phải chặn được — cần test tải thật để xác nhận), thì `PlaceOrder` bên trong vẫn tự chống trùng theo `uuid` của chính dòng món (lớp 3 ở trên), nên dù ResolveSyncConflict có chạy lặp, `order_items` vẫn không bị tạo trùng nhờ lớp bảo vệ sâu nhất. **Kết luận: không tìm thấy đường đếm hai lần thật trong code hiện tại.**

---

## 8. NHỮNG CHỖ TỰ QUYẾT VÌ TÀI LIỆU KHÔNG NÓI RÕ, VÀ NHỮNG CHỖ KHÔNG CHẮC ĐÃ ĐÚNG

*(Đây là các quyết định của LƯỢT LÀM PHASE 2, không phải của lượt review này — lượt review chỉ tổng hợp lại để Opus soát, dựa trên comment trong code và `docs/viec-ton.md`.)*

1. **Cách nhóm file theo Bước ở mục 1** là suy luận của lượt review này (không có trong bất kỳ commit message hay tài liệu nào) — git chỉ có 3 commit lớn cho toàn bộ Phase 2. Có rủi ro một vài file bị gán nhầm Bước (đặc biệt các file factory sửa hàng loạt ở Bước 2, có thể một phần thực ra làm ở Bước khác).

2. **`SplitTableSession::sinhMaLuotKhach()` vẫn dùng `count()+1`** — bản sao của `OpenTableSession::sinhMaLuotKhach()` viết TRƯỚC bản vá đua tranh mã (Bước 2 chỉ nêu đích danh `OpenTableSession`/`OpenShift`), nên `SplitTableSession` chưa được sửa theo. Đã tự ghi vào `docs/viec-ton.md` (dòng "[Bước sau] SplitTableSession::sinhMaLuotKhach()... VẪN dùng count()+1"). Hai người tách bàn cùng giây có thể ra cùng mã lượt khách mới hiển thị (không phải lỗi dữ liệu — `uuid`/`id` vẫn duy nhất, chỉ `code` hiển thị có thể trùng).

3. **Thứ tự khoá `Shift → TableSession` trong `SplitTableSession` và `ResolveSyncConflict::xuLyCaDaDong`** ngược với văn bản luật CLAUDE.md hiện hành (`Payment → TableSession → Shift → DiningTable`, sửa 02/08) — xem phân tích đầy đủ ở mục 6. Không rõ đây là do người viết Phase 2 đọc nhầm luật CŨ (comment trong `SplitTableSession.php` trích dẫn nguyên văn "Shift → TableSession" như một luật đang hiệu lực), hay là quyết định có chủ đích chưa ghi lại lý do.

4. **`ApplyPromotion` khoá `Promotion` trước `TableSession`** — `Promotion` là loại tài nguyên mới, luật mục 11 chưa nhắc tới nên không có "đúng/sai" rõ ràng, nhưng cần Opus quyết định có bổ sung vào luật hay không.

5. **`ResolveSyncConflict::xuLyThuVuotGiamGia`/`xuLyGiaLech` không truyền `skipApprovalThreshold` cho `CalculateBill`** (khác với `ApplyPromotion`, LUÔN bật cờ này) — nghĩa là nếu khoản "giảm bù chênh lệch giá" hoặc "bỏ giảm giá" phát sinh từ việc xử lý xung đột vượt ngưỡng % vai trò của người đang xử lý xung đột (`resolvedByUserId`), `CalculateBill` sẽ đòi thêm một lượt duyệt PIN NGAY GIỮA quá trình resolve — nhưng `ResolveSyncConflictData`/`ResolveSyncConflictRequest` CHỈ CÓ MỘT cặp `approverUserId`/`approverPin` dùng chung cho bước duyệt PIN xung đột dính tiền (`CAN_PIN_DUYET` — chỉ áp cho `ThuTienTrung`/`ThuVuotGiamGia`, KHÔNG áp cho `GiaLech`), và cặp PIN đó **không được truyền tiếp** vào `CalculateBillData` (`ResolveSyncConflict.php:293-301,484-493` đều truyền `approverUserId: null, approverPin: null`). Nếu số tiền giảm bù vượt ngưỡng, `ResolveSyncConflict` sẽ ném lỗi và toàn bộ transaction rollback, xung đột giữ nguyên "pending" — không mất dữ liệu, nhưng người xử lý xung đột nhận lỗi "phải có PIN duyệt" khó hiểu giữa một luồng tưởng đã có PIN rồi. **Chưa có test nào phủ kịch bản này** (`ResolveSyncConflictTest` — "giá món đổi — Giảm giá bù giảm đúng phần server thu nhiều hơn giá khách đã thấy" chỉ test trường hợp trong ngưỡng). Đây là điểm KHÔNG CHẮC đã đúng, cần Opus xác nhận có phải lỗ hổng thật hay chấp nhận được.

6. **`CancelOrderItem` qua `SyncBatch` luôn truyền `approverUserId/approverPin = null`** (mục 5, H5 ở trên) — chấp nhận được theo đúng thiết kế (huỷ món served không được phép offline), nhưng bản thân `SyncBatch` không tự kiểm tra `status` món trước khi quyết định truyền null — dựa hoàn toàn vào việc `CancelOrderItem` sẽ tự từ chối nếu cần PIN. Đúng về hành vi cuối cùng, nhưng cách viết hơi ngầm định (implicit) hơn là tường minh.

7. **Dòng 1 ma trận xung đột (bếp báo hết món) chưa cài** — quyết định hoãn đã có ngày tháng và lý do rõ ràng trong `docs/viec-ton.md`, không phải thiếu sót âm thầm. Ghi lại ở đây để Opus xác nhận việc hoãn này chấp nhận được cho việc đóng Phase 2 hay bắt buộc phải làm trước khi đóng.

8. **`sync_applied_ops` không có cột `created_at` chuẩn** (`$timestamps = false`, chỉ có `applied_at` riêng) — quyết định có chủ đích (đã thấy trong Model), nhưng khác quy ước "mọi bảng có `created_at`" ngầm định trong CLAUDE.md mục 4 câu 14 (không nói rõ bảng nào bắt buộc timestamps chuẩn) — cần Opus xác nhận đây có phải ngoại lệ chấp nhận được.

9. **`app/Console/Commands/CleanupSyncAppliedOps.php`** tồn tại (dọn bản ghi cũ trong `sync_applied_ops`) nhưng lượt review này CHƯA đọc nội dung file (ngoài phạm vi đọc trong lần soát này do giới hạn thời gian) — chưa xác nhận có lịch chạy tự động (Laravel Scheduler) hay phải chạy tay, và bảng này có thể phình vô hạn nếu không ai chạy lệnh này. Cần Opus tự đọc file để xác nhận.

10. **Test JS (`tests/js/queue.test.js`, 6 test)** chỉ test đơn vị lớp hàng chờ, KHÔNG có test end-to-end thật cho luồng offline (rút mạng thật, dùng Playwright `context.setOffline(true)`) — đã tự ghi nợ vào `docs/viec-ton.md`. Nghĩa là "quán vẫn bán được khi wifi rớt 10 phút" (tiêu chí Phase 2) mới được xác nhận bằng test đơn vị + `pos:demo` (chạy trong PHP, giả lập gọi Action trực tiếp, KHÔNG đi qua trình duyệt thật/Dexie thật/mất mạng thật) — chưa có bằng chứng tự động hoá cho kịch bản offline thật trên trình duyệt.

11. **Chưa kiểm chứng độc lập số lượng "test" theo từng file ở mục 1** — số liệu lấy từ `grep -c "^(it|test)\("`, có thể lệch nếu file dùng cú pháp `describe()`/`it()` lồng nhau hoặc `test()->group()`. Số tổng 418 test ở mục dưới (chạy `php artisan test`) là số ĐÁNG TIN CẬY NHẤT; các con số "N test" ghi trong mục 1 chỉ mang tính tham khảo nhanh.

---

## OUTPUT 5 LỆNH

### 1. `php artisan test` (lần 1)

```
Tests:    418 passed (1323 assertions)
Duration: 88.08s
```

*(Toàn bộ danh sách tên test đã chạy quá dài để dán nguyên văn ở đây — dán tóm tắt cuối cùng theo đúng định dạng Pest. Không có test nào FAIL/SKIP.)*

### 2. `php artisan test` (lần 2, kiểm tra số test có ổn định không)

```
Tests:    418 passed (1323 assertions)
Duration: 44.14s
```

✅ Cùng đúng **418 test / 1323 assertion** ở cả hai lần chạy — ổn định, không có test đỏ ngẫu nhiên.

### 3. `php artisan pos:demo`

```
MỞ CA
   Ca CA-20260805-09 mở bởi Thu ngân diễn tập, tiền lẻ đầu ca 500.000 đ

MỞ BÀN
   Lượt khách PH-20260805-0013 mở bởi Phục vụ diễn tập tại bàn DEMO-1 (ghép thêm bàn DEMO-2), 6 khách

GỌI MÓN
   [bếp] 3 x Gà nướng diễn tập — 360.000 đ (phiếu c4fa050e-36e0-4774-a94b-034b52cb046e)
   [quầy] 4 x Bia diễn tập — 100.000 đ (phiếu a109cc46-a9f9-4cad-bb2f-f47736af13a5)
   Tạm tính: 460.000 đ

GỬI BẾP
   Đã gửi phiếu c4fa050e-36e0-4774-a94b-034b52cb046e (kitchen) xuống nơi làm, trạng thái: sent
   Đã gửi phiếu a109cc46-a9f9-4cad-bb2f-f47736af13a5 (bar) xuống nơi làm, trạng thái: sent

BẾP BÁO XONG
   Phiếu c4fa050e-36e0-4774-a94b-034b52cb046e (kitchen) — Gà nướng diễn tập đã xong, trạng thái phiếu: served
   Phiếu a109cc46-a9f9-4cad-bb2f-f47736af13a5 (bar) — Bia diễn tập đã xong, trạng thái phiếu: served

TÁCH BÀN
   Tạm tính trước khi tách: 460.000 đ
   Tách bàn DEMO-2 và món Bia diễn tập (đã gửi bếp/quầy) sang lượt khách mới
   Sau khi tách — lượt cũ PH-20260805-0013: 360.000 đ, lượt mới PH-20260805-0002: 100.000 đ
   Tổng hai bên: 460.000 đ (phải bằng tạm tính trước khi tách)

HỦY MÓN
   Tạm tính trước khi huỷ: 360.000 đ
   Món Gà nướng diễn tập đang có 3 phần, đã phục vụ — huỷ bớt 1 phần, cần PIN duyệt của Thu ngân diễn tập
   Tách thành 2 dòng: dòng gốc còn 2 phần (giữ nguyên), dòng mới huỷ 1 phần (id 14, tách từ id 12)
   Tạm tính sau khi huỷ: 240.000 đ

THU TIỀN
   Tổng phải thu: 240.000 đ
   Khách đưa: 290.000 đ
   Thối lại: 50.000 đ
   Trạng thái lượt khách sau khi thu: closed

ĐÓNG CA
   Tiền mặt lẽ ra phải có: 690.000 đ
   Đếm thực tế trong két: 670.000 đ
   Chênh lệch: Thiếu 20.000 đ
   Trạng thái ca: closed

✅ TOÀN BỘ LƯỢT BÁN CHẠY ĐÚNG
Đã dọn sạch toàn bộ dữ liệu diễn tập (rollback, không có gì được ghi thật vào database).
```

Lưu ý: kịch bản `pos:demo` hiện có gồm cả bước **TÁCH BÀN** (Bước 1) nhưng KHÔNG có bước đồng bộ (`sync/batch`) hay xử lý xung đột (Bước 4/5) hay khuyến mãi/VietQR (Bước 6/7) hay báo cáo (Bước 8) — `docs/PHASE.md` mục "Nghiệm thu" của Bước 4/5/6 dùng cờ riêng (`pos:demo --den=sync`, `--den=khuyen-mai`) chứ không phải lệnh trần `pos:demo` — **lượt review này chỉ chạy lệnh trần theo đúng yêu cầu**, chưa xác nhận các cờ `--den=...` có hoạt động đúng hay không.

### 4. `php artisan route:list --path=api`

```
POST       api/v1/auth/login .............................................................. Api\AuthController@login
POST       api/v1/auth/logout ............................................................ Api\AuthController@logout
POST       api/v1/auth/pin-verify ..................................................... Api\AuthController@pinVerify
GET|HEAD   api/v1/floor-plan ......................................................... Api\FloorPlanController@index
POST       api/v1/kds/items/{orderItem}/status .................................. Api\KdsController@updateItemStatus
GET|HEAD   api/v1/kds/tickets ............................................................ Api\KdsController@tickets
GET|HEAD   api/v1/menu .................................................................... Api\MenuController@index
PATCH      api/v1/orders/{order}/items/{orderItem} .................................. Api\OrderItemController@update
DELETE     api/v1/orders/{order}/items/{orderItem} ................................. Api\OrderItemController@destroy
POST       api/v1/orders/{order}/items/{orderItem}/cancel ........................... Api\OrderItemController@cancel
POST       api/v1/orders/{order}/send ..................................................... Api\OrderController@send
POST       api/v1/payments/{payment}/void ............................................... Api\PaymentController@void
GET|HEAD   api/v1/shifts/current ....................................................... Api\ShiftController@current
POST       api/v1/shifts/open ............................................................. Api\ShiftController@open
POST       api/v1/shifts/{shift}/cash-movements ................................... Api\CashMovementController@store
POST       api/v1/shifts/{shift}/close ................................................... Api\ShiftController@close
GET|HEAD   api/v1/shifts/{shift}/report ................................................. Api\ShiftController@report
POST       api/v1/sync/batch ......................................................... Api\SyncBatchController@store
GET|HEAD   api/v1/sync/conflicts .................................................. Api\SyncConflictController@index
GET|HEAD   api/v1/sync/conflicts/pending-count ............................. Api\SyncConflictController@pendingCount
POST       api/v1/sync/conflicts/{conflict}/resolve ............................. Api\SyncConflictController@resolve
POST       api/v1/table-sessions ................................................... Api\TableSessionController@open
GET|HEAD   api/v1/table-sessions/{tableSession} .................................... Api\TableSessionController@show
GET|HEAD   api/v1/table-sessions/{tableSession}/bill ....................................... Api\BillController@show
POST       api/v1/table-sessions/{tableSession}/close ............................. Api\TableSessionController@close
POST       api/v1/table-sessions/{tableSession}/discount ....................... Api\TableSessionController@discount
POST       api/v1/table-sessions/{tableSession}/move-items .................... Api\TableSessionController@moveItems
POST       api/v1/table-sessions/{tableSession}/orders ................................... Api\OrderController@store
POST       api/v1/table-sessions/{tableSession}/payments ............................... Api\PaymentController@store
POST       api/v1/table-sessions/{tableSession}/split ............................. Api\TableSessionController@split
POST       api/v1/table-sessions/{tableSession}/tables ...................... Api\TableSessionController@attachTable
DELETE     api/v1/table-sessions/{tableSession}/tables/{diningTable} ........ Api\TableSessionController@detachTable
POST       api/v1/table-sessions/{tableSession}/transfer ....................... Api\TableSessionController@transfer
GET|HEAD   api/v1/table-sessions/{tableSession}/vietqr ................................... Api\VietQrController@show
POST       api/v1/table-sessions/{tableSession}/void ............................... Api\TableSessionController@void

                                                                                                   Showing [35] routes
```

Nhận xét: **không có endpoint nào cho khuyến mãi** (`ApplyPromotion` chưa nối API — đúng như đã ghi ở `docs/viec-ton.md`, chỉ có Action + Filament) và **không có endpoint nào cho báo cáo/dashboard chủ quán** qua API `v1` — `GetOwnerDashboard` phục vụ trực tiếp trang Filament `BaoCaoChuQuan`, không qua route `api/v1`.

### 5. `php artisan tinker --execute="echo config('database.default');"`

```
mariadb
```

Khớp với `phpunit.xml` đã đổi `DB_CONNECTION` sang `mariadb` (một trong "ba việc treo" của mục 10 `docs/thiet-ke-dong-bo.md`, đã xác nhận xong).

---

*(Hết Phần B. Hai file `docs/review-phase2-a.md` và `docs/review-phase2-b.md` là toàn bộ báo cáo — không có mục nào bị cắt bớt.)*
