# PHASE.md — BƯỚC DUY NHẤT ĐANG ĐƯỢC PHÉP LÀM

> Đặt file này tại `docs/PHASE.md`.
> **Chỉ chủ dự án được sửa file này.** Claude Code đọc, không ghi.

```
BUOC_DANG_MO = 3
```

**Bước 3 — Khởi tạo dự án.** Được phép: tạo project Laravel 12, cấu hình `.env`
trỏ MariaDB của XAMPP cổng 3306, lưu `CLAUDE.md` và `docs/schema.md`, tạo thư mục rỗng
`app/Domain/*`, cài 5 package đã chốt, khởi tạo git.

**Mọi việc thuộc Bước 4 trở đi: DỪNG và hỏi.**

---

## Bảng tra — việc nào thuộc bước nào

| Bước | Được làm gì | Nghiệm thu |
|---|---|---|
| 3 | Khởi tạo dự án, `.env`, thư mục rỗng, cài package, git | 4 phép thử MariaDB ĐẠT; `php artisan serve` chạy; `app/Domain` không có file `.php` nào |
| 4 | Migration + Model + Enum + quan hệ | `php artisan migrate` chạy sạch |
| 5 | Đăng nhập, PIN, Policy, activitylog | Test đăng nhập PASS |
| 6 | Middleware Idempotency, class `Money`, base Action | Test idempotency PASS |
| 7 | Seeder, Factory | Xem được 60 món trong database |
| 8 | CI, lệnh `php artisan phase0:check`, README | Lệnh check in toàn ✅ |
| 9 | Opus review — không viết code mới | Hết mục 🔴 |

## Cách dùng

Xong một bước, **tự tay** sửa số trong `BUOC_DANG_MO` rồi lưu file.
Đừng nhờ Claude Code sửa — đây là công tắc duy nhất nằm hoàn toàn trong tay bạn.

## Bước đã đóng

- [x] Bước 1 — Thiết kế database (Opus)
- [x] Bước 2 — Viết `CLAUDE.md` (Opus)
- [ ] Bước 3 — Khởi tạo dự án  ← ĐANG MỞ
- [ ] Bước 4 — Migration + Model
- [ ] Bước 5 — Đăng nhập & phân quyền
- [ ] Bước 6 — Idempotency & Money
- [ ] Bước 7 — Dữ liệu mẫu
- [ ] Bước 8 — CI & lệnh tự kiểm tra
- [ ] Bước 9 — Opus review
