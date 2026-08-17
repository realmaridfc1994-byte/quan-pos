# BƯỚC 5A.0 — KẾT NỐI TENANT — ✅ ĐÓNG 17/08

Bước đang mở tiếp theo: **5A.1**.

Nguồn: docs/phase-5-huong-dan-thuc-thi-v2.md, mục 1.2

## Được phép chạm
- config/database.php — ✅ xong
- app/Models/BaseModel.php — ✅ xong
- 35 file Model trong `app/Domain/*/Models/` — ✅ **XONG 17/08**
  (34 file `extends BaseModel`; `User` giữ `extends Authenticatable` và khai
  thẳng `protected $connection = 'tenant'`). Đã kiểm bằng máy: 5 Model mẫu kể
  cả `User` đều trả về `getConnectionName() === 'tenant'`.
- SQL thô — ✅ **XONG 17/08**, 7 chỗ thật (không phải 36), chi tiết ở dưới
- tests/Feature/DatabaseDriverTest.php — ⬜ không cần đụng

### Danh sách SQL thô — đã soát và SỬA XONG 17/08, con số 36 là ĐẾM THỪA

Quét cả `app/` được 40 chỗ. Nhưng **33 trong số đó không phải vấn đề kết nối**:
`selectRaw()`, `whereRaw()` gọi trên câu truy vấn của một Model
(`StockMovement::query()->selectRaw(...)`, `ProductProfitDaily::query()->...`)
đi theo đúng kết nối của Model đó — nghĩa là đã sang `tenant` từ commit 1 rồi,
không cần đụng. Chỉ `DB::` gọi thẳng mới bỏ qua Model và rơi về kết nối mặc
định. Còn **đúng 7 chỗ thật**, nằm gọn trong 2 file:

**Phải sửa — chạm bảng nghiệp vụ:**
1. `app/Console/Commands/BackfillStockMovementUuids.php:43` —
   `DB::table('stock_movements')->update(...)`. Đây là ngoại lệ CÓ CHỦ Ý (vá
   uuid cho dữ liệu cũ, cố tình đi vòng qua khoá cấm-sửa-sổ-cái ở tầng Model,
   xem chú thích ngay trên nó). Đi vòng qua Model nghĩa là cũng đi vòng qua
   `tenant` → sau 5B nó sẽ vá nhầm database.
2. `app/Console/Commands/Phase0Check.php:331` — `DB::table('shifts')` kiểm còn
   ca nào đang mở trước khi cho chạy test. Sau 5B nó soi nhầm database và trả
   lời "không có ca nào mở" cho một database khác — đúng loại trả lời sai mà
   vẫn trông đúng.

**Phải sửa — đọc siêu dữ liệu của database, phải soi ĐÚNG database:**
3. `Phase0Check.php:147` — `DB::selectOne(... information_schema.SCHEMATA
   where SCHEMA_NAME = DATABASE())` kiểm bảng mã.
4. `Phase0Check.php:170-171` — `DB::table('information_schema.tables')` +
   `DB::raw('DATABASE()')` đối chiếu danh sách bảng.
5. `Phase0Check.php:193-194` — như trên, cho `information_schema.statistics`.

**Chỗ cần quyết — ĐÃ QUYẾT 17/08:**
6. `Phase0Check.php:112` — `DB::selectOne('select version()')`. **Quyết: đổi
   sang `tenant`.** Lý do: cả lệnh `phase0:check` chỉ soi đúng một thứ — cái
   database đang giữ dữ liệu của quán. Hỏi phiên bản của một kết nối khác với
   kết nối vừa dùng để đếm bảng và đếm bàn thì báo cáo tự mâu thuẫn với chính
   nó. Nay cả 5 phép kiểm trong file đều hỏi cùng một kết nối.

**Dính kèm, sửa luôn một lượt (đã ghi `viec-ton.md` từ 15/08):**
7. `Phase0Check.php:110-111` đọc `config('database.connections.mysql.host')` —
   khối cấu hình của một kết nối KHÔNG AI DÙNG (dự án chạy khối `mariadb`).
   May là hai khối cùng đọc một biến môi trường nên số in ra vẫn đúng; sai ở
   chỗ đọc, không ở chỗ kết quả. Đổi sang đọc khối `tenant`.

### Chốt chặn thêm
`tests/Feature/Support/MoiGiaoDichDeuMoTrenKetNoiTenantTest.php` nay gác HAI
việc, không chỉ một: (a) không ai mở giao dịch trần, (b) không ai chạy
`DB::table()`/`DB::select()` trên kết nối mặc định trong toàn bộ `app/`. Bảy
chỗ vừa sửa vì vậy không tái phát được. Bộ quét cố ý KHÔNG bắt
`selectRaw`/`whereRaw` gọi trên câu truy vấn của Model — chúng đi theo kết nối
của chính Model đó, bắt chúng là báo đỏ 33 chỗ đang đúng.

## Cấm
- Bất kỳ file test cũ nào. Thấy cần sửa test → DỪNG, báo Bin
- Mọi thứ không có trong danh sách trên

## Cổng đóng bước — ✅ ĐẠT
Toàn bộ suite xanh, không sửa một dòng test cũ nào.

**Cổng này chỉ có nghĩa sau khi bước 5A.0-T xong** — còn một test tung đồng xu
trong suite thì "suite xanh" không phân biệt được đỏ-do-mình với đỏ-do-may-rủi.

## Thứ tự chốt (Bin, 17/08) — XONG CẢ 4
1. ✅ Xong 35 Model (commit 1)
2. ✅ Bước 5A.0-T — dọn test đồng xu, PR riêng
3. ✅ Chạy lại cổng — toàn bộ suite 3 lần liên tiếp, xanh cả 3 lần, không sửa
   một dòng test cũ nào ngoài đúng file của bước 5A.0-T
4. ✅ Đóng 5A.0 — 7 chỗ SQL thô đã sửa, cổng chạy lại **840 xanh, 3 lần liên
   tiếp cùng một con số**, Pint sạch, `php artisan phase0:check` chạy đúng như
   trước khi sửa

## Trạng thái khi đóng bước
- 35 Model đi trên `tenant`; `users` là ngoại lệ khai tay, có ghi chú lý do
- 44 chỗ mở giao dịch đều trên `tenant`
- 7 chỗ SQL thô đều trên `tenant`
- 2 bộ quét tự động gác không cho tái phát
- `tenant` và kết nối mặc định VẪN dùng chung một phiên — đây là trạng thái
  ĐÚNG khi đóng 5A.0. Việc tách phiên thuộc Bước 5B, và
  `AppServiceProvider::dungChungMotPhien()` phải còn nguyên cho tới lúc đó.

## Việc 5B thừa hưởng (đã ghi đủ trong docs/viec-ton.md)
- `$connectionsToTransact` trong `tests/TestCase.php`
- 41 chỗ trong `tests/` còn đọc/ghi thẳng qua `DB::` — bộ quét CỐ Ý chỉ soi
  `app/`, không soi `tests/`, vì trong test việc đọc thẳng kết nối mặc định
  hôm nay là hợp lệ. Ngày tách phiên phải rà lại từng chỗ.
- Hai Model của package (`PersonalAccessToken`, `Activity`) ở lại kết nối mặc
  định, không có khoá ngoại thật nên tách phiên sẽ hỏng IM LẶNG
- Duyệt PIN lồng trong giao dịch của `ResolveSyncConflict` — an toàn nhờ người
  gọi, không nhờ thiết kế

---

# BƯỚC 5A.0-T — DỌN TEST TUNG ĐỒNG XU — ✅ XONG 17/08 (PR RIÊNG)

Làm SAU commit 1, TRƯỚC khi đóng 5A.0. Không gộp vào PR nào khác.

## Đã làm
Thay 1 khẳng định tung đồng xu bằng 4 khẳng định tất định, đúng hướng "kiểm
NGUỒN SINH, không kiểm NỘI DUNG chuỗi":
1. `MaBanCongKhai::sinh()` nhận 0 tham số (Reflection) — không thể nhìn thấy id.
2. Gọi `sinh()` hai lần ra hai kết quả khác nhau — không phải hàm tất định của
   bất kỳ thứ gì.
3. Mã đủ độ dài `DO_DAI` và khác mã in trên tem.
4. Hai bàn CÙNG mã tem vẫn ra hai mã công khai khác nhau — mã không suy ra từ
   dữ liệu của chính dòng đó.

Bỏ luôn `not->toContain('B01')` ở dòng cũ — cùng một loại lỗi, chỉ hiếm hơn
4.000 lần (0,008%), và khẳng định số 3 + 4 đã phủ đúng phần luật nó định nói.

Đã quét cả `tests/` tìm lỗi cùng kiểu: **không còn chỗ nào khác**. Bảy chỗ
`toContain` còn lại đều là tìm id trong một MẢNG id (`pluck('id')`) hoặc trong
một chuỗi do code tự ghép — tất định.

## Cổng đóng bước — ✅ ĐẠT
`tests/Feature/Guest/GuestSessionTokenTest.php` chạy 10 lần liên tiếp: xanh cả
10 (25 test, 213 khẳng định, không đổi lần nào).

## Vì sao là một bước riêng chứ không phải việc vặt
Cổng đóng 5A.0 là "toàn bộ suite xanh". Một test tung đồng xu trong suite làm
cổng đó mất nghĩa: đỏ lên thì không ai phân biệt được là do đổi kết nối hay do
đồng xu. Và con số 30,1% nói thêm điều tệ hơn — test đó xanh 70% số lần, tức
suốt thời gian qua nó chưa từng bảo vệ cái gì. Nó không phải test yếu, nó là
test giả.

## Được phép chạm
- `tests/Feature/Guest/GuestSessionTokenTest.php` — ĐÚNG MỘT FILE

## Cấm
- Mọi file khác, kể cả `app/Support/MaBanCongKhai.php`

## Việc phải làm
Dòng 82, khẳng định `public_code` không chứa chuỗi id của bàn. `public_code` là
`Str::random(22)` trên bảng chữ 62 ký tự CÓ CẢ CHỮ SỐ, nên khẳng định đó là tung
đồng xu: đo thật 200.000 lần cho ra 30,1% "vi phạm" khi id có 1 chữ số, 0,54%
khi 2 chữ số.

**Không nới lỏng khẳng định rồi thôi.** Luật nó định diễn đạt là có thật và đáng
giữ: *mã công khai của bàn không được suy ra từ id nội bộ* (chống dò tuần tự).
Chỉ là "chuỗi không chứa chữ số của id" diễn đạt sai luật đó.

Cách diễn đạt đúng: **kiểm NGUỒN SINH mã, không kiểm NỘI DUNG chuỗi** — mã phải
đến từ bộ sinh ngẫu nhiên, và bộ sinh đó không nhận id làm đầu vào. Kiểm như vậy
thì tất định, chạy 200.000 lần vẫn một kết quả.

## Cổng đóng bước
Chạy `tests/Feature/Guest/GuestSessionTokenTest.php` 10 lần liên tiếp, xanh cả 10.

---

# COMMIT 2 — ĐÃ XONG (17/08, Bin duyệt), làm trước commit 1

Đảo thứ tự có chủ ý.

## Đã làm
- 44 chỗ mở giao dịch trong `app/` đổi sang `DB::connection('tenant')->transaction()`
  (42 chỗ `DB::transaction`, 2 chỗ `beginTransaction`/`commit`/`rollBack`).
  Không kéo lệnh nào vào trong hay ra ngoài giao dịch.
- `tests/Feature/Support/MoiGiaoDichDeuMoTrenKetNoiTenantTest.php` gác từ nay:
  cấm mở giao dịch trần trong toàn bộ `app/`.
- Gộp thêm (Bin duyệt): tách bước duyệt PIN ra TRƯỚC `DB::transaction` ở
  `CalculateBill` và `CancelOrderItem` — xem dòng "[ĐÃ XỬ LÝ 17/08]" trong
  `docs/viec-ton.md`. Kèm `tests/Feature/Staffing/PinSaiKhongBiQuayLuiTest.php`.

## Vì sao đảo thứ tự KHÔNG mở ra cửa sổ mất tính nguyên tử
Đã kiểm bằng máy ngày 17/08, không phải bằng lập luận:

```
DB::connection('mariadb') === DB::connection('tenant')   → true
cùng một đối tượng PDO                                    → true
số hiệu phiên MariaDB (connection_id())                   → giống hệt nhau
mở giao dịch trên 'tenant' → transactionLevel của 'mariadb' lên 1
```

`AppServiceProvider::dungChungMotPhien()` đăng ký `tenant` TRẢ VỀ CHÍNH đối tượng
kết nối mặc định — không phải hai kết nối trỏ cùng database, mà là một kết nối
mang hai tên. Nên hôm nay không có "phiên tenant" và "phiên default" nào để lệch
nhau: giao dịch mở ở đâu cũng bọc mọi lệnh ghi, dù Model đã chuyển sang `tenant`
hay chưa. Cửa sổ đó chỉ mở ra vào ngày gỡ `dungChungMotPhien()` (Bước 5B).
