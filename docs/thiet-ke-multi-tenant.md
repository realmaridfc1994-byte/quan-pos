# KIẾN TRÚC SAAS — BẢN 2

**Thay thế:** `docs/thiet-ke-multi-tenant.md` (bản 1, 13/08/2026) ở mục 0.5, 6.2 và 8. Toàn bộ mục 2 (so sánh phương án), mục 5 (53 điểm rò rỉ) và mục 7 (28 test) của bản 1 **giữ nguyên hiệu lực** và không lặp lại ở đây.

| | |
|---|---|
| Ngày viết | 13/08/2026 |
| Trạng thái | CHỜ DUYỆT |
| Điều kiện mở khoá | Duyệt bằng văn bản. Trước đó: không migration, không migration nháp |
| Cái gì đổi so với bản 1 | Bản 1 nói "làm Bước 1, dừng Bước 2–8". Bản này nói: **Bước 3 phải làm ngay bây giờ**, và thêm bốn quyết định nữa. Phần còn lại vẫn dừng |

---

## 1. VÌ SAO PHẢI CÓ BẢN 2

Bản 1 đặt câu hỏi đúng ("phương án nào?") và trả lời đúng ("P4 — mỗi quán một bản cài đặt"). Nhưng nó không đặt câu hỏi thứ hai, mà anh vừa hỏi:

> *Trong số việc đang dừng, có việc nào **rẻ hôm nay, đắt ngày mai** không?*

Có. **Năm việc.** Bản 1 xếp chúng chung vào khối "Bước 2–8, tạm dừng" — nhưng chúng không cùng một loại. Bảng dưới đây là toàn bộ nội dung của bản 2:

| Việc | Giá làm hôm nay | Giá làm ngày mở khoá SaaS | Chênh |
|---|---|---|---|
| **A. Kết nối có tên `tenant`** | ~1 ngày, hành vi không đổi | Bản 1 tự gọi là *"bước rủi ro cao nhất của cả dự án"* — chạm 100% điểm truy cập dữ liệu | **Rất lớn** |
| **B. Khoá cache / idempotency / Sanctum theo quán** | ~0.5 ngày | Rà tay mọi khoá cache viết trong lúc chờ, không sót cái nào | Lớn |
| **C. Mã quán trong URL + ruột token QR bàn** | ~0.5 ngày | **Phải in lại toàn bộ tem QR dán bàn ở mọi quán** | **Không sửa được bằng code** |
| **D. Mã quán trong kho đồng bộ ngoại tuyến (Dexie)** | ~0.5 ngày | Nâng cấp schema IndexedDB **trên từng máy POS, có thể đang giữ đơn chưa đồng bộ** | **Rất lớn** |
| **E. Đường dẫn tệp tải lên có mã quán** | ~2 giờ | Chuyển tệp + viết lại đường dẫn đã lưu trong database | Trung bình |

Ba trong năm việc này (A, C, D) có đặc điểm chung: **chúng đã kịp bám rễ vào thứ không sửa bằng deploy được** — tem giấy dán trên bàn, và dữ liệu nằm trong máy tính bảng ngoài tầm với.

Đây đúng là loại sai lầm mà chương 1.1 của roadmap gốc cảnh báo: *"một lỗi thiết kế database phát hiện ở tháng thứ 4 tốn hơn toàn bộ chi phí API của cả năm."*

---

## 2. KIẾN TRÚC ĐỀ XUẤT: **P4⁺**

> **Vẫn là P4 — mỗi quán một bản cài đặt riêng, một database, một `.env`. Không có tầng tenant nào chạy lúc thực thi.**
>
> **Nhưng viết code như thể đã multi-tenant ở đúng năm chỗ trên.**

Nói bằng ẩn dụ của bản 1 (mỗi quán một cuốn sổ, cất trong tủ riêng):

- **P4 trần** là: chỉ có một cuốn sổ, nên khỏi cần tủ, khỏi cần dán nhãn. Ngày có cuốn thứ hai thì đi dán nhãn cho tất cả.
- **P4⁺** là: vẫn một cuốn sổ, vẫn một tủ — nhưng **cuốn sổ đã có nhãn ghi tên quán, và mọi người đã quen thò tay vào tủ chứ không vơ đại trên bàn.** Ngày có cuốn thứ hai, chỉ cần mua thêm tủ.

Cái P4⁺ **không** làm, ghi rõ để không ai hiểu nhầm là đã xây SaaS:

- ❌ Không có bảng `tenants`, không có database lõi, không có danh bạ quán
- ❌ Không có `TenantContext` phân giải lúc chạy — mã quán là **một hằng số đọc từ cấu hình của chính bản cài đặt**
- ❌ Không có `tenant:create`, `tenant:migrate`
- ❌ Không có `branch_id`, không có bảng `branches` — quyết định 8.2 giữ nguyên
- ❌ Không có gói cước, thanh toán thuê bao, trang đăng ký

Khối lượng thêm so với bản 1: **khoảng 3 ngày.** Đổi lại, bước bị bản 1 đánh giá rủi ro cao nhất biến mất khỏi tương lai.

---

## 3. NĂM QUYẾT ĐỊNH — CHI TIẾT

### 3.A. Kết nối có tên `tenant` — quyết định quan trọng nhất

**Vấn đề.** Hôm nay mọi Model, Query, Action, Filament Resource, migration đều dùng kết nối mặc định *một cách ngầm định* — tức là không ai viết ra, nên không ai kiểm được. Bản 1, Bước 3, mô tả việc chuyển sang kết nối có tên là *"bước rủi ro cao nhất của cả dự án — nó chạm mọi truy cập dữ liệu."*

**Nhận xét then chốt: rủi ro đó không nằm ở bản chất công việc, mà nằm ở khối lượng code tại thời điểm làm.** Việc này chỉ tăng khối lượng theo thời gian, không bao giờ giảm. Codebase hôm nay là nhỏ nhất mà nó sẽ từng là.

Và lưới an toàn thì **giống hệt nhau ở cả hai thời điểm**, vì đây là thay đổi không đổi hành vi:

> `tenant` trỏ vào đúng database mà `default` đang trỏ. Cùng một database, chỉ khác tên gọi. **Toàn bộ bộ test hiện có phải xanh mà không sửa một dòng test nào.**

Làm sớm rẻ hơn, an toàn bằng nhau. Không có lý do hoãn.

**Làm gì:**

1. `config/database.php` thêm kết nối `tenant`, trỏ cùng thông số với `default`.
2. Một base Model khai `protected $connection = 'tenant'`. Mọi Model kế thừa.
3. Mọi `DB::table()`, `DB::select()`, `selectRaw` thô (bản 1 đếm được **36 chỗ**) chuyển sang `DB::connection('tenant')`.
4. Migration chạy trên kết nối `tenant`.
5. Filament dùng kết nối `tenant`.
6. **Bộ quét tự động** `MoiTruyVanDeuDiQuaKetNoiTenantTest` — song sinh của `OnlyOneStockWriterTest`. Quét toàn bộ Model / Query / Action / Filament Resource: không cái nào dùng kết nối mặc định. Đây là thứ bảo vệ cả code chưa ai viết.

**Điều KHÔNG làm hôm nay:** không để `default` trỏ vào database rỗng. Dưới P4 chỉ có một database, để `default` rỗng là tự chuốc lấy phiền toái với các lệnh `artisan` dựng sẵn của Laravel. Đó là việc của ngày mở khoá SaaS, và lúc đó bộ quét ở mục 6 đã đảm bảo không còn chỗ nào dùng `default`.

**Cái được ngay cả khi không bao giờ có SaaS:** một chỗ duy nhất để đổi thông số kết nối, và một bất biến kiểm được bằng máy thay vì bằng trí nhớ.

**Bẫy:** `DatabaseDriverTest` (đã tồn tại, đã tái phát bốn lần) phải mở rộng để kiểm cả kết nối `tenant`, không chỉ `default`. Nếu bỏ sót, cùng một lỗi sẽ tái phát lần thứ năm.

---

### 3.B. Khoá cache, idempotency, Sanctum theo quán

**Vấn đề.** `CACHE_STORE=database`. Bảng `cache` hôm nay chứa: khoá chống bấm hai lần (`EnsureIdempotencyKey:44`), bộ đếm PIN sai (`VerifyApproverPin:45`), khoá đồng bộ toàn cục (`SyncBatch:119` — `Cache::lock('sync:batch', 120)`), bộ đếm chặn gọi dồn dập. Không cái nào mang mã quán.

**Làm gì:**

1. Bảng `cache`, `cache_locks`, `personal_access_tokens`, `activity_log` nằm trên kết nối `tenant` (hệ quả của 3.A).
2. Một hàm sinh khoá duy nhất, mọi khoá cache đi qua nó, tự động gắn tiền tố mã quán. Cấm gọi thẳng `Cache::` với chuỗi tự viết.
3. `Cache::lock('sync:batch')` → mang mã quán. Cùng cách với bộ đếm ở `SyncBatch:663,669` và `Phase0Check:124`.
4. Bộ quét `KhongCoKhoaCacheNaoThieuMaQuanTest` (#26 của bản 1) — **viết ngay**, dưới P4 nó luôn xanh vô hại, nhưng nó bắt mọi khoá viết trong lúc chờ.

**Bẫy nghiêm trọng — vòng lặp gà và trứng.** Mã quán đọc từ bảng cấu hình. Nếu tầng đọc cấu hình lại đi qua cache, mà khoá cache lại cần mã quán, thì hỏng. **Mã quán phải đọc thẳng từ database, không qua cache, và nhớ trong bộ nhớ tiến trình.** Đây là chỗ dễ sai nhất của cả mục 3.B.

---

### 3.C. Mã quán trong URL gọi món và ruột token QR bàn — không sửa lại được

**Vấn đề.** Đây là mục duy nhất trong năm mục mà làm sai thì **không sửa bằng deploy được.**

Tem QR dán trên bàn là **vật lý**. Nó mang `dining_tables.public_code`. Nếu ngày mai đường dẫn gọi món cần mang thêm mã quán, thì mọi tem của mọi quán phải in lại và dán lại — trong lúc khách đang ngồi.

Đồng thời, `Ordering/Support/GuestSessionToken.php:52-62` mã hoá ruột token bằng `APP_KEY`, và ruột chỉ có `{'s': id lượt khách, 'b': id bàn, 'h': hạn giờ}` — toàn id số trần. Dưới P4 mỗi bản cài đặt có `APP_KEY` riêng nên vô hại. Nhưng ngày gộp về một hệ thống dùng chung `APP_KEY`, token cấp ở quán Anh Ba **giải mã hợp lệ ở quán Chị Tư** và trỏ vào lượt khách trùng id.

**Làm gì:**

1. Chốt hình dạng đường dẫn gọi món **có chỗ cho mã quán ngay từ bây giờ**, ví dụ `/g/{ma_quan}/{public_code}`. Dưới P4, `{ma_quan}` là hằng số của bản cài đặt, mọi tem in ra đã đúng vĩnh viễn.
2. Ruột token mang thêm mã quán, và `EnsureGuestSessionToken` **kiểm khớp** với mã quán của bản cài đặt — từ chối nếu lệch.
3. Test `TokenBanQrChiSongTrongQuanCapNoTest` (#10 của bản 1).

**Thời điểm:** đổi định dạng token làm chết token đang lưu hành. Làm lúc quán đóng cửa, khách quét lại là xong. Nếu quán đã in tem rồi thì **đây là việc gấp nhất trong cả văn bản** — mỗi ngày trôi qua là thêm tem sai.

> Câu hỏi cho anh: **quán đã in và dán tem QR chưa?** Nếu rồi, mục 3.C nhảy lên đầu hàng đợi, trước cả 5A.

---

### 3.D. Mã quán trong kho đồng bộ ngoại tuyến

**Vấn đề.** Dexie/IndexedDB nằm **trong máy tính bảng**, không nằm trên máy chủ. Nâng cấp schema IndexedDB là việc chạy trên từng thiết bị, và có thể chạy **trong lúc kho gửi đi đang giữ đơn hàng chưa đồng bộ** — tức là tiền thật của một ca chưa chốt.

Kiến trúc đồng bộ của dự án là **theo thao tác, không theo ảnh chụp trạng thái**. Nghĩa là mỗi dòng trong kho gửi đi là một thao tác chờ áp dụng. Một bản nâng cấp schema sai vào lúc sai là mất thao tác — mất đơn, mất phiếu thu.

**Làm gì:**

1. Thêm `ma_quan` vào schema Dexie **ngay bây giờ**, khi các máy POS gần như chắc chắn đang rỗng ngoài giờ bán. Dưới P4 là hằng số.
2. `POST /sync/batch` kiểm mã quán của gói tin khớp với bản cài đặt, **từ chối nếu lệch**. Bảo vệ luôn tình huống rất thực dưới P4: một máy tính bảng bị mang nhầm từ quán này sang quán kia.
3. Cập nhật `docs/thiet-ke-dong-bo.md`.

**Thời điểm bắt buộc: làm khi kho gửi đi rỗng.** Kiểm trước khi nâng cấp, không nâng cấp mù.

---

### 3.E. Đường dẫn tệp tải lên

`FILESYSTEM_DISK=local`. Chốt quy ước đường dẫn có mã quán ngay từ tệp đầu tiên. Đây là mục rẻ nhất và ít quan trọng nhất trong năm mục — đưa vào vì nó là một dòng quy ước, không phải vì nó cấp bách. Test `DuongDanTepTaiLenLuonNamTrongThuMucQuanTest` (#28).

---

## 4. THỨ TỰ THỰC THI — ĐÃ ĐỔI SO VỚI BẢN 1

Bản 1 xếp "vá ba điểm đỏ cấu hình" là Bước 1. **Bản 2 đảo lại**: kết nối `tenant` phải đi trước, nếu không thì bảng cấu hình mới sẽ được viết trên kết nối mặc định rồi phải chuyển lại ngay sau đó.

```
5A.0  Kết nối tenant + bộ quét + mở rộng DatabaseDriverTest      (3.A)
      └─ Cổng: toàn bộ test cũ xanh, KHÔNG sửa một dòng test nào

5A.1  Bảng cấu hình quán bền vững — có ma_quan là dòng đầu tiên
      └─ ma_quan đọc thẳng từ database, không qua cache

5A.2  VietQR vào bảng cấu hình + chặn cứng, cấm rơi về mặc định
      └─ Cổng: xoá khoá khỏi .env staging → hệ thống từ chối, không im lặng

5A.3  Thông tin quán trên hoá đơn + địa chỉ máy in + trang Filament

5A.4  Khoá cache / idempotency / Sanctum theo quán               (3.B)

5A.5  Mã quán trong URL gọi món + ruột token QR bàn              (3.C)
      └─ Làm ngoài giờ bán. Nếu đã in tem QR → chuyển lên trước 5A.1

5A.6  Mã quán trong Dexie + kiểm ở /sync/batch                   (3.D)
      └─ Bắt buộc kiểm kho gửi đi rỗng trước khi nâng cấp

5A.7  Đường dẫn tệp + bộ quét còn lại                            (3.E)
```

Một bước con = một commit = một PR = một session. Không gộp.

**Ước lượng: 2 tuần** (bản 1: 1 tuần). Chênh lệch một tuần này mua lại việc xoá bỏ bước rủi ro cao nhất của dự án, cộng ba thứ không sửa lại được.

---

## 5. NHỮNG GÌ VẪN DỪNG — VÀ VÌ SAO KẾT LUẬN P4 KHÔNG ĐỔI

Sau P4⁺, phần còn lại của SaaS thật gồm đúng bốn việc:

| Việc | Vì sao chờ được |
|---|---|
| Database lõi + danh bạ `tenants` | Thêm mới hoàn toàn, không sửa gì đang có. Rẻ như nhau ở mọi thời điểm |
| `TenantContext` phân giải lúc chạy | Nhờ 3.A, việc này thu lại còn **chọn database theo mã quán** — một middleware, một việc. Không còn là "chạm mọi truy cập dữ liệu" |
| `tenant:create` / `tenant:migrate` | Công cụ vận hành, không phải thay đổi kiến trúc |
| Quy trình sao lưu / khôi phục / tiễn khách | Quy trình, phải diễn tập thật, nhưng không phụ thuộc code viết trước |

**Không việc nào trong bốn việc này rẻ hơn khi làm sớm.** Đó là toàn bộ lý do kết luận P4 của bản 1 vẫn đúng — bản 2 không lật nó, chỉ tách ra khỏi nó năm việc bị xếp nhầm chỗ.

**Hai tín hiệu mở khoá giữ nguyên**, đo được, không cảm tính:
- Đang chạy từ **4 bản cài đặt trở lên**, hoặc một lần phát hành tốn quá 2 giờ thao tác tay, hoặc đã có một lần hai quán chạy lệch schema mà không ai phát hiện.
- Có **khách hàng thật, đã trả tiền hoặc đặt cọc**, muốn xem nhiều quán trên một màn hình.

Mục 5 (53 điểm rò rỉ) và mục 7 (28 test) của bản 1 giữ nguyên làm tài liệu tham chiếu. Sau P4⁺, số điểm đỏ ngoài database của bản 1 — **3 điểm** — về **0**.

---

## 6. THAY ĐỔI `CLAUDE.md`

Bản 1 nêu hai luật mới. Bản 2 phân loại lại theo nguyên tắc đã học từ 4A.2 (**không ghi luật cho khái niệm chưa tồn tại**):

| Luật | Ghi ngay? |
|---|---|
| Mọi truy vấn đi qua kết nối `tenant`, cấm dùng kết nối mặc định | ✅ **Ghi ngay.** Khái niệm tồn tại từ 5A.0, có bộ quét canh |
| Mọi khoá cache và khoá chống tranh chấp phải mang mã quán | ✅ **Ghi ngay.** Có bộ quét canh |
| Cấm mọi giao dịch chạm hai tenant cùng lúc | ⏸ Chờ. Chưa có tenant thứ hai để chạm |
| Database tenant mới phải tạo bằng cách chạy đủ 58 migration, cấm chép bảng | ⏸ Chờ. Chưa có lệnh tạo tenant |

Hai luật chờ vào `docs/viec-ton.md` kèm điều kiện kích hoạt.

**Luật 11 (chuỗi thứ tự khoá) và mục 7.4 (ba chốt chặn lõi) không đụng một chữ.** Đây là điểm mạnh nhất của cả P4 và P3: `uq_shifts_only_one_open`, `uq_stock_takes_only_one_open`, `uq_tst_one_session_per_table` mang nghĩa "trong một database của một quán" — đúng sẵn, không sửa.

---

## 7. RỦI RO CỦA CHÍNH BẢN 2

Ghi ra để công bằng, vì bản này đang đề nghị làm thêm việc:

| Rủi ro | Đối phó |
|---|---|
| **5A.0 chạm mọi điểm truy cập dữ liệu** — đúng là rủi ro cao nhất, bản 2 chỉ dời nó sớm lên chứ không xoá | Hành vi không đổi. Cổng chặn: toàn bộ test cũ xanh **không sửa một dòng test nào**. Deploy ngoài giờ bán, có người trực. Quay lui = trả code về, không có dữ liệu nào phải hoàn tác |
| **Đây vẫn là xây cho nhu cầu chưa tồn tại** | Thật một phần. Nhưng A và B có ích ngay cả khi không bao giờ có quán thứ hai (một chỗ đổi kết nối, bất biến kiểm bằng máy). C và D thì lý do không phải SaaS mà là **không sửa lại được** |
| **5A.5 và 5A.6 chạm khách hàng và thiết bị đang chạy** | 5A.5 làm lúc đóng cửa. 5A.6 bắt buộc kiểm kho gửi đi rỗng trước. Cả hai không làm vào tối thứ Bảy |
| **Thêm 1 tuần vào lúc Phase 4 chưa xong** | Ba việc ở mục 0.3 của hướng dẫn Phase 5 (gỡ 4B.1, Filament dòng mồ côi, chốt 4B.3) vẫn đi trước. Filament dòng mồ côi đang chặn vận hành thật — nó thắng tất cả |

---

## 8. CẦN ANH QUYẾT

1. **Quán đã in và dán tem QR bàn chưa?** Đây là câu quan trọng nhất. Nếu rồi, 3.C nhảy lên đầu hàng đợi ngay hôm nay.
2. **Duyệt P4⁺ hay giữ P4 trần?** Chênh lệch ~1 tuần. Nếu chỉ chọn được một mục, chọn **3.A**.
3. **Hình dạng mã quán** — chuỗi ngắn do người đặt (`anh-ba`, `chi-tu`) hay UUID? Tôi nghiêng về **chuỗi ngắn**: nó xuất hiện trong URL trên tem QR, khách nhìn thấy, và người vận hành phải gõ được.
4. **Hình dạng đường dẫn gọi món** — `/g/{ma_quan}/{public_code}` hay khác? Chốt xong là in tem được, và **không đổi lại nữa**.

---

*Hết văn bản. Chờ duyệt bằng văn bản trước khi mở 5A.0.*
