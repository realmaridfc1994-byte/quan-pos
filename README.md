# POS Quán Nhậu — Hướng dẫn dùng cho chủ quán

File này viết cho người **không biết code**. Cứ làm theo từng bước, gõ đúng từng lệnh là được.

Mọi lệnh dưới đây gõ vào cửa sổ dòng lệnh (Terminal / PowerShell / Git Bash), đứng tại thư mục dự án (`pos-quan`).

---

## 1. Khởi động hệ thống

Cần bật đủ 3 thứ theo đúng thứ tự: **database → máy chủ web → giao diện**.

### Bước 1 — Bật database (MariaDB qua XAMPP)

Mở **XAMPP Control Panel**, bấm **Start** ở dòng `MySQL`. Đèn xanh là được — dữ liệu quán (bàn, món, hoá đơn...) nằm trong này.

> Quán đang dùng MariaDB có sẵn trong XAMPP ở cổng 3306, không phải MySQL Docker. Nếu sau này chuyển sang chạy bằng Docker (`docker compose up -d`), phải đổi cấu hình `.env` sang cổng 3307 — việc này cần hỏi người phụ trách kỹ thuật trước.

### Bước 2 — Bật máy chủ web (API)

```bash
php artisan serve
```

Lệnh này bật "bộ não" xử lý dữ liệu — mở bàn, gọi món, thu tiền... Cửa sổ này **phải để yên, không được tắt** trong lúc quán đang bán hàng. API chạy ở `http://localhost:8000`.

### Bước 3 — Bật giao diện (màn hình bấm chọn)

Mở một cửa sổ dòng lệnh **khác** (đừng tắt cửa sổ Bước 2), rồi gõ:

```bash
npm run dev
```

Lệnh này bật màn hình để bấm chọn món, thu tiền... Cửa sổ này cũng phải để yên khi đang bán hàng.

### Nếu cần in tem bếp/quầy chạy nền

Mở thêm một cửa sổ nữa:

```bash
php artisan queue:listen --tries=1
```

---

## 2. Cách tắt hệ thống

1. Vào từng cửa sổ dòng lệnh đang chạy `php artisan serve`, `npm run dev`, `php artisan queue:listen` — bấm `Ctrl + C` để dừng.
2. Mở XAMPP Control Panel, bấm **Stop** ở dòng `MySQL`.

Tắt theo thứ tự nào cũng được, không sợ mất dữ liệu — dữ liệu đã lưu trong database rồi, tắt web/giao diện không xoá gì cả.

---

## 3. Cách reset dữ liệu về trạng thái mẫu ban đầu

⚠️ **Việc này xoá sạch toàn bộ dữ liệu thật đang có** (bàn đang mở, hoá đơn, tiền đã thu...) và thay bằng dữ liệu mẫu để demo/test. **Không bao giờ chạy lệnh này khi quán đang hoạt động thật.**

```bash
php artisan migrate:fresh --seed
```

Lệnh này dựng lại toàn bộ database trắng tinh, rồi nạp:
- 4 tài khoản mẫu (xem mục 5)
- 12 bàn mẫu
- Thực đơn mẫu (các món nhắm, nướng, lẩu, hải sản, đồ uống...)

Nếu chỉ muốn nạp lại dữ liệu mẫu mà **không** xoá cấu trúc bảng (hiếm khi cần):

```bash
php artisan db:seed
```

---

## 4. Cách xem log khi có lỗi

Log là "sổ nhật ký" ghi lại mọi lỗi hệ thống gặp phải. Khi màn hình báo lỗi hoặc có gì bất thường, xem log để biết chuyện gì xảy ra.

File log nằm ở: `storage/logs/laravel.log`

Mở file này bằng Notepad hoặc bất kỳ trình soạn thảo nào. Lỗi mới nhất nằm ở **cuối file**.

Nếu muốn xem log chạy trực tiếp trên màn hình (tiện khi đang thao tác thử):

```bash
php artisan pail
```

Gặp lỗi thì **chụp màn hình hoặc copy đoạn lỗi cuối file**, gửi cho người phụ trách kỹ thuật — đừng tự sửa file này.

---

## 5. Cách chạy kiểm tra hệ thống (phase0:check)

Đây là lệnh kiểm tra tổng quát xem hệ thống có đang chạy đúng không — database, dữ liệu mẫu, các quy tắc chống lỗi... Chạy khi:
- Vừa cài đặt xong, muốn chắc chắn mọi thứ ổn.
- Nghi ngờ có gì đó sai nhưng không biết ở đâu.

```bash
php artisan phase0:check
```

Kết quả in ra từng dòng ✅ (đạt) hoặc ❌ (chưa đạt), tiếng Việt, dễ đọc. Cuối cùng có dòng tổng kết `PHASE 0 HOÀN TẤT ✅` hoặc `CÒN X MỤC CHƯA XONG ❌`.

⚠️ **Lưu ý quan trọng**: lệnh này có chạy toàn bộ test tự động ở bước cuối, và bước đó sẽ **xoá sạch dữ liệu đang có rồi dựng lại database trắng** (phục vụ việc kiểm tra, không tránh được). Sau khi chạy `phase0:check` xong, nhớ chạy lại:

```bash
php artisan db:seed
```

để có lại dữ liệu mẫu (bàn, món, tài khoản).

---

## 6. Thông tin đăng nhập 4 tài khoản mẫu

Sau khi chạy `migrate:fresh --seed` hoặc `db:seed`, hệ thống có sẵn 4 tài khoản:

| Vai trò | Tên đăng nhập | Mật khẩu | Mã PIN |
|---|---|---|---|
| Chủ quán | `owner` | `password` | `1234` |
| Quản lý | `manager` | `password` | `1234` |
| Phục vụ | `staff` | `password` | `1234` |
| Bếp | `kitchen` | `password` | `1234` |

Mã PIN dùng để đăng nhập nhanh trên máy tính bảng tại quầy (không cần gõ mật khẩu dài mỗi lần đổi ca).

**Đây là tài khoản demo, mật khẩu đơn giản có chủ đích.** Trước khi dùng thật ở quán, phải đổi mật khẩu từng tài khoản — hỏi người phụ trách kỹ thuật cách đổi.

---

## 7. Khi gặp lỗi thì làm gì

### Lỗi 1: Mở trang web báo "Không kết nối được database" / "Connection refused"

**Nguyên nhân**: MySQL trong XAMPP chưa bật.

**Cách xử lý**: Mở XAMPP Control Panel, kiểm tra dòng `MySQL` có đèn xanh chưa. Nếu chưa, bấm **Start**. Đợi vài giây rồi tải lại trang.

### Lỗi 2: Bấm nút gì cũng không phản hồi, màn hình trắng hoặc treo

**Nguyên nhân**: Cửa sổ chạy `php artisan serve` hoặc `npm run dev` đã bị tắt hoặc bị lỗi.

**Cách xử lý**: Kiểm tra 2 cửa sổ dòng lệnh đó còn mở không, có dòng chữ đỏ báo lỗi không. Nếu bị tắt, mở lại và chạy lại lệnh (xem mục 1). Nếu có lỗi đỏ, copy lại gửi người phụ trách kỹ thuật.

### Lỗi 3: Thu tiền / gọi món báo "Dữ liệu gửi lên không hợp lệ" hoặc lỗi màu đỏ khác

**Nguyên nhân**: Thường là do nghiệp vụ — ví dụ thu tiền vượt quá số còn thiếu, mở bàn đã có khách, chưa mở ca mà đã thu tiền.

**Cách xử lý**: Đọc kỹ dòng chữ thông báo trên màn hình — hệ thống luôn giải thích rõ lý do bằng tiếng Việt (ví dụ: "Chưa mở ca. Phải mở ca trước khi thu tiền."). Làm đúng theo hướng dẫn đó. Nếu thông báo khó hiểu, chụp màn hình gửi người phụ trách kỹ thuật.

### Lỗi 4: Bấm thu tiền hoặc gửi bếp hai lần liền, sợ bị tính tiền/lên món hai lần

**Không cần lo.** Hệ thống đã có cơ chế chống trùng: bấm lại vì mạng lag hoặc tay run thì chỉ tính một lần duy nhất, không cộng dồn. Cứ yên tâm thao tác lại nếu thấy màn hình đứng lâu.

### Lỗi 5: Chạy `phase0:check` hoặc bật hệ thống mà báo "đủ vai trò/bàn/món chưa đúng"

**Nguyên nhân**: Dữ liệu mẫu chưa được nạp, hoặc vừa bị xoá (ví dụ vừa chạy test).

**Cách xử lý**: Chạy lệnh sau rồi thử lại:

```bash
php artisan db:seed
```

Nếu vẫn báo thiếu, chạy `php artisan phase0:check` lại để xem chính xác đang thiếu gì rồi báo người phụ trách kỹ thuật.

---

### Vẫn không xử lý được?

Chụp lại toàn bộ màn hình lỗi (kể cả dòng chữ đỏ nếu có) và nội dung cuối file `storage/logs/laravel.log`, gửi cho người phụ trách kỹ thuật. Đừng tự sửa file trong dự án nếu không chắc — dễ làm hỏng thêm.
