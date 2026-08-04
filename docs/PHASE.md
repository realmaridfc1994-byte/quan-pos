# PHASE.md — BƯỚC DUY NHẤT ĐANG ĐƯỢC PHÉP LÀM

> Đặt tại `docs/PHASE.md`, ghi đè bản Phase 1.
> **Chỉ chủ dự án được sửa file này.** Claude Code đọc, không ghi.

```
PHASE = 2
BUOC_DANG_MO = 2
```

**Phase 2 — Chuẩn hoá vận hành. Bước 0 — Kiểm toán định danh.**
Được phép: CHỈ BÁO CÁO. Không sửa code, không tạo file ngoài file báo cáo.

**Mọi việc thuộc Bước 1 trở đi: DỪNG và hỏi.**

---

## Tiêu chí Phase 2

**Quản lý đóng ca tự tin không cần đếm tay đối chiếu, và quán vẫn bán được khi wifi rớt 10 phút.**

---

## Bảng tra — việc nào thuộc bước nào

| Bước | Được làm gì | Nghiệm thu |
|---|---|---|
| 0 | Kiểm toán định danh + thứ tự khoá — CHỈ BÁO CÁO | Có `docs/kiem-toan-offline.md` |
| 1 | Tách bàn, chuyển món giữa hai lượt khách | `pos:demo --den=tach-ban` |
| 2 | Định danh do máy POS sinh cho mọi bảng ghi được offline | Test quét toàn bộ endpoint ghi |
| 3 | Kho dữ liệu trên máy POS (Dexie) + hàng chờ gửi | Rút dây mạng, gọi món vẫn được |
| 4 | `POST /sync/batch` + ma trận xử lý xung đột | `pos:demo --den=sync` |
| 5 | Màn hình xử lý xung đột cần người quyết | Bấm tay giải quyết được |
| 6 | Khuyến mãi: giảm %, giảm tiền, giờ vàng | `pos:demo --den=khuyen-mai` |
| 7 | Thanh toán QR (VietQR) | Quét thử bằng app ngân hàng |
| 8 | Bảng tổng hợp ngày + màn hình chủ quán | Xem doanh thu 7 ngày trên điện thoại |
| 9 | Opus review toàn phase | Hết mục 🔴 |

## Bước đã đóng

- [x] Phase 0 — 9 bước, 5 lỗi 🔴 đóng sau 2 vòng review
- [x] Phase 1 — 11 bước, 2 lỗi 🔴 đóng sau vòng review cuối
- [x] Bước 0 — Kiểm toán định danh  ← ĐANG MỞ
- [x] Bước 1 — Tách bàn
- [x] Bước 2 — Định danh client sinh
- [ ] Bước 3 — Kho dữ liệu trên máy POS
- [ ] Bước 4 — Đồng bộ hàng loạt
- [ ] Bước 5 — Màn hình xử lý xung đột
- [ ] Bước 6 — Khuyến mãi
- [ ] Bước 7 — Thanh toán QR
- [ ] Bước 8 — Báo cáo tổng hợp
- [ ] Bước 9 — Opus review
