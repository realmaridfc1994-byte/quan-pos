# PHASE.md — BƯỚC DUY NHẤT ĐANG ĐƯỢC PHÉP LÀM

> Đặt tại `docs/PHASE.md`, ghi đè bản Phase 4.
> **Chỉ chủ dự án được sửa file này.** Claude Code đọc, không ghi — sửa lần này là NGOẠI LỆ, làm theo yêu cầu trực tiếp của chủ dự án ngày 12/08/2026 để chỉnh lại số hiệu bước.

```
PHASE = 4
BUOC_DANG_MO = 4B.2
```

# BẢN ĐỒ PHASE 4

## P4-4A.1 — Nền khách hàng — ĐÃ ĐÓNG (12/08/2026)

Bảng `customers` đã tạo và commit (221e497). Sổ cái điểm thưởng tách ra thành P4-4A.2 riêng (xem dưới).

## P4-4A.2 — Sổ cái điểm thưởng (tích điểm) — HOÃN, XOÁ KHỎI BẢN ĐỒ PHASE 4

Không tính vào các bước đang mở. Chi tiết chính sách chưa chốt và quyết định kiến trúc đã chốt: `docs/viec-ton.md` mục "Tích điểm thành viên — HOÃN (12/08/2026)". Code tham khảo giữ nguyên trên branch `park/loyalty-4a1`, không hợp nhất vào `master`.

## P4-4A.3 — Đặt bàn

**Đổi số hiệu ngày 12/08/2026: trước đây gọi nhầm là 4A.2 (số đó thuộc về sổ cái điểm thưởng, xem trên) — đúng ra là 4A.3.** Một commit đã lỡ ghi "P4-4A.2" trong message (06c8502, chỉ sửa `CLAUDE.md` mục 11) — không amend lại, chỉ sửa số hiệu từ đây trở đi.

Trạng thái: **ĐÃ ĐÓNG (13/08/2026)** — commit `ee42321` (bảng, 6 Action, cọc dùng lại `payments`) + commit dấu vết cọc 13/08 (`MarkDepositHandled`, `GetUnhandledDeposits`, `status_reason`). Ba mục "dấu vết cọc" bên dưới đã làm đủ; 55 test nhóm Reservations xanh.

### Trong phạm vi
- Bảng `reservations` (trạng thái pending/confirmed/seated/no_show/cancelled)
- Tiền cọc dùng lại sổ `payments` đã có (thêm `reservation_id`, `table_session_id` nullable)
- `CreateReservation`, `ConfirmReservation`, `SeatReservation`, `MarkNoShow`, `CancelReservation`, `RecordReservationDeposit`

### NGOÀI phạm vi
- SMS/email thông báo, UI, Filament resource
- Dời bàn cho đặt trước sau khi tạo
- Z-report tách dòng tiền cọc

### Chính sách cọc khi no_show — ĐÃ CHỐT 12/08, ĐÃ LÀM XONG 13/08
Hệ thống **không tự quyết** giữ hay hoàn cọc; thu ngân thao tác tay bằng `VoidPayment`. Nhưng phải để lại DẤU VẾT, nếu không ba tháng sau không ai trả lời được "khách này no-show, cọc xử lý chưa?":
- `MarkNoShow` **bắt buộc có lý do**, ghi vào audit log
- `reservations` có trường ghi nhận cọc đã xử lý hay chưa (`đã hoàn` / `giữ lại` / `chưa xử lý`) — **do người thao tác đánh dấu, hệ thống KHÔNG tự suy diễn**
- Có báo cáo liệt kê được các đặt bàn `no_show` còn cọc **chưa xử lý**

### Điều kiện đóng bước
- Toàn bộ test xanh
- Ba mục "dấu vết cọc" ở trên đã làm
- Chủ dự án duyệt xong

## P4-4B.0 — Lãi gộp không còn ngụy trang doanh thu thiếu giá vốn thành lãi 100%

Trạng thái: **ĐÃ ĐÓNG (13/08/2026)** — commit `d2748a6` (`product_profit_daily`) + `ae8f928` (cặp cột cấp ngày ở `daily_summaries`, lệnh `report:backfill-gia-von --thu`, test trên fixture méo). Lệnh backfill **chưa chạy trên database nào** — chờ bản sao dữ liệu thật, xem mục dưới.

### Trong phạm vi
- Sửa `product_profit_daily`: cột `revenue_uncosted_amount`, công thức `profit_amount` trả NULL khi toàn bộ doanh thu chưa có giá vốn
- `SummarizeProductProfit` tính cột mới

### NGOÀI phạm vi
- `GetOwnerProfitDashboard.php` / bất kỳ dashboard nào — thuộc P4-4B.1
- Đổi cách trừ kho của Phase 3
- Backfill tự động trong migration — CẤM, xem quy trình 3 bước ở `docs/viec-ton.md`

### Backfill — HOÃN tới khi có bản sao dữ liệu THẬT (chốt 12/08)
Ba mục kiểm tra (lãi gộp phải giảm, doanh thu phải đứng yên, trường đếm phải khớp) **chỉ có ý nghĩa trên dữ liệu thật, méo thật**. `pos:demo` sinh dữ liệu sạch — chạy trên đó chỉ chứng minh code không crash, không chứng minh nó sửa đúng cái méo. Vì vậy **KHÔNG dựng dữ liệu mẫu để thử**.

Làm được ngay, thay thế:
1. Test tự động trên fixture cố ý dựng MÉO (vài món `has_cost=false`, vài đường phục vụ chưa xác nhận), assert đúng ba mục trên — chạy được trên máy dev và **ở lại repo bảo vệ mãi**, giá trị hơn một lần chạy tay.
2. Command backfill vẫn viết, **chưa chạy**. Có cờ `--thu` (chạy thử): in bảng so sánh trước/sau, **không ghi gì**.

Khi có dữ liệu thật: chạy `--thu` trước, đọc bảng, mới chạy thật, và chạy **TỪNG THÁNG** — không chạy một phát cả năm.

### Điều kiện đóng bước
- Toàn bộ test xanh, gồm test fixture méo ở trên
- Command backfill có `--thu`, đã chứng minh không ghi gì khi chạy thử
- Chủ dự án duyệt xong

## P4-4B.1 — Dashboard lãi gộp/hao hụt cho chủ quán

Trạng thái: CHƯA MỞ — chờ P4-4B.0 đóng trước, vì `GetOwnerProfitDashboard.php` phải đổi cách đọc `product_profit_daily` (đọc `profit_amount`/`revenue_uncosted_amount` trực tiếp, không tự `SUM(revenue) - SUM(cost)` như hiện tại).
