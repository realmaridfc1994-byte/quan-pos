# HƯỚNG DẪN CHẠY DATABASE BẰNG DOCKER

Tài liệu này viết cho người **không phải lập trình viên**. Anh chỉ cần gõ đúng
mấy dòng lệnh bên dưới, không cần hiểu bên trong nó làm gì.

---

## 1. Docker là cái gì, nói cho dễ hiểu

Hãy hình dung Docker là một **cái tủ đông chuyên dụng**.

Trước đây muốn dùng MySQL, anh phải cài nó vào máy như cài một phần mềm — nó lẫn vào
hệ thống, đụng độ với phần mềm khác, gỡ ra không sạch. XAMPP trên máy anh chính là kiểu đó.

Docker thì khác: MySQL nằm gọn trong một cái hộp riêng, cắm điện thì chạy, rút điện thì
tắt, vứt đi thì máy sạch bong như chưa từng có. Nó không đụng gì tới XAMPP đang có sẵn.

---

## 2. Ba câu lệnh cần nhớ

Mở **PowerShell** tại thư mục `C:\Users\Administrator\quan-pos` rồi gõ:

| Việc cần làm | Câu lệnh |
|---|---|
| **Bật database lên** (mỗi lần bắt đầu làm việc) | `docker compose up -d` |
| **Tắt đi** (giữ nguyên dữ liệu) | `docker compose down` |
| **Xem đang chạy không** | `docker compose ps` |

Lần đầu chạy sẽ hơi lâu (khoảng một phút) vì máy phải tải MySQL về. Các lần sau chỉ vài giây.

> **Câu lệnh nguy hiểm — đọc kỹ trước khi dùng**
>
> `docker compose down -v`
>
> Chữ `-v` ở cuối nghĩa là **xoá sạch toàn bộ dữ liệu**: mọi hoá đơn, mọi ca, mọi bàn.
> Không khôi phục được. Chỉ dùng khi anh cố ý muốn làm lại từ số không.

---

## 3. Sau khi bật lên thì có gì

### phpMyAdmin — cửa sổ nhìn vào database bằng chuột

Mở trình duyệt vào: **http://localhost:8080**

Đây là giao diện bấm chuột để xem dữ liệu, không cần gõ lệnh. Anh sẽ thấy 15 bảng,
bấm vào từng bảng để xem bên trong có gì. Rất tiện để tự kiểm tra
*"đơn hàng lúc nãy đã vào chưa"*.

Nó tự đăng nhập sẵn, không cần nhập mật khẩu.

### MySQL — nơi Laravel kết nối vào

| Thông tin | Giá trị |
|---|---|
| Máy chủ | `127.0.0.1` |
| Cổng | **`3307`** |
| Tên database | `quan_pos` |
| Tài khoản | `quanpos` |
| Mật khẩu | `quanpos_secret` |
| Tài khoản quản trị | `root` / `quanpos_root_2026` |

**Vì sao cổng 3307 mà không phải 3306?** Cổng 3306 đang bị XAMPP (MariaDB) trên máy anh
giữ chỗ. Dùng 3307 để hai thứ sống chung, anh không phải gỡ XAMPP đi.

### Cấu hình cho Laravel

Dán đoạn này vào file `.env` của dự án Laravel:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3307
DB_DATABASE=quan_pos
DB_USERNAME=quanpos
DB_PASSWORD=quanpos_secret
```

---

## 4. Điều quan trọng nhất cần hiểu: file SQL chỉ chạy MỘT LẦN

Hai file trong `docker/mysql/init/` (`01-schema.sql` tạo bảng, `02-seed-demo.sql` tạo
dữ liệu mẫu) chỉ được chạy **đúng một lần duy nhất**: lúc database được sinh ra lần đầu.

Nghĩa là nếu sau này anh sửa `01-schema.sql`, rồi tắt bật lại container, **sẽ không có gì
thay đổi cả** — vì database đã tồn tại rồi, MySQL không chạy lại file đó nữa.

Có hai cách xử lý, chọn theo tình huống:

**Cách 1 — Đang thiết kế, chưa có dữ liệu thật (dùng bây giờ):**
```
docker compose down -v
docker compose up -d
```
Xoá sạch làm lại từ đầu. Nhanh, gọn. Chỉ dùng khi trong database chưa có gì đáng tiếc.

**Cách 2 — Đã chạy thật, có hoá đơn của khách (dùng về sau):**
Không đụng vào file SQL nữa. Mọi thay đổi cấu trúc đều đi qua **migration của Laravel**,
để dữ liệu cũ được giữ nguyên. Đây là cách làm chuẩn khi hệ thống đã vận hành.

---

## 5. Dữ liệu mẫu có sẵn

Để anh mở phpMyAdmin lên là thấy ngay chứ không nhìn vào bảng trống:

- **5 nhân viên**: chủ quán, thu ngân Hạnh, phục vụ Nam, phục vụ Lan, bếp trưởng
- **8 bàn**: Bàn 1–4 (trong nhà), Sân 1–3, Phòng VIP
- **5 nhóm món**: Bia, Nước ngọt (ra quầy) · Mồi khô, Món nướng, Lẩu (xuống bếp)
- **7 món, 12 biến thể**: Tiger lon 25k / chai 27k / thùng 550k, Lẩu Thái nồi nhỏ 250k / nồi lớn 380k...
- **3 nhóm tùy chọn, 9 tùy chọn**: Đá (áp cả nhóm Nước ngọt), Độ cay và Ăn thêm (áp riêng Lẩu Thái)

> **Mật khẩu của 5 tài khoản mẫu đều là `123456`.**
> Đây là dữ liệu để nghịch thử. Trước khi mở quán bán thật, **bắt buộc** xoá file
> `02-seed-demo.sql` và tạo tài khoản thật với mật khẩu thật.

---

## 6. Kết quả kiểm tra thực tế

Toàn bộ cấu trúc đã được chạy thử trên MySQL 8.4.11 trong chính môi trường Docker này,
ngày 30/07/2026:

| Hạng mục | Kết quả |
|---|---|
| 15 bảng | Tạo thành công |
| 30 khoá ngoại | Tạo thành công |
| 17 ràng buộc kiểm tra | Tạo thành công |
| Tiếng Việt có dấu | Hiển thị đúng: "Lẩu Thái hải sản", "Gà nướng muối ớt" |
| Múi giờ | +07:00 |
| Chế độ nghiêm ngặt | Đã bật |

**27 tình huống nghiệp vụ đã thử, tất cả cho kết quả đúng như thiết kế:**

*Nhóm chống sai sót do hai người thao tác cùng lúc*
- Nam chiếm bàn B03 → Lan mở đúng bàn B03 → **bị chặn**
- Nam ghép thêm bàn B04 vào cùng lượt khách → **cho phép**
- Đóng bàn, nhả B03 + B04 → Lan mở lại B03 → **cho phép** (bàn đã trống)
- Mở ca thứ hai khi ca cũ chưa đóng → **bị chặn**
- Máy POS gửi lại đúng phiếu vì mạng lag → **bị chặn**, bếp chỉ nhận một tem
- Thu ngân bấm thu tiền hai lần → **bị chặn**

*Nhóm bảo vệ tiền*
- 5 lon Tiger × 25.000đ → máy tự tính 125.000đ, không ai gõ tay được
- Lẩu Thái 250.000đ + thêm mì 15.000đ → tự ra 265.000đ
- Giảm giá mà không ghi lý do → **bị chặn**
- Giảm giá nhiều hơn tổng bill → **bị chặn**
- Khách đưa 200k, ghi nhận 120k, thối nhầm 50k → **bị chặn** (phải là 80k)
- Chuyển khoản mà lại có tiền thối → **bị chặn**
- Đóng bàn khi khách chưa trả đủ → **bị chặn**
- Đóng ca mà không nhập số tiền đếm được → **bị chặn**
- Ghi khoản chi mà không nói chi vào việc gì → **bị chặn**

*Nhóm "không có cục tẩy"*
- Hủy món mà không ghi ai hủy / vì sao → **bị chặn**
- Hủy món có đủ thông tin → cho phép, và **dòng món vẫn nằm nguyên trong sổ**
- Hủy 1 trong 5 lon → tách thành dòng 4 lon + dòng 1 lon đã hủy, tổng vẫn là 5
- Xoá cứng một món đã từng bán → **bị chặn**

---

## 7. Khi có trục trặc

| Hiện tượng | Cách xử lý |
|---|---|
| `docker: command not found` | Docker Desktop chưa chạy. Mở Docker Desktop lên, chờ biểu tượng cá voi hết nhấp nháy. |
| `port is already allocated` | Có thứ khác đang chiếm cổng 3307 hoặc 8080. Sửa số cổng trong `docker-compose.yml` (đổi `"3307:3306"` thành `"3308:3306"` chẳng hạn), rồi nhớ sửa `DB_PORT` trong `.env` cho khớp. |
| phpMyAdmin báo không kết nối được | MySQL chưa khởi động xong. Chờ khoảng 40 giây rồi tải lại trang. |
| Sửa file SQL mà không thấy đổi gì | Bình thường — xem lại mục 4 ở trên. |
| Muốn xem MySQL đang kêu ca gì | `docker compose logs mysql` |
| Muốn gõ lệnh SQL trực tiếp | `docker exec -it quanpos-mysql mysql -uroot -pquanpos_root_2026 quan_pos` |

---

## 8. Trước khi mở quán bán thật — danh sách phải làm

Cấu hình hiện tại dành cho **máy của anh, lúc đang xây dựng**. Mật khẩu để đơn giản
cho dễ gõ. Trước khi đưa vào chạy thật ngoài quán, bắt buộc làm đủ những việc sau:

- [ ] Đổi toàn bộ mật khẩu trong `docker-compose.yml` sang mật khẩu mạnh
- [ ] Xoá file `docker/mysql/init/02-seed-demo.sql` (dữ liệu mẫu và mật khẩu `123456`)
- [ ] **Tắt phpMyAdmin đi** — nó cho phép sửa thẳng vào dữ liệu, không nên để ai chạm được
- [ ] Không mở cổng 3307 ra ngoài mạng internet
- [ ] **Sao lưu tự động hàng ngày** — đây là việc quan trọng nhất. Toàn bộ doanh thu của quán
      nằm trong một cái thùng chứa duy nhất; ổ cứng hỏng là mất trắng. Nếu anh muốn,
      tôi dựng luôn script sao lưu tự động ra thư mục khác hoặc lên Google Drive.
