# PHASE 5 — HƯỚNG DẪN THỰC THI (BẢN 2)

**Nguồn thiết kế:** `docs/thiet-ke-multi-tenant.md` (bản 2 — kiến trúc **P4⁺**).
**Thay thế:** bản hướng dẫn Phase 5 trước đó (dựa trên bản thiết kế 1).

| | |
|---|---|
| Ngày viết | 13/08/2026 |
| Trạng thái | CHỜ DUYỆT |
| Phạm vi | Khối **5A** — tám bước con, ~2 tuần. Khối 5B và 5C vẫn khoá |
| Điều kiện mở khoá | Anh duyệt bằng văn bản. Trước đó: không migration, không migration nháp |

---

## 0. TRƯỚC KHI MỞ BƯỚC ĐẦU TIÊN

### 0.1. Một việc phải làm ngay hôm nay, không chờ duyệt

Bản thiết kế 2 trích dẫn bản 1 ở ba chỗ và tuyên bố chúng còn hiệu lực:

| Trích dẫn | Nội dung bị mất nếu bản 1 biến mất |
|---|---|
| Mục 2 bản 1 | Bảng so sánh ba phương án — căn cứ của mọi quyết định sau |
| **Mục 5 bản 1** | **53 điểm rò rỉ chéo quán, có đường dẫn file cụ thể** |
| Mục 7 bản 1 | 28 test bắt buộc cho bước triển khai SaaS thật |

Mục 5 là thứ đắt nhất trong ba thứ: nó là kết quả của một lần rà toàn bộ codebase. Mất nó nghĩa là ngày mở khoá 5B phải rà lại từ đầu.

> **Việc:** khôi phục bản 1 dưới tên `docs/thiet-ke-multi-tenant-ban-1.md`, và sửa bản 2 để trỏ đúng tên file đó. Đây là việc chép file, không phải việc code.

### 0.2. Ba việc chặn trước Phase 5

| # | Việc | Vì sao đi trước |
|---|---|---|
| 1 | **Filament UI xác nhận dòng mồ côi** | Đang chặn vận hành thật. Thắng mọi thứ trong văn bản này |
| 2 | **Gỡ 4B.1 "Dashboard đa chi nhánh" khỏi Phase 4** | Quyết định 8.2 chốt không xây `branch_id`. Không có chi nhánh thì không có dashboard đa chi nhánh. Chuyển sang `viec-ton.md` |
| 3 | **Chốt hoặc đóng 4B.3 (hoá đơn điện tử)** | Đang treo vì thiếu yêu cầu pháp lý/nhà cung cấp. Đóng lại, đừng để treo qua Phase 5 |

Nhánh `park/loyalty-4a1` giữ nguyên trạng thái treo, không chạm.

### 0.3. Một câu hỏi quyết định thứ tự

> **Quán đã in và dán tem QR bàn chưa?**

- **Chưa in** → chạy đúng thứ tự 5A.0 → 5A.7 dưới đây.
- **Đã in** → **5A.5 nhảy lên ngay sau 5A.1**, trước cả VietQR. Mỗi ngày trôi qua là thêm tem mang đường dẫn sai, và tem là vật lý — không sửa bằng deploy.

### 0.4. Quy tắc chung cho cả tám bước

- Một bước con = **một commit = một PR = một session**. Không gộp.
- `docs/PHASE.md` chỉ mở **một** bước tại một thời điểm. Chỉ anh sửa file này.
- Opus rà kiến trúc và bất biến → Sonnet thực thi cơ học → **không push khi Opus chưa xem migration**.
- Phát hiện ngoài phạm vi → `docs/viec-ton.md`, không tự sửa.
- Không deploy vào tối thứ Sáu, tối thứ Bảy, hay ngày lễ.

---

## 1. BƯỚC 5A.0 — KẾT NỐI CÓ TÊN `tenant`

**Đây là bước rủi ro cao nhất của toàn Phase 5.** Tách làm hai commit, không gộp.

### 1.1. Phát hiện quan trọng chưa có trong bản thiết kế

Bản thiết kế mô tả 5A.0 là *"cùng một database, chỉ khác tên gọi kết nối — hành vi không đổi."* **Câu đó đúng với truy vấn, nhưng sai với giao dịch và khoá.**

Laravel quản lý transaction **theo từng kết nối**. Hai kết nối trỏ cùng một database vẫn là **hai phiên riêng biệt** đối với MariaDB. Hệ quả:

```php
DB::transaction(function () {          // ← mở giao dịch trên kết nối 'default'
    $phieu = StockMovement::create(...); // ← ghi trên kết nối 'tenant'
    $so_du->save();                      // ← ghi trên kết nối 'tenant'
});                                    // ← commit kết nối 'default': KHÔNG bọc hai lệnh trên
```

Đoạn này **chạy không lỗi, test có thể vẫn xanh, và tính nguyên tử biến mất trong im lặng.** Nếu lệnh thứ hai hỏng, lệnh thứ nhất đã nằm lại trong database. Đúng loại lỗi mà bài học của dự án đã gọi tên: *lỗi im lặng nguy hiểm hơn lỗi ầm ĩ*.

Nó rơi thẳng vào kiến trúc sổ cái ghi thêm: `RecordStockMovement` ghi sổ cái rồi cập nhật `stock_balances` — hai lệnh bắt buộc phải cùng sống cùng chết.

Còn `lockForUpdate()` thì ngược lại — nó sẽ **ầm ĩ**: hai phiên khác nhau tranh khoá trên cùng dòng, treo tới khi hết hạn chờ. Khó chịu nhưng thấy được ngay.

**Vì sao đây lại là lý do làm sớm chứ không phải lý do hoãn:** hôm nay số điểm cần rà là nhỏ nhất mà nó sẽ từng là. Và tin tốt: dưới P4 hai kết nối trỏ cùng một database, nên **bảng `migrations` là cùng một bảng**, không có nguy cơ tách đôi lịch sử migration. Ngày mở khoá 5B thì không còn miễn phí như vậy.

### 1.2. Commit 1 — chuyển kết nối

Ra:
- `config/database.php` thêm kết nối `tenant`, cùng thông số với `default`.
- Base Model khai `protected $connection = 'tenant'`. Mọi Model kế thừa.
- **36 chỗ SQL thô** (`DB::table`, `selectRaw`, `whereRaw` — danh sách ở mục 2.2 bản 1) chuyển sang `DB::connection('tenant')`.
- Filament dùng kết nối `tenant`.
- Mở rộng `DatabaseDriverTest` kiểm **cả hai** kết nối là MariaDB. Lỗi driver đã tái phát bốn lần; bỏ sót chỗ này là lần thứ năm.

**Chưa** để `default` trỏ vào database rỗng. Dưới P4 chỉ có một database; để rỗng là tự chuốc phiền với các lệnh `artisan` dựng sẵn. Việc đó thuộc ngày mở khoá 5B.

### 1.3. Commit 2 — quét giao dịch và khoá

Ra:
- Mọi `DB::transaction(...)` → `DB::connection('tenant')->transaction(...)`. Rà **từng chỗ**, không tìm-thay hàng loạt.
- Mọi `DB::beginTransaction/commit/rollBack` rời rạc, nếu có, cùng cách.
- Bộ quét `MoiGiaoDichDeuMoTrenKetNoiTenantTest` — quét toàn bộ Action và Query, cấm `DB::transaction` trần. Bắt cả code viết sau.
- Bộ quét `MoiTruyVanDeuDiQuaKetNoiTenantTest` — song sinh của `OnlyOneStockWriterTest`, quét Model/Query/Action/Filament Resource.

**Bất biến không được đụng:** luật "PIN duyệt luôn đi trước khi mở transaction" giữ nguyên. Đổi kết nối không được kéo lệnh nào vào trong hay ra ngoài giao dịch.

### 1.4. Cổng chặn 5A.0

- [ ] Toàn bộ bộ test hiện có xanh, **không sửa một dòng test cũ nào** — đây là lưới an toàn duy nhất, mất nó là mất căn cứ để tin bước này an toàn
- [ ] `DatabaseDriverTest` xanh trên cả hai kết nối
- [ ] Hai bộ quét mới xanh
- [ ] Đọc tay lại **mọi** đường ghi của `RecordStockMovement` và đường thu tiền, xác nhận nằm trong giao dịch trên kết nối `tenant`
- [ ] Chạy thử một tối giả lập trên staging: mở ca → gọi món → in bếp → thu tiền → đóng ca → xem báo cáo

**Quay lui:** trả code về bản trước. Không có thay đổi dữ liệu nào phải hoàn tác.
**Nghỉ bán:** không. Deploy ngoài giờ bán, có người trực.

---

## 2. BƯỚC 5A.1 — BẢNG CẤU HÌNH QUÁN BỀN VỮNG

**Mục tiêu:** đổi chỗ lưu, không đổi hành vi.

Ra:
- Migration bảng cấu hình trong database quán, khoá `khoa` UNIQUE, có `kieu_du_lieu` để ép kiểu khi đọc.
- **Dòng đầu tiên là `ma_quan`** — hằng số nuôi 5A.4, 5A.5, 5A.6.
- `Support/CauHinhQuan.php` đổi nguồn đọc. **Giữ nguyên chữ ký hàm public** — phía gọi không sửa một dòng.
- Nhớ tạm trong phạm vi một request. Không `forever`.
- Data migration một lần: chép giá trị đang có trong bảng `cache` sang bảng mới; thiếu thì lấy mặc định hiện hành. Người dùng không được thấy khác biệt.
- Ghi `activitylog` mỗi lần đổi: ai, khi nào, từ gì sang gì.

**Bẫy 1 — vòng lặp gà và trứng.** Khoá cache (5A.4) cần `ma_quan`; `ma_quan` nằm trong database. Nếu tầng đọc cấu hình đi qua cache thì hỏng. **`ma_quan` phải đọc thẳng từ database, không qua cache, nhớ trong bộ nhớ tiến trình.**

**Bẫy 2 — `ma_quan` là ghi một lần.** Nó sẽ nằm trong khoá cache, trong đường dẫn trên tem QR giấy, và trong dữ liệu Dexie ở máy tính bảng. Đổi nó sau là làm mồ côi cả ba. **Không làm giao diện sửa `ma_quan`.** Đổi nó là một quy trình có văn bản, không phải một ô nhập liệu.

**Bẫy 3 — ghi cạnh tranh.** `updateOrInsert` trên khoá UNIQUE. Cấm `first()` rồi `save()`.

**Bẫy 4 — `nguong_hao_hut_can_pin`** nằm trên đường duyệt PIN. Đọc ngưỡng phải nằm **ngoài** giao dịch.

**Test:** `NguongCauHinhSongSotQuaCacheClearTest` · `DoiNguongCauHinhCoGhiNhatKyTest` · `GiaTriCauHinhSauDiTruBangGiaTriTruocDiTruTest` · `MaQuanDocDuocKhiCacheRongTest`.

**Quay lui:** giá trị cũ vẫn nằm nguyên trong `.env` và bảng `cache`. `down()` xoá bảng sạch.

---

## 3. BƯỚC 5A.2 — VIETQR VÀO DATABASE + CHẶN CỨNG

**Tier Cao. Opus tự làm, không giao Sonnet.** Đây là đường đi của tiền.

Ra:
- `bank_bin`, `account_number`, `account_name` chuyển từ `config('vietqr.*')` sang bảng cấu hình.
- **Chặn cứng:** thiếu bất kỳ giá trị nào → `GetVietQrForTableSession` ném lỗi rõ ràng, **không sinh mã QR, không rơi về `.env`**.
- Thông báo tiếng Việt dùng được cho thu ngân: *"Quán chưa khai tài khoản ngân hàng — vào Cấu hình để khai."*
- Validate định dạng khi lưu.

**Vì sao không thương lượng:** cơ chế rơi-về-mặc-định nghĩa là khi cấu hình sai, tiền khách chảy vào tài khoản khai trong `.env` mà không ai thấy lỗi. Lỗi im lặng trên đường đi của tiền là loại tệ nhất.

**Trước khi viết code:** liệt kê **mọi** đường đi tới `GetVietQrForTableSession` và chứng minh không đường nào lách được chốt chặn.

**Cổng chặn:** xoá ba khoá VietQR khỏi `.env` staging → hệ thống từ chối sinh QR, không im lặng dùng mặc định.

**Sau khi merge:** xoá ba khoá khỏi `.env` sản xuất. Để lại là để nguyên một đường rơi-về-mặc-định cho người sau vô tình bật lại.

**Test:** `ChuaKhaiTaiKhoanThiTuChoiSinhMaQrTest` (quan trọng nhất Phase 5) · `MaQrChuaDungSoTaiKhoanDaKhaiTest` · `DoiTaiKhoanNganHangCoGhiNhatKyTest`.

---

## 4. BƯỚC 5A.3 — THÔNG TIN HOÁ ĐƠN, MÁY IN, MÀN HÌNH CẤU HÌNH

Ra:
- Tên quán, địa chỉ, MST, điện thoại trên `FinalBillTemplate` đọc từ bảng cấu hình.
- **Địa chỉ máy in bếp/bar** vào bảng cấu hình (điểm #26, #28 của bản 1 — gộp vào đây vì cùng một màn hình).
- Trang Filament "Cấu hình quán", bốn nhóm: thông tin quán · tài khoản ngân hàng · máy in · ngưỡng vận hành.
- Nhóm tài khoản ngân hàng: **chỉ `owner` sửa được**.
- Xem trước hoá đơn ngay trên trang, để chủ quán không phải in thử.
- `ma_quan` **hiển thị, không sửa được** (bẫy 2 ở 5A.1).

**Test:** `HoaDonInRaMangThongTinTuBangCauHinhTest` · `ChiChuQuanDoiDuocTaiKhoanNganHangTest` · `MaQuanKhongSuaDuocQuaGiaoDienTest`.

---

## 5. BƯỚC 5A.4 — KHOÁ CACHE, IDEMPOTENCY, SANCTUM THEO QUÁN

Ra:
- `cache`, `cache_locks`, `personal_access_tokens`, `activity_log` nằm trên kết nối `tenant` (đã có từ 5A.0, chỉ xác nhận).
- **Một hàm sinh khoá duy nhất**, tự gắn tiền tố `ma_quan`. Mọi khoá cache đi qua nó. Cấm gọi thẳng `Cache::` với chuỗi tự viết.
- Vá bốn chỗ: `EnsureIdempotencyKey:44` · `VerifyApproverPin:45` · `SyncBatch:119` (`Cache::lock('sync:batch', 120)`) và bộ đếm `SyncBatch:663,669` · `Phase0Check:124`.
- Bộ quét `KhongCoKhoaCacheNaoThieuMaQuanTest` (#26 bản 1).

**Bẫy — khoá đang lưu hành.** Đổi tiền tố làm mọi khoá idempotency đang có trở nên vô nghĩa. Máy POS gửi lại một yêu cầu cũ sẽ **không nhận ra là trùng** và có thể ghi hai lần. **Deploy khi kho gửi đi rỗng và không có yêu cầu đang treo** — cùng điều kiện với 5A.6.

**Test:** thêm `KhoaIdempotencyMangMaQuanTest` · `KhoaDongBoKhongConTenCoDinhTest`.

---

## 6. BƯỚC 5A.5 — MÃ QUÁN TRONG URL GỌI MÓN VÀ RUỘT TOKEN QR

**Nếu quán đã in tem QR: bước này chạy ngay sau 5A.1, trước 5A.2.**

Ra:
- Chốt hình dạng đường dẫn có chỗ cho mã quán, ví dụ `/g/{ma_quan}/{public_code}`. Dưới P4 `{ma_quan}` là hằng số → tem in ra đúng vĩnh viễn.
- Ruột token (`Ordering/Support/GuestSessionToken.php:52-62`) mang thêm mã quán; `EnsureGuestSessionToken` **kiểm khớp**, từ chối nếu lệch.
- Đường dẫn cũ nhận thêm 2 tuần rồi tắt, để tem đã in (nếu có) không chết ngay.

**Bẫy — token đang lưu hành chết khi đổi định dạng.** Khách đang ngồi bàn sẽ bị đá ra. **Làm lúc quán đóng cửa**, khách quét lại là xong.

**Test:** `TokenBanQrChiSongTrongQuanCapNoTest` (#10 bản 1) · `DuongDanGoiMonMangMaQuanTest`.

---

## 7. BƯỚC 5A.6 — MÃ QUÁN TRONG KHO ĐỒNG BỘ NGOẠI TUYẾN

**Bước duy nhất chạm dữ liệu nằm ngoài tầm với — trong máy tính bảng.**

Ra:
- Thêm `ma_quan` vào schema Dexie, kèm bản nâng cấp phiên bản Dexie đúng chuẩn.
- `POST /sync/batch` kiểm mã quán của gói tin khớp bản cài đặt, **từ chối nếu lệch**. Bảo vệ luôn tình huống rất thực dưới P4: máy tính bảng bị mang nhầm từ quán này sang quán kia.
- Cập nhật `docs/thiet-ke-dong-bo.md`.

**Bẫy — kho gửi đi có thể đang giữ tiền thật.** Đồng bộ là **theo thao tác, không theo ảnh chụp trạng thái**: mỗi dòng là một thao tác chờ áp dụng. Mất một dòng là mất một đơn hoặc một phiếu thu của ca chưa chốt.

**Điều kiện bắt buộc trước khi nâng cấp:**
- [ ] Kiểm **từng máy POS**, xác nhận kho gửi đi **rỗng**. Kiểm, không đoán
- [ ] Ca đã đóng, không có phiên bàn nào đang mở
- [ ] Bản nâng cấp Dexie **không xoá dữ liệu chưa đồng bộ** kể cả khi gặp dòng lạ — thà giữ lại chờ người xử lý còn hơn xoá sạch cho gọn

**Test:** `DongBoTuChoiGoiTinSaiMaQuanTest` · `NangCapDexieKhongLamMatDongChuaDongBoTest`.

---

## 8. BƯỚC 5A.7 — ĐƯỜNG DẪN TỆP VÀ CÁC BỘ QUÉT CÒN LẠI

Ra:
- Quy ước đường dẫn tệp tải lên mang `ma_quan`, áp từ tệp đầu tiên.
- `DuongDanTepTaiLenLuonNamTrongThuMucQuanTest` (#28 bản 1).
- `ViecChayNenTongHopDungQuanTest` (#20 bản 1) — viết **ngay bây giờ** dù `QUEUE_CONNECTION=sync`, để ngày ai đó đổi sang hàng đợi thật thì test đỏ chứ không phải doanh thu sai.

Đây là bước rẻ nhất và ít cấp bách nhất. Nếu phải cắt một bước vì hết thời gian, cắt bước này.

---

## 9. CỔNG CHẶN RA KHỎI 5A

- [ ] Toàn bộ suite xanh · `DatabaseDriverTest` xanh trên cả hai kết nối
- [ ] Năm bộ quét tự động xanh: truy vấn · giao dịch · khoá cache · đường dẫn tệp · việc chạy nền
- [ ] `cache:clear` trên staging → ba ngưỡng còn nguyên
- [ ] Xoá khoá VietQR khỏi `.env` staging → từ chối sinh QR, không im lặng
- [ ] **Bài diễn tập P4:** dựng database rỗng → `migrate --seed` → khai cấu hình **qua màn hình** → bán một hoá đơn giả từ mở ca tới đóng ca → xem báo cáo — **không đụng một dòng code hay `.env` nghiệp vụ nào**

Bài cuối là lời hứa "P4 chạy được". Nó đỏ thì 5A chưa xong, dù tám PR đã merge.

---

## 10. BẢNG GIAO VIỆC

| Task | Tier | Lý do |
|---|---|---|
| Rà đường giao dịch/khoá ở 5A.0 commit 2 | **Cao** | Tính nguyên tử của sổ cái. Sai là mất im lặng |
| Thiết kế chặn cứng VietQR + đường lỗi tới thu ngân | **Cao** | Chạm tiền |
| Rà data migration chép giá trị từ `cache` | **Cao** | Chạm dữ liệu vận hành đang chạy |
| Chốt hình dạng `ma_quan` và đường dẫn gọi món | **Cao** | In lên giấy, không đổi lại được |
| Kế hoạch nâng cấp Dexie | **Cao** | Dữ liệu ngoài tầm với, có tiền thật |
| Đổi kết nối Model/Query/Filament (5A.0 commit 1) | Trung | Cơ học, có bộ quét canh |
| Migration + `CauHinhQuan` + trang Filament | Trung | |
| Hàm sinh khoá cache + vá bốn chỗ | Trung | |
| Feature test từng bước | Trung | |
| Năm bộ quét tự động | Trung | Theo mẫu `OnlyOneStockWriterTest` |
| Chuỗi thông báo tiếng Việt, nhãn form | **Thấp** | Batch |

---

## 11. BỘ PROMPT

Mọi prompt mở đầu bằng khối tiền tố, không được lược bớt.

```
=== KHỐI BẮT BUỘC — KHÔNG ĐƯỢC BỎ QUA ===
Nền tảng thật: Laravel 12, MariaDB 10.4.32 qua XAMPP (KHÔNG phải MySQL 8,
KHÔNG Docker). CACHE_STORE=database. QUEUE_CONNECTION=sync. Không Redis,
không Reverb, không broadcast. Không có bảng branches, không có cột branch_id,
không có global scope nào trong toàn dự án. Đừng tin mô tả nào khác.

Trước khi sinh một dòng code nào, viết mục "PHẠM VI TÔI HIỂU" liệt kê ĐẦY ĐỦ
đường dẫn từng file sẽ tạo hoặc sửa, rồi DỪNG LẠI chờ chữ "DUYỆT".
Phát hiện gì ngoài phạm vi: ghi vào docs/viec-ton.md, KHÔNG tự sửa.
Chỉ làm đúng bước đang mở trong docs/PHASE.md.
=== HẾT KHỐI BẮT BUỘC ===
```

### 5A.0 commit 1 (Trung)

```
[Đính kèm: CLAUDE.md, config/database.php, base Model, DatabaseDriverTest]

Thêm kết nối 'tenant' trỏ CÙNG thông số với 'default', và chuyển mọi truy cập
dữ liệu sang kết nối đó.

Ràng buộc:
- Hành vi KHÔNG được đổi. Toàn bộ test hiện có phải xanh mà KHÔNG sửa một dòng
  test nào. Nếu anh thấy cần sửa test, DỪNG LẠI và báo — đó là dấu hiệu hành vi
  đã đổi.
- KHÔNG để 'default' trỏ vào database rỗng ở bước này.
- Mở rộng DatabaseDriverTest kiểm CẢ HAI kết nối là MariaDB.
- Liệt kê đầy đủ 36 chỗ SQL thô trước khi sửa, để tôi đối chiếu.

CHƯA đụng tới DB::transaction ở commit này.
```

### 5A.0 commit 2 (Cao — Opus tự làm)

```
[Đính kèm: RecordStockMovement, đường thu tiền, CLAUDE.md luật 11]

Laravel quản transaction theo từng kết nối. Sau commit 1, mọi DB::transaction()
trần vẫn mở trên 'default' trong khi Model ghi trên 'tenant' — hai phiên khác
nhau. Tính nguyên tử mất TRONG IM LẶNG.

Việc:
1. Liệt kê MỌI chỗ mở giao dịch trong dự án (DB::transaction, beginTransaction).
   Với từng chỗ, ghi rõ nó bọc những lệnh ghi nào và trên kết nối nào.
2. Chuyển sang DB::connection('tenant')->transaction(). Rà từng chỗ, KHÔNG
   tìm-thay hàng loạt.
3. Viết MoiGiaoDichDeuMoTrenKetNoiTenantTest quét toàn bộ Action và Query,
   cấm DB::transaction trần.

Ràng buộc: luật "PIN duyệt đi trước khi mở transaction" giữ nguyên. Không kéo
lệnh nào vào trong hay ra ngoài giao dịch.

Bước 1 là phần tôi cần đọc kỹ nhất. Trình bày xong hãy dừng.
```

### 5A.1 (Trung)

```
[Đính kèm: CLAUDE.md, app/Support/CauHinhQuan.php, một migration mẫu chuẩn]

Chuyển ba ngưỡng cấu hình từ bảng cache sang bảng bền vững, và thêm ma_quan
làm dòng đầu tiên.

Ràng buộc:
- Chữ ký hàm public của CauHinhQuan KHÔNG đổi. Phía gọi không sửa dòng nào.
- ma_quan đọc THẲNG từ database, KHÔNG qua cache, nhớ trong bộ nhớ tiến trình.
  Lý do: khoá cache sẽ cần ma_quan → đi qua cache là vòng lặp.
- ma_quan là GHI MỘT LẦN. Không làm đường sửa nó.
- Ghi bằng updateOrInsert trên khoá UNIQUE. Cấm đọc-rồi-ghi.
- nguong_hao_hut_can_pin nằm trên đường duyệt PIN — đọc ngưỡng phải NGOÀI giao dịch.
- Data migration chép giá trị từ cache, và down() quay lui sạch.

Kèm bốn test: NguongCauHinhSongSotQuaCacheClearTest, DoiNguongCauHinhCoGhiNhatKyTest,
GiaTriCauHinhSauDiTruBangGiaTriTruocDiTruTest, MaQuanDocDuocKhiCacheRongTest.
```

### 5A.2 (Cao — Opus tự làm)

```
[Đính kèm: Billing/Queries/GetVietQrForTableSession.php, bảng cấu hình từ 5A.1]

Chuyển tài khoản ngân hàng VietQR từ config sang bảng cấu hình quán.

Ràng buộc không thương lượng:
- Thiếu bất kỳ giá trị nào thì TỪ CHỐI sinh mã QR. Cấm mọi đường rơi về .env
  hay config mặc định. Lỗi im lặng trên đường đi của tiền là lỗi tệ nhất của
  hệ thống này.
- Thông báo tiếng Việt dùng được cho thu ngân, không lộ chi tiết kỹ thuật.
- Validate định dạng khi lưu. Đổi số tài khoản ghi activitylog.

Trước khi viết code: liệt kê MỌI đường đi tới GetVietQrForTableSession và chứng
minh không đường nào lách được chốt chặn.

Kèm ChuaKhaiTaiKhoanThiTuChoiSinhMaQrTest, MaQrChuaDungSoTaiKhoanDaKhaiTest,
DoiTaiKhoanNganHangCoGhiNhatKyTest.
```

### 5A.5 và 5A.6 (Cao — chốt thiết kế trước, code sau)

```
[Đính kèm: Ordering/Support/GuestSessionToken.php, EnsureGuestSessionToken,
docs/thiet-ke-dong-bo.md, schema Dexie hiện tại]

Hai việc này chạm thứ không sửa lại được bằng deploy: tem QR in trên giấy, và
dữ liệu trong máy tính bảng.

Trước khi viết code, trình bày cho tôi duyệt:
1. Hình dạng đường dẫn gọi món cuối cùng, và cách nhận đường dẫn cũ thêm 2 tuần.
2. Hình dạng mới của ruột token, và chuyện gì xảy ra với token đang lưu hành.
3. Kế hoạch nâng cấp Dexie: kiểm kho gửi đi rỗng thế nào, và làm gì khi gặp
   dòng lạ. Nguyên tắc: THÀ GIỮ LẠI CHỜ NGƯỜI XỬ LÝ CÒN HƠN XOÁ SẠCH CHO GỌN.

Dừng ở đây. Chưa viết code.
```

*(5A.3, 5A.4, 5A.7 theo cùng khuôn, tier Trung.)*

---

## 12. GHI VÀO `docs/viec-ton.md` KHI DUYỆT

| Mục | Điều kiện mở lại |
|---|---|
| Dashboard đa chi nhánh (4B.1) — gỡ khỏi Phase 4 | Có khách thật mở địa điểm thứ hai |
| Khối 5B (DB lõi · `TenantContext` · `tenant:create`/`migrate` · quy trình sao lưu) | Một trong hai tín hiệu mục 5 bản thiết kế |
| Luật `CLAUDE.md`: cấm giao dịch chạm hai tenant | Ngày mở khoá 5B |
| Luật `CLAUDE.md`: tenant mới phải chạy đủ 58 migration, cấm chép bảng | Ngày mở khoá 5B |
| Để `default` trỏ database rỗng | Ngày mở khoá 5B |
| Khối 5C (phần thương mại) — chưa có văn bản thiết kế nào | Khi có ý định bán thật |
| Roadmap gốc lệch hiện trạng — đánh dấu tài liệu lịch sử | — |

## 13. GHI VÀO `CLAUDE.md` — CHỈ HAI LUẬT

| Luật | Ghi ở bước |
|---|---|
| Mọi truy vấn **và mọi giao dịch** đi qua kết nối `tenant`. Cấm kết nối mặc định | 5A.0 |
| Mọi khoá cache và khoá chống tranh chấp phải mang `ma_quan`, sinh qua hàm chung | 5A.4 |

Hai luật còn lại chờ 5B (mục 12) — **không ghi luật cho khái niệm chưa tồn tại**, đúng bài học 4A.2.

**Luật 11 và mục 7.4 không đụng một chữ.**

---

## 14. CẦN ANH QUYẾT TRƯỚC KHI MỞ 5A.0

1. **Quán đã in và dán tem QR bàn chưa?** Quyết định thứ tự cả Phase.
2. **Hình dạng `ma_quan`** — chuỗi ngắn (`anh-ba`) hay UUID? Tôi nghiêng về chuỗi ngắn: nó lên tem QR, khách nhìn thấy, người vận hành phải gõ được.
3. **Đường dẫn gọi món** — `/g/{ma_quan}/{public_code}` hay khác? Chốt xong là in tem được và không đổi lại nữa.
4. **Tên bảng cấu hình** — `cau_hinh_quan` hay tên khác?
5. **Có gỡ khoá VietQR khỏi `.env` sản xuất sau 5A.2 không?** Tôi đề nghị có.

---

*Hết văn bản. Chờ duyệt bằng văn bản trước khi mở 5A.0.*
