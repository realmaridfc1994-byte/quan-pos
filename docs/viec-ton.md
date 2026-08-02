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
[Bước 11] Rà lại thứ tự khoá của RecordPayment, CalculateBill, CloseShift cho khớp luật Shift → TableSession → Payment — VoidPayment đã xử lý xong (gộp khoá 2 ca theo id tăng dần trong 1 câu whereIn, ca hiện tại khoá riêng ở bước sau vì id chưa biết trước) — 01/08
[Phase 2] Z-report cột "người duyệt" món huỷ hiện dùng cancelled_by_user_id (người bấm huỷ) — không đổi schema ở P1. Thiết kế cột approved_by ở P2, cùng lúc với luồng duyệt PIN huỷ món sau khi gửi bếp — 01/08
[Bước 9] In tem bếp, tạm tính, bill bằng ESC/POS 80mm — ba mẫu tiếng Việt, Node.js print agent chạy trên LAN, lệnh `pos:print-test`, ghi README — 01/08
[Bước 9-10] Nút "In tạm tính" và "In bill" trên màn POS đang in qua trình duyệt. Cần: (a) endpoint POST /api/v1/table-sessions/{id}/print/{loai} đẩy việc in vào hàng đợi, (b) màn POS gọi endpoint đó thay vì window.print(), (c) xử lý khi print agent không chạy — báo lỗi rõ cho thu ngân, không im lặng — 01/08
[Đã quyết - không làm] PIN chỉ dùng để DUYỆT việc nhạy cảm, không bao giờ dùng để đăng nhập thay người. Đổi ca phải đăng xuất và đăng nhập bằng SĐT + mật khẩu. Lý do: PIN 4 số làm mật khẩu thì nhân viên dò ra sẽ đăng nhập thành quản lý và activity_log ghi nhầm người — 01/08
[Phase 2] Z-report nên hiện riêng dòng "chi vượt tiền thu" để chủ quán biết ca đó phải bù tiền từ ngoài két bao nhiêu — 01/08
[Bước sau] SplitTableSession/MoveOrderItem không tính lại orders.status của phiếu GỐC sau khi dòng món bị chuyển đi hết — có thể để lại một phiếu "đang làm" ảo dù không còn món nào cần làm trên đó — 02/08
[Bước sau] MoveOrderItem/SplitTableSession chỉ chuyển nguyên một dòng order_item (cả số lượng) sang lượt khách khác — chưa hỗ trợ chuyển một phần số lượng (ví dụ 2 trong 5 lon) như CancelOrderItem đã làm được cho huỷ — 02/08
[Bước 4] table_sessions/order_items do SplitTableSession, MoveOrderItem tạo ra (lượt khách mới khi tách, phiếu mới khi gom món theo trạm) và order_items do CancelOrderItem tạo ra khi tách dòng huỷ một phần — CHƯA nhận uuid do máy POS sinh, server vẫn tự tạo nội bộ. Ba Action này là hành động quản trị/nội bộ, không phải luồng "mở bàn – gọi món – thu tiền" chính mà Bước 2 nhắm tới. Cần xem lại khi các Action này phải tự chạy được lúc máy POS offline — 02/08
[Bước 3] Mã lượt khách (table_sessions.code) và mã ca (shifts.code) giờ dùng ID tự tăng thay vì đếm theo ngày (sửa đua tranh Bước 2) — số không còn reset về 0001/01 mỗi ngày như trước, chỉ còn phần ngày-tháng trong mã là có ý nghĩa "hôm nay". Cần thông báo cho chủ quán biết nếu có thói quen đọc số thứ tự trong mã như "lượt khách thứ mấy hôm nay" — 02/08
[Bước sau] SplitTableSession::sinhMaLuotKhach() (viết riêng ở Bước 1, bản sao của OpenTableSession::sinhMaLuotKhach()) VẪN dùng count()+1 — CÙNG LỖI đua tranh mã vừa sửa ở Bước 2 cho OpenTableSession/OpenShift, nhưng đề bài Bước 2 chỉ nêu đích danh hai chỗ đó nên chưa sửa ở đây. Hai người tách bàn cùng giây có thể ra cùng mã lượt khách mới — 02/08