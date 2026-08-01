# VIEC-TON.md — Việc phát hiện ra nhưng thuộc bước sau

> Đặt file này tại `docs/viec-ton.md`.
> Claude Code ghi vào đây khi thấy việc cần làm nhưng ngoài phạm vi bước đang mở.
> Ghi xong là hết trách nhiệm — **không làm, không xin phép làm luôn**.

Mẫu một dòng:

```
[Bước dự kiến] Mô tả việc — lý do cần làm — ngày ghi
```

Ví dụ:

```
[Bước 6] Cần rate limit cho endpoint pin-verify — PIN 4 số dễ bị dò — 31/07
[Bước 4] Cột order_items.note nên có độ dài tối đa — tránh nhân viên dán cả đoạn — 31/07
```

---

## Danh sách

<!-- Claude Code thêm dòng mới ở dưới đây -->
[Phase 4] Máy đặt ở quán: đặt APP_ENV=production, APP_DEBUG=false trong .env — 31/07
[Phase 1] option_groups chưa có ràng buộc chống trùng (name, product_id, category_id) — cần cột sinh tự động như uq_tst_one_session_per_table. Hiện chỉ dựa vào quy ước trong seeder — 31/07
[Bước 8] CloseShift đang dò lượt khách mở bằng truy vấn trực tiếp — thay bằng Action của Bước 3 khi có — 01/08
[Bước sau] Product.image_path trong Filament chỉ có ô nhập đường dẫn text — chưa có upload ảnh thật, cần làm khi có yêu cầu — 01/08
[Bước 10] Màn hình POS: khi bàn chính đổi do nhả bàn, phải hiện thông báo cho nhân viên biết lượt khách giờ gọi theo tên bàn nào — 01/08
[Bước 7] CloseTableSession chưa kiểm T6 (chỉ đóng khi paid_amount >= total_amount) — nối tiếp khi có bảng thanh toán — 01/08
[Phase 2] order_items chưa có trạng thái "bếp làm xong, chờ bưng ra" — hiện chỉ ordered → served. Cần khi quán đông, muốn biết món nào nằm chờ quá lâu ở cửa sổ — 01/08
[Bước 10] Màn hình POS phải cảnh báo rõ trước khi bấm Gửi bếp: gửi rồi chỉ hủy được kèm lý do, không sửa được — 01/08
[Bước sau] VoidTableSession chưa huỷ dây chuyền orders/order_items đang mở của lượt khách — cân nhắc khi có yêu cầu rõ — 01/08
[Bước sau] Đơn hàng không tự chuyển "served" nếu dòng món cuối cùng còn lại bị huỷ thay vì phục vụ xong — cần xem lại khi làm màn hình bếp thật — 01/08
[Bước 11] Bất biến B2 có một ngoại lệ hợp lệ: lượt khách mở lại sau khi hủy phiếu thu có thể không chiếm bàn nào, vì bàn cũ có thể đã có khách khác. Cần ghi vào docs/schema.md khi Opus review toàn phase — 01/08
[Bước 10] Màn hình POS cần một chỗ hiện "hoá đơn chờ thu, không còn bàn" — nếu chỉ hiện theo sơ đồ bàn thì hoá đơn này biến mất khỏi màn hình, thu ngân không tìm thấy để thu tiếp — 01/08
[Bước 11] Cân nhắc thêm CHECK ck_table_sessions_not_overpaid (total_amount >= paid_amount) ở tầng DB — hiện chỉ chặn ở tầng code — 01/08
[Bước 8] Z-report phải hiện riêng các khoản hoàn tiền phiếu thu ca cũ, tách khỏi thu chi vặt thường — để chủ quán tra được vì sao két lệch — 01/08
[Bước 8] Z-report đếm "số lượt khách trong ca" phải xử lý được lượt khách chuyển ca do huỷ phiếu thu — nó mở ở ca này nhưng thu tiền ở ca khác — 01/08
[Bước 11] Rà lại thứ tự khoá của RecordPayment, VoidPayment, CalculateBill, CloseShift cho khớp luật Shift → TableSession → Payment — 01/08