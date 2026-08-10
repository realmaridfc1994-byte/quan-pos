# PHASE.md — BƯỚC DUY NHẤT ĐANG ĐƯỢC PHÉP LÀM

> Đặt tại `docs/PHASE.md`, ghi đè bản Phase 2.
> **Chỉ chủ dự án được sửa file này.** Claude Code đọc, không ghi.

```
PHASE = 3
BUOC_DANG_MO = 9
```

**Phase 3 — Kho và lợi nhuận. Bước 0 — Kiểm toán chuẩn bị kho.**
Được phép: CHỈ BÁO CÁO. Không sửa code, không tạo file ngoài file báo cáo.

**Mọi việc thuộc Bước 1 trở đi: DỪNG và hỏi.**

---

## Tiêu chí Phase 3

**Biết chính xác món nào lãi bao nhiêu, và hao hụt mỗi tháng là bao nhiêu.**

---

## Bảng tra — việc nào thuộc bước nào

| Bước | Được làm gì | Nghiệm thu |
|---|---|---|
| 0 | Kiểm toán chuẩn bị kho — CHỈ BÁO CÁO | Có `docs/kiem-toan-kho.md` |
| 1 | Schema kho: 9 bảng + bất biến nhóm K | Opus duyệt thiết kế |
| 2 | Nguyên liệu, đơn vị, quy đổi nhiều cấp | Nhập được 60 nguyên liệu qua trình duyệt |
| 3 | Định lượng món (BOM) | Xem được 1 lẩu gà ăn hết những gì |
| 4 | Nhập hàng + giá vốn bình quân gia quyền | Nhập 2 lô giá khác nhau, giá vốn đúng |
| 5 | Trừ kho tự động khi món được phục vụ | `pos:demo --den=tru-kho` |
| 6 | Hao hụt, hủy hàng, điều chỉnh | Ghi được 5 lon bia vỡ |
| 7 | Kiểm kê và xử lý chênh lệch | Kiểm kê một vòng, chốt được |
| 8 | Báo cáo lãi gộp theo món, theo ngày | Biết lẩu gà lãi bao nhiêu phần trăm |
| 9 | Job đối soát sổ cái và tồn kho | Cố tình làm lệch, job phát hiện được |
| 10 | Opus review toàn phase | Hết mục 🔴 |

## Bước đã đóng

- [x] Phase 0 — nền móng, 5 lỗi 🔴 đóng sau 2 vòng review
- [x] Phase 1 — MVP bán hàng, 2 lỗi 🔴 đóng
- [x] Phase 2 — offline, đồng bộ, khuyến mãi, 2 lỗi 🔴 đóng
- [x] Bước 0 — Kiểm toán chuẩn bị kho  ← ĐANG MỞ
- [x] Bước 1 — Schema kho
- [x] Bước 2 — Nguyên liệu và đơn vị
- [x] Bước 3 — Định lượng món
- [x] Bước 4 — Nhập hàng và giá vốn
- [x] Bước 5 — Trừ kho tự động
- [x] Bước 6 — Hao hụt và điều chỉnh
- [x] Bước 7 — Kiểm kê
- [x] Bước 8 — Báo cáo lãi gộp
- [ ] Bước 9 — Job đối soát
- [ ] Bước 10 — Opus review
