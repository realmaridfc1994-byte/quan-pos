# THIẾT KẾ CƠ CHẾ ĐỒNG BỘ — PHASE 2 BƯỚC 4

> Lưu tại `docs/thiet-ke-dong-bo.md`
> Thiết kế: Opus 5 · Ngày 04/08/2026 · Đã duyệt bảy quyết định gốc
> **Giữ thu tiền offline** (chủ dự án quyết ngày 04/08)

---

## 0. Nguyên tắc gốc — đọc trước mọi thứ khác

**Hàng chờ chứa THAO TÁC, không chứa dữ liệu.**

Máy POS không gửi lên *"lượt khách bàn 5 có tổng 380.000"*. Nó gửi *"mở bàn 5 lúc 19:02"*, *"gọi 3 lon Tiger lúc 19:05"*, *"thu 380.000 tiền mặt lúc 20:30"*.

Server chạy lại từng thao tác **qua đúng các Action đã có từ Phase 1**. `SyncBatch` không được viết một dòng logic nghiệp vụ nào.

Ba hệ quả:

1. **43 bất biến vẫn do các Action cũ giữ.** Nếu `RecordPayment` chặn thu quá số còn thiếu, thì đồng bộ cũng bị chặn y hệt.
2. **Đồng bộ chỉ làm hai việc**: xếp thứ tự thao tác, và xử lý va chạm.
3. Sửa một luật nghiệp vụ ở Action là đồng bộ tự theo. Không có hai nơi phải sửa.

> **Luật cho người viết code:** nếu bạn thấy mình đang viết `->update()` hay `->create()` trực tiếp trong `SyncBatch`, dừng lại. Việc đó phải nằm trong một Action.

---

## 1. Thao tác nào được phép offline

| Thao tác | Offline | Lý do |
|---|---|---|
| `open_session` — mở bàn | ✅ | |
| `attach_table` / `detach_table` — ghép, nhả bàn | ✅ | |
| `place_order` — gọi món (kèm dòng món và tùy chọn) | ✅ | |
| `send_to_kitchen` — gửi bếp | ✅ | Máy in trong mạng nội bộ vẫn tới được |
| `cancel_order_item` — hủy món **chưa gửi bếp** | ✅ | Không cần PIN duyệt |
| `record_payment` — thu tiền **mặt** | ✅ | Có điều kiện, xem mục 1.1 |
| `close_session` — đóng bàn | ✅ | |
| Hủy món **đã bưng ra** | ❌ | Cần PIN, cần khoá tạm 15 phút phía server |
| Giảm giá | ❌ | Ngưỡng 20% tính trên tạm tính, có thể đã đổi ở máy khác |
| Hủy phiếu thu, hủy bill | ❌ | Cần PIN |
| Thu chuyển khoản, QR | ❌ | Cần xác nhận từ ngân hàng |
| Mở ca, đóng ca | ❌ | |

Máy POS bấm vào việc bị cấm khi offline → hiện ngay: *"Cần có mạng để làm việc này. Đang mất kết nối."*

### 1.1. Điều kiện thu tiền mặt offline

Ba điều kiện, kiểm ở **máy POS** trước khi cho bấm:

1. Lượt khách này chưa có thao tác thu tiền nào trong hàng chờ của **máy khác mà máy này biết**
2. Số tiền thu ≤ tổng phải trả theo dữ liệu cục bộ
3. Chỉ tiền mặt

Điều kiện 1 chỉ chặn được khi hai máy còn thấy nhau. Hai máy cô lập hoàn toàn vẫn có thể cùng thu — đó là dòng số 4 của ma trận, và nó **luôn cần người quyết**.

---

## 2. Sơ đồ luồng

```
MÁY POS (offline)
   │
   │ nhân viên bấm → ghi vào Dexie + đẩy vào HÀNG CHỜ
   │ mỗi thao tác có: op_uuid, type, occurred_at, depends_on, payload
   │ tem bếp in ngay qua máy in LAN
   ▼
[có mạng lại]
   │
   │ gom tối đa 200 thao tác → POST /api/v1/sync/batch
   ▼
SERVER
   │
   ├─ 1. Giành khoá toàn cục "sync:batch"  ── không giành được → 429, POS thử lại
   │
   ├─ 2. Xếp thứ tự: đồ thị phụ thuộc → occurred_at → vị trí trong gói
   │
   ├─ 3. Với TỪNG thao tác, theo thứ tự:
   │      ├─ Đã áp dụng rồi (uuid trùng)? ──────────→ duplicate
   │      ├─ Cha bị conflict/rejected? ─────────────→ deferred
   │      ├─ Kiểm va chạm theo ma trận mục 5
   │      │    ├─ Có va chạm ──→ ghi sync_conflicts ─→ conflict
   │      │    └─ Không ──┐
   │      └───────────────┴─ gọi Action Phase 1 trong DB::transaction
   │                          ├─ thành công ────────→ applied
   │                          └─ DomainException ───→ rejected
   │
   ├─ 4. Nhả khoá
   ▼
TRẢ VỀ: kết quả từng thao tác + giờ server
   │
MÁY POS xử lý theo trạng thái (mục 3.3)
   │
   └─ có conflict → chấm đỏ trên màn hình POS
                    → chủ quán xử lý ở màn hình Bước 5
```

---

## 3. Hợp đồng `POST /api/v1/sync/batch`

### 3.1. Gửi lên

```json
{
  "device_id": "pos-01",
  "batch_uuid": "3f8a...",
  "client_time": "2026-08-04T20:31:07+07:00",
  "operations": [
    {
      "op_uuid": "a1b2...",
      "type": "open_session",
      "occurred_at": "2026-08-04T19:02:11+07:00",
      "depends_on": [],
      "payload": {
        "uuid": "a1b2...",
        "dining_table_ids": [5],
        "primary_dining_table_id": 5,
        "guest_count": 4,
        "shift_id": 12
      }
    },
    {
      "op_uuid": "c3d4...",
      "type": "place_order",
      "occurred_at": "2026-08-04T19:05:40+07:00",
      "depends_on": ["a1b2..."],
      "payload": {
        "uuid": "c3d4...",
        "table_session_uuid": "a1b2...",
        "items": [
          {
            "uuid": "e5f6...",
            "product_id": 12,
            "product_variant_id": 34,
            "quantity": 3,
            "note": null,
            "client_unit_price": 25000,
            "options": [
              { "uuid": "g7h8...", "option_id": 7, "client_price_delta": 0 }
            ]
          }
        ]
      }
    }
  ]
}
```

**Bốn điểm bắt buộc:**

- **Tham chiếu bằng `uuid`, không bằng `id`.** Máy POS offline không biết `id` của lượt khách nó vừa tạo. Server tự tra `id` từ `uuid`.
- **`client_unit_price` chỉ để so sánh.** Server luôn tự tính lại từ thực đơn của mình. Lệch → dòng 8 ma trận. Nếu tin giá từ máy POS thì ai chạm được máy tính bảng đều sửa được giá bill.
- **`shift_id`** là ca máy POS biết lúc bấm. Server kiểm lại, ca đã đóng → dòng 7 ma trận.
- **`occurred_at`** giờ máy POS. Chỉ đáng tin **trong cùng một máy**, xem mục 4.

Tối đa **200 thao tác** một gói. Nhiều hơn thì chia nhiều gói.

### 3.2. Trả về

```json
{
  "batch_uuid": "3f8a...",
  "server_time": "2026-08-04T20:31:09+07:00",
  "summary": { "applied": 12, "duplicate": 2, "conflict": 1, "deferred": 3, "rejected": 0 },
  "results": [
    {
      "op_uuid": "a1b2...",
      "status": "applied",
      "server_ids": { "table_session_id": 148, "code": "PH-20260804-0148" }
    },
    {
      "op_uuid": "x9y8...",
      "status": "conflict",
      "conflict_id": 27,
      "conflict_kind": "thu_tien_trung",
      "message": "Bàn B05 đã được máy POS số 1 thu đủ tiền lúc 20:12. Phiếu thu này chờ chủ quán xem lại."
    },
    {
      "op_uuid": "z1z2...",
      "status": "deferred",
      "reason": "Chờ thao tác a1b2... được xử lý xong"
    }
  ]
}
```

### 3.3. Năm trạng thái và máy POS phải làm gì

| Trạng thái | Nghĩa | Máy POS làm gì |
|---|---|---|
| `applied` | Đã áp dụng thành công | Xoá khỏi hàng chờ, cập nhật `id` và `code` thật vào Dexie |
| `duplicate` | Đã áp dụng ở lần gửi trước | Xoá khỏi hàng chờ, cập nhật `id` từ `server_ids` |
| `conflict` | Cần người quyết | Xoá khỏi hàng chờ, tăng bộ đếm cảnh báo, hiện chấm đỏ |
| `deferred` | Cha chưa xong, hoãn lại | **GIỮ trong hàng chờ**, gửi lại ở gói sau |
| `rejected` | Sai dữ liệu, không bao giờ áp dụng được | Xoá khỏi hàng chờ, hiện lỗi tiếng Việt cho nhân viên |

`deferred` là trạng thái duy nhất máy POS giữ lại. Ba trạng thái kia đều xoá — nếu không, hàng chờ phình mãi.

**Chống kẹt vô hạn:** thao tác `deferred` quá **5 lần** thì chuyển thành `conflict`, ghi vào danh sách chờ người quyết. Không để một thao tác hỏng nằm trong hàng chờ vĩnh viễn.

### 3.3.1. `duplicate` được xác định bằng HAI lớp khác nhau, cùng tồn tại

**Lớp 1 — uuid nghiệp vụ trên từng bảng.** `table_sessions.uuid`, `orders.uuid`, `payments.uuid`... Các Action Phase 1 (`OpenTableSession`, `PlaceOrder`, `RecordPayment`) đã tự tra uuid này trước khi tạo, không cần SyncBatch làm gì thêm.

**Lớp 2 — `op_uuid` ở tầng đồng bộ, bảng `sync_applied_ops`.** Không phải mọi loại thao tác đều có cột uuid nghiệp vụ riêng để tra — `attach_table`, `detach_table`, `send_to_kitchen`, `close_session`, huỷ món **toàn bộ** không có. Nếu chỉ dựa vào Lớp 1, gửi lại một trong năm thao tác này (mạng rớt đúng lúc server trả kết quả) sẽ bị Action gốc từ chối lần hai — VD `SendToKitchen` báo "phiếu có món không khớp nơi làm" hoặc tương tự đã gửi rồi — máy POS hiện lỗi đỏ, nhân viên bấm gửi lại, bếp nhận tem hai lần thật.

Vì vậy `SyncBatch` tra `op_uuid` trong `sync_applied_ops` **TRƯỚC** khi xử lý bất kỳ thao tác nào, áp dụng cho **mọi loại thao tác** — không riêng năm loại thiếu uuid nghiệp vụ. Có rồi → trả `duplicate` ngay kèm `result_payload` đã lưu, không gọi lại Action. Ghi vào bảng ngay sau khi Action chạy thành công (trạng thái `applied`) — không ghi cho `conflict`, `deferred`, `rejected`.

Xung đột (`sync_conflicts`) có lớp chống trùng riêng của nó: cột `op_uuid` đã UNIQUE (`uq_sync_conflicts_op`) — gửi lại một thao tác đã ghi xung đột trả về đúng `conflict_id` cũ, trạng thái vẫn là `conflict` (không phải `duplicate`, vì không có gì được "tạo trùng" — chỉ là chưa ai xử lý xong).

**Tóm lại: ba cơ chế, ba tầng, không thay thế nhau** — uuid nghiệp vụ (dữ liệu), `op_uuid` trong `sync_applied_ops` (thao tác đã áp dụng), `op_uuid` trong `sync_conflicts` (thao tác đang chờ người).

---

## 4. Xếp thứ tự thao tác

### 4.1. Thuật toán

1. **Sắp theo đồ thị phụ thuộc** (`depends_on`). Cha luôn trước con.
2. Cùng bậc → sắp theo `occurred_at` tăng dần.
3. Trùng `occurred_at` → theo thứ tự trong mảng `operations`.
4. Phát hiện vòng lặp phụ thuộc → toàn bộ vòng đó thành `rejected`, ghi log. Đây là lỗi máy POS, không phải va chạm.

### 4.2. Đồng hồ máy POS không đáng tin giữa các máy

Máy tính bảng chạy pin, đồng hồ lệch vài phút là chuyện thường. Nên:

- **Trong cùng một máy**: `occurred_at` đáng tin. Dùng để xếp thứ tự.
- **Giữa hai máy khác nhau**: `occurred_at` **không** đáng tin. Ai đến server trước thì thắng.

Nghe có vẻ không công bằng — máy A mở bàn 19:00, máy B mở 19:30, nhưng B có mạng trước nên B thắng. Chấp nhận được vì: đây luôn là tình huống **conflict**, và người quyết sẽ nhìn cả hai `occurred_at` để xử. Máy không tự chọn.

Server ghi **cả hai** mốc thời gian vào `sync_conflicts`: `occurred_at` (máy POS khai) và `received_at` (server nhận). Người quyết cần cả hai.

---

## 5. Ma trận xử lý va chạm

Mười dòng. Bảy dòng bạn nêu, ba dòng tôi thêm vì chúng sẽ xảy ra.

| # | Tình huống | Server làm gì tự động | Cần người? |
|---|---|---|---|
| 1 | Bếp báo hết món, POS offline vẫn gọi món đó | **Nhận món**, đánh dấu `mon_da_het` | ✅ |
| 2 | Hai máy cùng offline, cùng mở bàn 5 | Máy đến trước giữ bàn, mọi thao tác con của nó chạy bình thường. **Cả cụm** (open_session + thao tác con) của máy sau gom vào MỘT bản ghi conflict — `auto_action = 'khong_lam_gi'`, không tự tạo gì cho máy sau (xem 5.0) | ✅ — GẤP |
| 3 | Hai máy cùng gọi món vào bàn 5 | **Nhận cả hai**, cộng dồn | ❌ tự động |
| 4 | Hai máy cùng thu tiền một lượt khách | Phiếu đầu nhận. Phiếu sau **không tạo dòng nào** trong `payments` | ✅ |
| 5 | POS offline thu tiền, máy online đã giảm giá bàn đó | Thu ≤ tổng mới → nhận. Vượt → không tạo phiếu | ✅ nếu vượt |
| 6 | Gọi món vào lượt khách máy khác đã đóng | **Không** nhét vào lượt cũ — thao tác này và mọi thao tác con của nó gom vào một bản ghi conflict (xem 5.0) | ✅ |
| 7 | Phiếu thu thuộc ca đã đóng ở server | Đề xuất gán sang ca đang mở. Không có ca mở → chờ | ✅ |
| 8 | Giá món đổi giữa lúc gọi và lúc đồng bộ | **Ghi theo giá server**, đánh dấu `gia_lech` | ✅ |
| 9 | Gọi món vào lượt khách đã bị hủy | Không nhận — thao tác này và mọi thao tác con của nó gom vào một bản ghi conflict (xem 5.0) | ✅ |
| 10 | Thiếu thao tác gốc trong hàng chờ | `deferred` 5 lần rồi thành `conflict` — mọi thao tác con của nó gom vào cùng bản ghi (xem 5.0) | ✅ |

### 5.0. Xử lý theo CỤM — khi thao tác GỐC bị conflict

Áp dụng cho dòng 2, 6, 9, 10 — mọi trường hợp "gốc hỏng thì con theo". Quyết định ngày 04/08, thay cho phương án ban đầu (tạo lượt khách không chiếm bàn) vì phương án đó để lại dữ liệu vi phạm B2 nằm trong bảng thật — lượt khách không có bàn không hiện trên sơ đồ, nhân viên không tìm thấy.

Khi một thao tác GỐC (ví dụ `open_session`) bị phát hiện va chạm, MỌI thao tác CON phụ thuộc nó — trực tiếp (`depends_on` chứa uuid gốc) và gián tiếp (con của con, đệ quy theo đồ thị phụ thuộc đã xếp ở mục 4) — **KHÔNG được tạo bản ghi `sync_conflicts` riêng**. Tất cả gom vào ĐÚNG MỘT bản ghi của thao tác gốc:

- `sync_conflicts.payload` chứa **CẢ CỤM**: thao tác gốc + toàn bộ thao tác con, giữ nguyên thứ tự đã xếp ở mục 4 và quan hệ `depends_on` giữa chúng — đủ để chạy lại đúng thứ tự khi người quyết chọn phương án.
- Mỗi thao tác con trả về trong `results` với `status: "conflict"` và **ĐÚNG `conflict_id` của thao tác gốc** — không tạo id mới. Máy POS xoá cả cụm khỏi hàng chờ bằng một chấm đỏ, không phải nhiều chấm đỏ rời rạc cho từng thao tác con.
- Khi người quyết chọn một phương án ở màn hình Bước 5, server chạy lại **CẢ CỤM** theo đúng thứ tự đã lưu, qua đúng các Action Phase 1 — không chạy riêng lẻ từng thao tác con.

**Không có Action mới, không sửa `OpenTableSession`.** Cơ chế gom cụm nằm ở tầng `SyncBatch`/`sync_conflicts` (xếp thao tác vào cùng một bản ghi), không phải ở tầng nghiệp vụ.

**Riêng dòng 2:**
- `auto_action = 'khong_lam_gi'` — server không tạo gì cho máy thua ngay lúc phát hiện.
- Lựa chọn **"Gộp"**: chạy lại các thao tác con (đã gom trong payload) vào lượt khách của máy THẮNG.
- Lựa chọn **"Tách"**: người quyết chỉ định bàn khác cho lượt khách máy thua, server chạy lại **cả cụm từ `open_session`** với bàn mới đó.

**Xung đột dòng 2 là GẤP (`is_urgent = 1`, xem mục 6):** tem đã in offline, bếp đang nấu thật, nhưng món chưa tồn tại trong hệ thống và bàn đó không thanh toán được cho tới khi xử lý xong. Màn hình Bước 5 hiện nhóm gấp lên trên. Việc gán `is_urgent` cho các dòng khác (nếu có dòng nào cũng chặn đường thanh toán) xác định lúc cài đặt `SyncBatch`, theo đúng quy tắc chung ở mục 6 — tài liệu này chỉ chốt chắc chắn dòng 2.

### 5.1. Giải thích từng dòng và câu thông báo tiếng Việt

**Dòng 1 — Bếp báo hết món.** Món đã nấu và bưng ra rồi (tem in offline, bếp làm bình thường). Từ chối là làm mất một món đã bán thật. Nhận rồi để người xem.

> *"Máy POS số 2 gọi 2 phần Lẩu gà lá é lúc 19:40 khi mất mạng. Bếp đã báo hết món này lúc 19:15. Món vẫn được ghi vào bàn B05 — kiểm tra xem bếp có làm được không."*
> Lựa chọn: **Giữ món** (bếp làm được) · **Hủy món** (ghi lý do "hết hàng")

**Dòng 2 — Hai máy cùng mở bàn 5.** B2 giữ nguyên, KHÔNG có ngoại lệ mới — không tạo lượt khách nào thiếu bàn trong bảng thật. Toàn bộ cụm thao tác của máy thua (mở bàn + mọi món đã gọi sau đó) gom vào một bản ghi `sync_conflicts` chờ người quyết, xem 5.0.

> *"Hai máy POS cùng mở bàn B05 khi mất mạng. Máy 1 lúc 19:02 (4 khách, đã gọi 5 món). Máy 2 lúc 19:04 (2 khách, đã gọi 2 món). Bàn B05 đang thuộc lượt khách của máy 1. Tem bếp của máy 2 đã in — bếp có thể đang nấu."*
> Lựa chọn: **Gộp** (chạy lại 2 món của máy 2 vào lượt khách máy 1) · **Tách** (chọn bàn khác cho máy 2, server chạy lại từ đầu: mở lượt khách mới + 2 món đó)

**Dòng 3 — Cùng gọi món.** Không phải va chạm. Gọi món là việc **cộng thêm**, hai máy cộng vào cùng một bàn là chuyện bình thường ở quán đông. Nhận cả hai, cộng dồn, không hỏi ai.

**Dòng 4 — Cùng thu tiền.** Quan trọng nhất. Phiếu thứ hai **tuyệt đối không tạo dòng nào** trong `payments` — nếu tạo phiếu "tạm" thì `paid_amount` sai ngay lập tức và mọi ràng buộc T bị phá.

> *"Máy POS số 2 thu 380.000 tiền mặt bàn B05 lúc 20:15 khi mất mạng. Nhưng bàn B05 đã được máy POS số 1 thu đủ 380.000 lúc 20:12. Kiểm tra két: có thừa 380.000 không?"*
> Lựa chọn: **Két không thừa** (thu trùng, bỏ phiếu này) · **Két có thừa** (khách trả hai lần thật, ghi nhận rồi hoàn lại)

Chọn "két có thừa" → tạo phiếu thu thật + một khoản chi ra để hoàn lại, cả hai trong một giao dịch.

**Dòng 5 — Thu offline, giảm giá online.** Bill 500.000, POS offline thu 500.000, nhưng máy online đã giảm còn 400.000. Thu 500.000 vào bill 400.000 là vi phạm luật "tổng không được xuống dưới đã thu" mà `CalculateBill` đang giữ.

> *"Máy POS số 2 thu 500.000 bàn B05 lúc 20:15 khi mất mạng. Trong lúc đó bàn này đã được giảm giá còn 400.000. Khách đã đưa 500.000 — thừa 100.000."*
> Lựa chọn: **Thu 400.000, hoàn 100.000** · **Bỏ giảm giá, thu đủ 500.000**

**Dòng 6 — Gọi món vào lượt đã đóng.** Tuyệt đối không nhét vào lượt cũ: lượt đó đã thu tiền, đã chốt, thêm món vào là phá số liệu đã kết sổ.

> *"Máy POS số 2 gọi 3 món vào bàn B05 lúc 20:20 khi mất mạng. Nhưng lượt khách PH-20260804-0148 ở bàn đó đã thanh toán xong lúc 20:10."*
> Lựa chọn: **Mở lượt khách mới** (khách mới ngồi vào) · **Hủy các món này** (gọi nhầm)

**Dòng 7 — Phiếu thu thuộc ca đã đóng.** Đúng tiền lệ `VoidPayment`: tiền vào két **hôm nay** thì phải thuộc ca hôm nay.

> *"Máy POS số 2 thu 380.000 tiền mặt lúc 22:50 khi mất mạng, thuộc ca CA-20260804-01. Ca đó đã đóng lúc 23:00. Tiền này nên tính vào ca đang mở CA-20260805-01?"*
> Lựa chọn: **Tính vào ca đang mở** · **Xem lại sau** (giữ chờ)

Không có ca nào đang mở → nằm chờ, không ép.

**Dòng 8 — Giá món đổi.** Server ghi theo giá **của mình**, luôn luôn. Nhưng phiếu tạm tính đã in offline mang giá cũ, khách có thể đã trả theo giá đó.

> *"Máy POS số 2 gọi 3 lon Tiger lúc 19:40 với giá 25.000/lon. Giá hiện tại là 27.000/lon. Đã ghi theo giá 27.000 — chênh 6.000. Phiếu tạm tính in ra lúc đó mang giá cũ."*
> Lựa chọn: **Giữ giá mới** (đúng bảng giá) · **Giảm giá 6.000** (giữ đúng phiếu đã đưa khách)

**Dòng 9 — Lượt khách đã hủy.** Giống dòng 6, nhưng lượt bị hủy chứ không phải đã thanh toán.

**Dòng 10 — Thiếu thao tác gốc.** Máy POS gửi "gọi món vào lượt a1b2" nhưng thao tác mở lượt a1b2 không có trong gói và cũng không có trên server — có thể máy POS mất dữ liệu, hoặc gói bị chia sai.

> *"Máy POS số 2 gửi lên 3 món cho một lượt khách không tìm thấy trong hệ thống. Có thể dữ liệu trên máy đó bị mất một phần."*
> Lựa chọn: **Tạo lượt khách mới** cho các món này · **Bỏ qua**

---

## 6. Bảng `sync_conflicts`

```sql
CREATE TABLE sync_conflicts (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    op_uuid             CHAR(36) COLLATE ascii_bin NOT NULL,
    batch_uuid          CHAR(36) COLLATE ascii_bin NOT NULL,
    device_id           VARCHAR(50) NOT NULL,

    op_type             VARCHAR(40) NOT NULL COMMENT 'open_session, place_order, record_payment...',
    conflict_kind       VARCHAR(40) NOT NULL COMMENT '10 loại ở ma trận mục 5',
    is_urgent           TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Bật cho xung đột CHẶN ĐƯỜNG THANH TOÁN — ví dụ dòng 2: tem đã in, bếp đang nấu, bàn chưa thanh toán được cho tới khi xử lý xong. Màn hình Bước 5 hiện nhóm này lên trên (xem 5.0)',

    occurred_at         DATETIME NOT NULL COMMENT 'Giờ máy POS khai',
    received_at         DATETIME NOT NULL COMMENT 'Giờ server nhận',

    payload             JSON NOT NULL COMMENT 'Thao tác gốc, đủ để áp dụng lại. Với dòng 2/6/9/10 (xử lý theo CỤM, xem 5.0): chứa CẢ CỤM — thao tác gốc + toàn bộ thao tác con, giữ nguyên thứ tự và depends_on',
    server_state        JSON NOT NULL COMMENT 'Trạng thái server lúc phát hiện',
    auto_action         VARCHAR(40) NULL COMMENT 'Server đã tự làm gì: nhan_mon, khong_lam_gi',

    message_vi          TEXT NOT NULL COMMENT 'Câu giải thích cho chủ quán',
    options             JSON NOT NULL COMMENT 'Các lựa chọn + hậu quả từng cái',

    table_session_id    BIGINT UNSIGNED NULL,
    status              ENUM('pending','resolved','dismissed') NOT NULL DEFAULT 'pending',
    resolution          VARCHAR(40) NULL,
    resolution_note     TEXT NULL,
    resolved_by_user_id BIGINT UNSIGNED NULL,
    resolved_at         DATETIME NULL,

    created_at          TIMESTAMP NULL,
    updated_at          TIMESTAMP NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uq_sync_conflicts_op (op_uuid),
    KEY idx_sync_conflicts_pending (status, is_urgent, created_at),
    KEY idx_sync_conflicts_session (table_session_id),
    CONSTRAINT fk_sync_conflicts_session FOREIGN KEY (table_session_id)
        REFERENCES table_sessions (id),
    CONSTRAINT fk_sync_conflicts_user FOREIGN KEY (resolved_by_user_id)
        REFERENCES users (id),
    CONSTRAINT ck_sync_conflicts_resolved CHECK (
        status = 'pending'
     OR (resolved_at IS NOT NULL AND resolved_by_user_id IS NOT NULL
         AND resolution IS NOT NULL)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Năm điểm:**

- `uq_sync_conflicts_op` — gửi lại cùng thao tác không tạo hai bản ghi chờ. Với xung đột theo CỤM (5.0), chỉ THAO TÁC GỐC có một dòng ở đây — thao tác con không tự tạo dòng riêng, chỉ được trả về đúng `conflict_id` của gốc.
- `ck_sync_conflicts_resolved` — đã xử lý thì bắt buộc có ai, lúc nào, chọn gì. Cùng khuôn với luật "không xóa cứng, hủy phải ghi ba thông tin".
- `payload` đủ để **áp dụng lại** khi người chọn. Không lưu tham chiếu tới hàng chờ máy POS — máy đó có thể đã xóa. Với xung đột theo cụm, đủ để chạy lại CẢ CỤM đúng thứ tự, không chỉ riêng thao tác gốc.
- `auto_action` ghi việc server đã tự làm. Bắt buộc, để không bao giờ có chuyện tự chọn rồi im lặng.
- `is_urgent` đánh dấu xung đột chặn đường thanh toán (VD dòng 2) để màn hình Bước 5 hiện nhóm gấp lên trên.

### 6.1. Vòng đời

```
pending ──→ resolved   (người chọn một phương án, áp dụng)
        └─→ dismissed  (người xem và quyết định không làm gì)
```

Cả hai đều ghi `resolved_by_user_id`, `resolved_at`, `resolution_note`. `dismissed` không phải "bỏ qua" — nó là một quyết định có người chịu trách nhiệm.

### 6.2. Chặn đóng ca

`CloseShift` thêm một điều kiện: còn `sync_conflicts` nào `pending` liên quan tới ca này → **chặn**, thông báo nêu rõ còn mấy việc và ở bàn nào.

Lý do: xung đột chưa xử lý nghĩa là số tiền chưa chắc đúng. Đóng ca lúc đó là chốt một con số sai, và C5 nói con số đã chốt thì không sửa được nữa.

---

## 7. Chiến lược khoá phía server

### 7.1. Khoá toàn cục cho đồng bộ

```php
Cache::lock('sync:batch', 120)->block(5, function () { ... });
```

Mỗi lúc **một gói** trên toàn hệ thống. Không giành được trong 5 giây → trả HTTP 429, máy POS thử lại sau 10 giây.

Vì sao chấp nhận được: quán 15 bàn, 2–3 máy, một gói 200 thao tác chạy dưới 2 giây. Xác suất hai máy cùng đồng bộ là thấp, và chờ 10 giây không ai thấy.

Đổi lại: xoá bỏ **toàn bộ** nhóm va chạm giữa hai gói cùng chạy. Đây là chỗ đổi một chút tốc độ lấy rất nhiều đơn giản — và đơn giản là thứ giữ cho tiền không sai.

### 7.2. Khoá trong từng thao tác

Mỗi thao tác chạy trong `DB::transaction` **riêng**, gọi Action Phase 1. Các Action đó đã có khoá đúng theo luật `CLAUDE.md` mục 11:

```
Payment → TableSession → Shift → DiningTable
```

`SyncBatch` **không tự khoá gì thêm**. Mọi khoá đã nằm trong Action.

### 7.3. Vì sao mỗi thao tác một giao dịch riêng

Nếu bọc cả gói trong một giao dịch, một thao tác hỏng là mất luôn 199 cái đã thành công. Với gói chứa 10 phút bán hàng, đó là mất 10 phút.

Mỗi thao tác một giao dịch: thao tác 47 hỏng thì 1–46 vẫn giữ nguyên, 48 trở đi vẫn chạy tiếp nếu không phụ thuộc.

Bù lại: gói có thể áp dụng **một phần**. Đó là lý do máy POS phải xử lý từng thao tác theo trạng thái riêng, không xử lý theo cả gói.

---

## 8. Trường hợp KHÔNG tự giải quyết được

Mười trường hợp, mỗi cái đều đưa vào `sync_conflicts` chờ người. Server **không bao giờ tự chọn** ở những chỗ này:

| # | Trường hợp | Vì sao máy không quyết được |
|---|---|---|
| 1 | Hai máy cùng thu tiền một bàn | Chỉ người đếm két mới biết khách trả một hay hai lần |
| 2 | Thu tiền nhiều hơn tổng sau khi đã giảm giá | Phải quyết hoàn lại bao nhiêu, hay bỏ giảm giá |
| 3 | Hai máy cùng mở một bàn | Không biết đó là hai nhóm khách hay một nhóm bị mở hai lần |
| 4 | Gọi món vào lượt khách đã đóng | Không biết khách mới hay gọi nhầm |
| 5 | Gọi món vào lượt khách đã hủy | Như trên |
| 6 | Bếp báo hết món nhưng POS offline đã gọi | Chỉ bếp biết còn làm được không |
| 7 | Giá món đổi giữa lúc gọi và lúc đồng bộ | Phiếu đã đưa khách mang giá cũ — quyết định kinh doanh |
| 8 | Phiếu thu thuộc ca đã đóng | Chuyển ca là quyết định kế toán |
| 9 | Thiếu thao tác gốc | Không biết dữ liệu mất hay gói chia sai |
| 10 | Thao tác hoãn quá 5 lần | Đã hết cách tự động |

**Điểm chung:** mọi trường hợp đều là chỗ máy phải **đoán ý người** hoặc **đoán sự thật ngoài đời** (két có thừa tiền không, bếp còn làm được không). Máy không đoán. Máy hỏi.

Một lần hệ thống âm thầm chọn sai về tiền là chủ quán mất niềm tin vĩnh viễn — và quay lại sổ giấy. Bảng `sync_conflicts` và màn hình Bước 5 là phần đắt nhưng không được cắt.

---

## 9. Bàn giao cho Sonnet

Prompt trong hướng dẫn Phase 2 Bước 4 vẫn dùng được, thêm bốn ràng buộc:

```
BỔ SUNG cho prompt Bước 4:

1. SyncBatch KHÔNG được viết logic nghiệp vụ. Mọi thao tác phải gọi đúng
   Action đã có ở Phase 1. Thấy mình viết ->create() hay ->update() trực
   tiếp trong SyncBatch thì DỪNG và báo.

2. Mỗi thao tác một DB::transaction riêng, KHÔNG bọc cả gói.

3. Khoá toàn cục Cache::lock('sync:batch', 120), block 5 giây, không được
   thì trả 429.

4. Với mỗi dòng trong ma trận mục 5, viết MỘT test. Không bỏ dòng nào.
   Dòng nào chưa test được thì ghi rõ lý do vào docs/viec-ton.md.
   Test phải khẳng định cả hai điều: server làm đúng việc tự động, VÀ
   bản ghi sync_conflicts sinh ra đúng loại với câu thông báo tiếng Việt.
```

---

## 10. Việc còn treo trước khi bắt đầu Bước 4

- [ ] Xác nhận đã sửa `now()` → `opened_at` trong hai hàm sinh mã *(chủ dự án báo đã xong 04/08)*
- [ ] Chạy `./vendor/bin/pest` **năm lần liên tiếp**, cả năm xanh cùng một số test
- [ ] `phpunit.xml` đã đổi `DB_CONNECTION` sang `mariadb`, và không test nào đổi kết quả sau khi đổi
- [ ] Bước 3 xong: rút dây mạng, gọi 3 món, gửi bếp — tem vẫn in ra

Ba việc đầu là dọn dẹp còn sót từ Bước 2. Việc thứ tư là điều kiện cần — không có hàng chờ thì không có gì để đồng bộ.
