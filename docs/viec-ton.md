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