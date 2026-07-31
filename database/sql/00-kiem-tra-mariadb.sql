-- ═══════════════════════════════════════════════════════════════════════
-- KIỂM CHỨNG MARIADB 10.4.32 (XAMPP 8.2.12)
-- Đặt file tại: database/sql/00-kiem-tra-mariadb.sql
--
-- CHẠY FILE NÀY TRƯỚC KHI VIẾT MIGRATION ĐẦU TIÊN.
--
-- Mục đích: schema được thiết kế và kiểm chứng trên MySQL 8. MariaDB gần
-- giống nhưng không giống hoàn toàn. File này chứng minh bốn thứ quan trọng
-- nhất vẫn hoạt động đúng trên MariaDB, TRƯỚC khi bạn xây 15 bảng lên đó.
--
-- CÁCH CHẠY (người không phải coder làm được):
--   1. Mở http://localhost/phpmyadmin
--   2. Bấm tab SQL
--   3. Dán TỪNG KHỐI một (khối = phần giữa hai dòng ───), bấm Go
--   4. Đối chiếu với "MONG ĐỢI" ghi ngay trên khối đó
--
--   Quan trọng: vài khối CỐ TÌNH gây lỗi. Lỗi ở đó là ĐẠT, không phải hỏng.
--   Đừng dán cả file một lượt — lỗi cố ý sẽ chặn các khối sau.
--
-- KẾT LUẬN: cả 4 phép thử ĐẠT thì đi tiếp. Trượt một phép thì DỪNG.
-- ═══════════════════════════════════════════════════════════════════════


-- ───────────────────────────────────────────────────────────────────────
-- KHỐI 0 — Chuẩn bị
-- MONG ĐỢI: chạy xong không lỗi, cột phien_ban hiện "10.4.32-MariaDB"
-- ───────────────────────────────────────────────────────────────────────
DROP DATABASE IF EXISTS kiemtra_mariadb;
CREATE DATABASE kiemtra_mariadb
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE kiemtra_mariadb;

SELECT VERSION() AS phien_ban, @@sql_mode AS che_do_sql;


-- ═══════════════════════════════════════════════════════════════════════
-- PHÉP THỬ 1 — CHỐT "MỘT BÀN CHỈ THUỘC MỘT LƯỢT KHÁCH ĐANG MỞ"
-- Đây là chốt chặn quan trọng nhất hệ thống.
-- ═══════════════════════════════════════════════════════════════════════

-- ───────────────────────────────────────────────────────────────────────
-- KHỐI 1A — Tạo bảng thử
-- MONG ĐỢI: tạo thành công, không lỗi.
--   Nếu lỗi ở đây => MariaDB không cho khoá duy nhất trên cột sinh tự động
--   => PHÉP THỬ 1 TRƯỢT, dừng lại ngay.
-- ───────────────────────────────────────────────────────────────────────
CREATE TABLE thu_ban_luot_khach (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    table_session_id    BIGINT UNSIGNED NOT NULL,
    dining_table_id     BIGINT UNSIGNED NOT NULL,
    detached_at         TIMESTAMP NULL,

    -- Bàn đang bị chiếm => bằng số hiệu bàn. Bàn đã nhả => rỗng (NULL).
    occupied_table_id   BIGINT UNSIGNED
        GENERATED ALWAYS AS (IF(detached_at IS NULL, dining_table_id, NULL)) STORED,

    PRIMARY KEY (id),
    UNIQUE KEY uq_thu_one_session_per_table (occupied_table_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ───────────────────────────────────────────────────────────────────────
-- KHỐI 1B — Nam mở bàn 5
-- MONG ĐỢI: 1 row inserted. Không lỗi.
-- ───────────────────────────────────────────────────────────────────────
INSERT INTO thu_ban_luot_khach (table_session_id, dining_table_id, detached_at)
VALUES (101, 5, NULL);


-- ───────────────────────────────────────────────────────────────────────
-- KHỐI 1C — Lan cũng bấm mở bàn 5 (cùng lúc)
-- MONG ĐỢI: ❗ PHẢI BÁO LỖI ❗
--   Lỗi mong đợi: "Duplicate entry '5' for key 'uq_thu_one_session_per_table'"
--
--   CÓ LỖI = ✅ ĐẠT. Database đã chặn được hai lượt khách trên một bàn.
--   KHÔNG LỖI = ❌ TRƯỢT. Dừng lại, báo ngay, đừng xây tiếp.
-- ───────────────────────────────────────────────────────────────────────
INSERT INTO thu_ban_luot_khach (table_session_id, dining_table_id, detached_at)
VALUES (202, 5, NULL);


-- ───────────────────────────────────────────────────────────────────────
-- KHỐI 1D — Nam trả bàn 5, sau đó Lan mở lại bàn 5
-- MONG ĐỢI: cả hai câu chạy được, không lỗi.
--   Chứng minh bàn nhả ra rồi thì dùng lại được cho lượt khách mới.
-- ───────────────────────────────────────────────────────────────────────
UPDATE thu_ban_luot_khach SET detached_at = NOW() WHERE table_session_id = 101;

INSERT INTO thu_ban_luot_khach (table_session_id, dining_table_id, detached_at)
VALUES (202, 5, NULL);


-- ───────────────────────────────────────────────────────────────────────
-- KHỐI 1E — Xem kết quả
-- MONG ĐỢI: 2 dòng.
--   Dòng 101: detached_at có giờ, occupied_table_id = NULL
--   Dòng 202: detached_at NULL,   occupied_table_id = 5
-- ───────────────────────────────────────────────────────────────────────
SELECT id, table_session_id, dining_table_id, detached_at, occupied_table_id
FROM thu_ban_luot_khach ORDER BY id;


-- ═══════════════════════════════════════════════════════════════════════
-- PHÉP THỬ 2 — CHỐT "MỘT LÚC CHỈ MỘT CA ĐANG MỞ"
-- ═══════════════════════════════════════════════════════════════════════

-- ───────────────────────────────────────────────────────────────────────
-- KHỐI 2A — Tạo bảng ca làm việc
-- MONG ĐỢI: tạo thành công, không lỗi.
-- ───────────────────────────────────────────────────────────────────────
CREATE TABLE thu_ca (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     BIGINT UNSIGNED NOT NULL,
    status      ENUM('open','closed') NOT NULL DEFAULT 'open',
    opened_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    closed_at   TIMESTAMP NULL,

    open_guard  TINYINT UNSIGNED
        GENERATED ALWAYS AS (IF(status = 'open', 1, NULL)) STORED,

    PRIMARY KEY (id),
    UNIQUE KEY uq_thu_only_one_open (open_guard)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ───────────────────────────────────────────────────────────────────────
-- KHỐI 2B — Thu ngân ca sáng mở ca
-- MONG ĐỢI: 1 row inserted, không lỗi.
-- ───────────────────────────────────────────────────────────────────────
INSERT INTO thu_ca (user_id, status) VALUES (1, 'open');


-- ───────────────────────────────────────────────────────────────────────
-- KHỐI 2C — Thu ngân ca tối mở ca trong khi ca sáng chưa đóng
-- MONG ĐỢI: ❗ PHẢI BÁO LỖI ❗ "Duplicate entry '1' for key 'uq_thu_only_one_open'"
--   CÓ LỖI = ✅ ĐẠT.   KHÔNG LỖI = ❌ TRƯỢT.
-- ───────────────────────────────────────────────────────────────────────
INSERT INTO thu_ca (user_id, status) VALUES (2, 'open');


-- ───────────────────────────────────────────────────────────────────────
-- KHỐI 2D — Đóng ca sáng rồi mở ca tối
-- MONG ĐỢI: cả hai câu chạy được, không lỗi.
-- ───────────────────────────────────────────────────────────────────────
UPDATE thu_ca SET status = 'closed', closed_at = NOW() WHERE user_id = 1;
INSERT INTO thu_ca (user_id, status) VALUES (2, 'open');


-- ═══════════════════════════════════════════════════════════════════════
-- PHÉP THỬ 3 — RÀNG BUỘC KIỂM TRA (CHECK) CÓ ĐƯỢC THI HÀNH KHÔNG
-- Đây là thứ bảo vệ các quy tắc tiền bạc.
-- ═══════════════════════════════════════════════════════════════════════

-- ───────────────────────────────────────────────────────────────────────
-- KHỐI 3A — Tạo bảng phiếu thu
-- MONG ĐỢI: tạo thành công, không lỗi.
-- ───────────────────────────────────────────────────────────────────────
CREATE TABLE thu_phieu_thu (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    amount      BIGINT UNSIGNED NOT NULL COMMENT 'Số tiền (đồng)',
    method      ENUM('cash','transfer') NOT NULL,
    received    BIGINT UNSIGNED NULL COMMENT 'Khách đưa (chỉ tiền mặt)',

    PRIMARY KEY (id),
    CONSTRAINT ck_thu_amount CHECK (amount > 0),
    CONSTRAINT ck_thu_cash   CHECK (
        method <> 'cash' OR (received IS NOT NULL AND received >= amount)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ───────────────────────────────────────────────────────────────────────
-- KHỐI 3B — Phiếu thu hợp lệ: khách trả 200.000, đưa 200.000
-- MONG ĐỢI: 1 row inserted, không lỗi.
-- ───────────────────────────────────────────────────────────────────────
INSERT INTO thu_phieu_thu (amount, method, received) VALUES (200000, 'cash', 200000);


-- ───────────────────────────────────────────────────────────────────────
-- KHỐI 3C — Phiếu thu số tiền bằng 0
-- MONG ĐỢI: ❗ PHẢI BÁO LỖI ❗ "CONSTRAINT `ck_thu_amount` failed"
--   CÓ LỖI = ✅ ĐẠT.   KHÔNG LỖI = ❌ TRƯỢT — ràng buộc CHECK bị bỏ qua.
-- ───────────────────────────────────────────────────────────────────────
INSERT INTO thu_phieu_thu (amount, method, received) VALUES (0, 'cash', 0);


-- ───────────────────────────────────────────────────────────────────────
-- KHỐI 3D — Thu tiền mặt 200.000 nhưng khách chỉ đưa 100.000
-- MONG ĐỢI: ❗ PHẢI BÁO LỖI ❗ "CONSTRAINT `ck_thu_cash` failed"
--   CÓ LỖI = ✅ ĐẠT.
-- ───────────────────────────────────────────────────────────────────────
INSERT INTO thu_phieu_thu (amount, method, received) VALUES (200000, 'cash', 100000);


-- ═══════════════════════════════════════════════════════════════════════
-- PHÉP THỬ 4 — TIỀN VÀ TIẾNG VIỆT
-- ═══════════════════════════════════════════════════════════════════════

-- ───────────────────────────────────────────────────────────────────────
-- KHỐI 4A — Cột thành tiền do máy tự tính
-- MONG ĐỢI: tạo bảng OK. Dòng kết quả: 3 lon × (25000 + 5000) = 90000
-- ───────────────────────────────────────────────────────────────────────
CREATE TABLE thu_dong_mon (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ten_mon         VARCHAR(255) NOT NULL,
    unit_price      BIGINT UNSIGNED NOT NULL,
    options_amount  BIGINT UNSIGNED NOT NULL DEFAULT 0,
    quantity        SMALLINT UNSIGNED NOT NULL,

    line_amount     BIGINT UNSIGNED
        GENERATED ALWAYS AS ((unit_price + options_amount) * quantity) STORED,

    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO thu_dong_mon (ten_mon, unit_price, options_amount, quantity)
VALUES ('Bia Tiger lon', 25000, 5000, 3);

SELECT ten_mon, unit_price, options_amount, quantity, line_amount
FROM thu_dong_mon;


-- ───────────────────────────────────────────────────────────────────────
-- KHỐI 4B — Không nhập tay được thành tiền
-- MONG ĐỢI: ❗ PHẢI BÁO LỖI ❗ "The value specified for generated column ... is not allowed"
--   CÓ LỖI = ✅ ĐẠT. Nhân viên không sửa được thành tiền.
-- ───────────────────────────────────────────────────────────────────────
INSERT INTO thu_dong_mon (ten_mon, unit_price, options_amount, quantity, line_amount)
VALUES ('Bia gian lận', 25000, 0, 3, 1);


-- ───────────────────────────────────────────────────────────────────────
-- KHỐI 4C — Tìm món tiếng Việt không cần gõ dấu
-- MONG ĐỢI: cả 3 câu đều trả về dòng 'Bia Tiger lon' hoặc số đếm = 1.
--   Chứng minh utf8mb4_unicode_ci so sánh bỏ dấu và bỏ hoa thường,
--   giống hệt utf8mb4_0900_ai_ci của MySQL 8.
-- ───────────────────────────────────────────────────────────────────────
INSERT INTO thu_dong_mon (ten_mon, unit_price, quantity) VALUES ('Lẩu gà lá é', 250000, 1);

SELECT COUNT(*) AS tim_khong_dau  FROM thu_dong_mon WHERE ten_mon = 'Lau ga la e';
SELECT COUNT(*) AS tim_hoa_thuong FROM thu_dong_mon WHERE ten_mon = 'LẨU GÀ LÁ É';
SELECT ten_mon FROM thu_dong_mon WHERE ten_mon LIKE '%ga%';


-- ───────────────────────────────────────────────────────────────────────
-- KHỐI 5 — Dọn dẹp sau khi kiểm tra xong
-- Chạy khối này SAU KHI đã ghi lại kết quả 4 phép thử.
-- ───────────────────────────────────────────────────────────────────────
DROP DATABASE IF EXISTS kiemtra_mariadb;


-- ═══════════════════════════════════════════════════════════════════════
-- BẢNG GHI KẾT QUẢ — tự đánh dấu rồi lưu lại
--
--   [ ] Phép thử 1 — Một bàn một lượt khách        (khối 1A–1E)
--   [ ] Phép thử 2 — Một lúc một ca mở             (khối 2A–2D)
--   [ ] Phép thử 3 — Ràng buộc tiền được thi hành  (khối 3A–3D)
--   [ ] Phép thử 4 — Thành tiền tự tính + tiếng Việt (khối 4A–4C)
--
-- ĐỦ 4 DẤU  → MariaDB 10.4.32 dùng được. Đi tiếp Bước 3.
-- THIẾU DẤU → DỪNG. Gửi lại kết quả khối bị trượt, đừng xây tiếp.
-- ═══════════════════════════════════════════════════════════════════════
