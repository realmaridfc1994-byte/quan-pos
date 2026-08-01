# PHASE.md — BƯỚC DUY NHẤT ĐANG ĐƯỢC PHÉP LÀM

> Đặt file này tại `docs/PHASE.md`, ghi đè bản Phase 0.
> **Chỉ chủ dự án được sửa file này.** Claude Code đọc, không ghi.

```
PHASE = 1
BUOC_DANG_MO = 11
```

**Phase 1 — MVP bán hàng. Bước 1 — Ca làm việc.**
Được phép: Action mở ca / đóng ca / thu chi vặt, API tương ứng, lệnh `pos:demo`, test.

**Mọi việc thuộc Bước 2 trở đi: DỪNG và hỏi.**

---

## Tiêu chí duy nhất của Phase 1

**Bán được một ca thật tại quán, không cần sổ giấy.**

Không kho. Không khuyến mãi. Không khách hàng thân thiết. Không báo cáo đẹp.

---

## Bảng tra — việc nào thuộc bước nào

| Bước | Được làm gì | Bất biến phải giữ | Nghiệm thu |
|---|---|---|---|
| 1 | Ca làm việc: mở, đóng, thu chi vặt. Lệnh `pos:demo` | C1, C2, C6, C7 | `pos:demo --den=ca` chạy sạch |
| 2 | Thực đơn: API menu, quản lý món trên Filament | E1–E6 | Mở Filament sửa được giá món |
| 3 | Bàn & lượt khách: mở bàn, ghép bàn, chuyển bàn, đóng | B1–B6 | `pos:demo --den=ban` |
| 4 | Gọi món: thêm, sửa, xoá món khi chưa gửi bếp | M1–M6, M8, M9 | `pos:demo --den=goi-mon` |
| 5 | Gửi bếp & màn hình bếp | M3, M7, E6 | `pos:demo --den=gui-bep` |
| 6 | Hủy món & duyệt bằng PIN | H1–H6 | `pos:demo --den=huy-mon` |
| 7 | Tính tiền & thu tiền | T1–T9 | `pos:demo --den=thu-tien` |
| 8 | Đóng ca & đối soát két | C3, C4, C5 | `pos:demo` chạy trọn vẹn |
| 9 | In tem bếp, tạm tính, bill | — | In ra giấy thật |
| 10 | Màn hình POS và màn hình bếp | — | Bán thử một bàn bằng tay |
| 11 | Opus review toàn phase | — | Hết mục 🔴 |

## Cách dùng

Xong một bước, **tự tay** sửa số trong `BUOC_DANG_MO` rồi lưu file.
Đừng nhờ Claude Code sửa — đây là công tắc duy nhất nằm hoàn toàn trong tay bạn.

## Bước đã đóng

- [x] Phase 0 — toàn bộ 9 bước, 5 lỗi 🔴 đã đóng sau 2 vòng review
- [ ] Bước 1 — Ca làm việc  ← ĐANG MỞ
- [ ] Bước 2 — Thực đơn
- [ ] Bước 3 — Bàn & lượt khách
- [ ] Bước 4 — Gọi món
- [ ] Bước 5 — Gửi bếp & KDS
- [ ] Bước 6 — Hủy món & duyệt PIN
- [ ] Bước 7 — Tính tiền & thu tiền
- [ ] Bước 8 — Đóng ca & đối soát
- [ ] Bước 9 — In ấn
- [ ] Bước 10 — Màn hình POS & KDS
- [ ] Bước 11 — Opus review
