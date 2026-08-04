-- ===========================================================================
-- POS QUÁN NHẬU — CẤU TRÚC DATABASE PHASE 1
-- Tài liệu giải thích đầy đủ: docs/database-design.md
--
-- File này CHẠY TỰ ĐỘNG khi container MySQL được khởi tạo LẦN ĐẦU.
-- Thứ tự bảng đã sắp đúng: bảng được trỏ tới luôn tạo trước.
-- ===========================================================================

SET NAMES utf8mb4 COLLATE utf8mb4_0900_ai_ci;
USE quan_pos;

-- =====================================================================
-- NHÓM A — CON NGƯỜI VÀ CA LÀM VIỆC
-- =====================================================================

-- 1. NHÂN VIÊN
CREATE TABLE users (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name                VARCHAR(100)    NOT NULL COMMENT 'Tên hiển thị trên bill và tem',
    username            VARCHAR(50)     NOT NULL COMMENT 'Tên đăng nhập',
    phone               VARCHAR(20)     NOT NULL COMMENT 'Số điện thoại, dùng để đăng nhập trên máy POS',
    password            VARCHAR(255)    NOT NULL COMMENT 'Mật khẩu đã mã hoá (bcrypt)',
    pin_code            VARCHAR(255)    NULL     COMMENT 'Mã PIN 4-6 số đã mã hoá, dùng để duyệt nhanh việc hủy món',
    role                ENUM('owner','cashier','staff','kitchen') NOT NULL DEFAULT 'staff',
    is_active           TINYINT(1)      NOT NULL DEFAULT 1 COMMENT 'Nghỉ việc thì tắt cờ này, KHÔNG xoá',
    remember_token      VARCHAR(100)    NULL,
    created_at          TIMESTAMP       NULL,
    updated_at          TIMESTAMP       NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_username (username),
    UNIQUE KEY uq_users_phone (phone),
    KEY idx_users_active_role (is_active, role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- 2. CA LÀM VIỆC
CREATE TABLE shifts (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code                VARCHAR(30)     NOT NULL COMMENT 'Mã ca hiển thị, ví dụ CA-20260730-01',

    opened_by_user_id   BIGINT UNSIGNED NOT NULL,
    opened_at           DATETIME        NOT NULL,
    opening_cash        BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Tiền lẻ có sẵn trong két khi mở ca (đồng)',

    closed_by_user_id   BIGINT UNSIGNED NULL,
    closed_at           DATETIME        NULL,
    counted_cash        BIGINT UNSIGNED NULL COMMENT 'Số tiền mặt ĐẾM THỰC TẾ trong két cuối ca (đồng)',
    expected_cash       BIGINT UNSIGNED NULL COMMENT 'Số tiền mặt LẼ RA phải có, do hệ thống tính, chốt lại lúc đóng ca',

    status              ENUM('open','closed') NOT NULL DEFAULT 'open',
    note                VARCHAR(500)    NULL COMMENT 'Ghi chú đối soát: vì sao lệch',

    -- Cột kỹ thuật: chỉ có giá trị 1 khi ca đang mở => khoá duy nhất bên dưới
    -- bảo đảm KHÔNG BAO GIỜ có hai ca mở cùng lúc.
    open_guard          TINYINT UNSIGNED
                        GENERATED ALWAYS AS (IF(status = 'open', 1, NULL)) STORED,

    created_at          TIMESTAMP       NULL,
    updated_at          TIMESTAMP       NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_shifts_code (code),
    UNIQUE KEY uq_shifts_only_one_open (open_guard),
    KEY idx_shifts_opened_at (opened_at),
    CONSTRAINT fk_shifts_opened_by FOREIGN KEY (opened_by_user_id) REFERENCES users (id) ON DELETE RESTRICT,
    CONSTRAINT fk_shifts_closed_by FOREIGN KEY (closed_by_user_id) REFERENCES users (id) ON DELETE RESTRICT,
    CONSTRAINT ck_shifts_closed_fields CHECK (
        (status = 'open'   AND closed_at IS NULL     AND counted_cash IS NULL)
     OR (status = 'closed' AND closed_at IS NOT NULL AND counted_cash IS NOT NULL
                           AND closed_by_user_id IS NOT NULL AND expected_cash IS NOT NULL)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- 3. THU CHI TIỀN MẶT NGOÀI BÁN HÀNG
CREATE TABLE cash_movements (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    shift_id            BIGINT UNSIGNED NOT NULL,
    direction           ENUM('in','out') NOT NULL COMMENT 'in = bỏ thêm tiền vào két, out = lấy tiền ra',
    amount              BIGINT UNSIGNED NOT NULL COMMENT 'Luôn là số dương (đồng); chiều tiền nằm ở cột direction',
    reason              VARCHAR(255)    NOT NULL COMMENT 'Bắt buộc ghi lý do: "mua đá", "chủ rút tiền"',
    created_by_user_id  BIGINT UNSIGNED NOT NULL,
    occurred_at         DATETIME        NOT NULL,
    created_at          TIMESTAMP       NULL,
    updated_at          TIMESTAMP       NULL,
    PRIMARY KEY (id),
    KEY idx_cash_movements_shift (shift_id, occurred_at),
    CONSTRAINT fk_cash_movements_shift   FOREIGN KEY (shift_id)           REFERENCES shifts (id) ON DELETE RESTRICT,
    CONSTRAINT fk_cash_movements_user    FOREIGN KEY (created_by_user_id) REFERENCES users (id)  ON DELETE RESTRICT,
    CONSTRAINT ck_cash_movements_amount  CHECK (amount > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- =====================================================================
-- NHÓM B — KHÔNG GIAN QUÁN
-- =====================================================================

-- 4. BÀN VẬT LÝ
CREATE TABLE dining_tables (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code                VARCHAR(20)     NOT NULL COMMENT 'Mã bàn ngắn in trên tem: B01, VIP1',
    name                VARCHAR(50)     NOT NULL COMMENT 'Tên hiển thị: Bàn 1, Bàn sân',
    area                VARCHAR(50)     NULL     COMMENT 'Khu vực: Trong nhà, Sân, Lầu 1',
    seats               TINYINT UNSIGNED NOT NULL DEFAULT 4 COMMENT 'Số ghế, chỉ để gợi ý xếp bàn',
    sort_order          SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    is_active           TINYINT(1)      NOT NULL DEFAULT 1 COMMENT 'Bàn dẹp đi thì tắt cờ, KHÔNG xoá',
    created_at          TIMESTAMP       NULL,
    updated_at          TIMESTAMP       NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_dining_tables_code (code),
    KEY idx_dining_tables_layout (is_active, area, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- 5. LƯỢT KHÁCH  (TRÁI TIM HỆ THỐNG)
CREATE TABLE table_sessions (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    -- Phase 2 Bước 2: vân tay do máy POS sinh trước khi gửi. NULL tạm thời cho
    -- dữ liệu cũ — xem lệnh `pos:backfill-uuid`.
    uuid                CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,

    code                VARCHAR(30)     NOT NULL COMMENT 'Mã lượt khách: PH-20260730-0007',
    shift_id            BIGINT UNSIGNED NOT NULL COMMENT 'Lượt khách này mở trong ca nào',

    guest_count         SMALLINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Số khách, dùng để thống kê chi tiêu bình quân',
    status              ENUM('open','billing','closed','void') NOT NULL DEFAULT 'open'
                        COMMENT 'open=đang nhậu, billing=đã in tạm tính đang chờ trả, closed=đã thu đủ, void=huỷ toàn bộ',

    opened_by_user_id   BIGINT UNSIGNED NOT NULL,
    opened_at           DATETIME        NOT NULL,

    -- Tiền: chốt lại tại thời điểm tính tiền, KHÔNG tính lại về sau
    subtotal_amount     BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Tổng tiền món chưa giảm giá (đồng)',
    discount_amount     BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Số tiền giảm trên tổng bill (đồng)',
    discount_reason     VARCHAR(255)    NULL COMMENT 'Bắt buộc ghi khi có giảm giá: "khách quen", "bớt lẻ"',
    -- Phase 2 Bước 6: "một lượt khách một khuyến mãi". NULL = chưa áp.
    promotion_id        BIGINT UNSIGNED NULL COMMENT 'Khuyến mãi đã áp, xem bảng promotions',
    total_amount        BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Số tiền khách phải trả = subtotal - discount',
    paid_amount         BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Đã thu được bao nhiêu (cộng dồn từ payments)',

    -- In ấn
    bill_no             VARCHAR(30)     NULL COMMENT 'Số hoá đơn, chỉ sinh khi in bill chính thức',
    bill_printed_at     DATETIME        NULL,
    provisional_printed_at DATETIME     NULL COMMENT 'Lần cuối in tạm tính',
    provisional_print_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,

    closed_by_user_id   BIGINT UNSIGNED NULL,
    closed_at           DATETIME        NULL,

    -- Huỷ toàn bộ lượt khách (khách bỏ về, mở nhầm)
    voided_by_user_id   BIGINT UNSIGNED NULL,
    voided_at           DATETIME        NULL,
    void_reason         VARCHAR(255)    NULL,

    note                VARCHAR(500)    NULL,
    created_at          TIMESTAMP       NULL,
    updated_at          TIMESTAMP       NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_table_sessions_uuid (uuid),
    UNIQUE KEY uq_table_sessions_code (code),
    UNIQUE KEY uq_table_sessions_bill_no (bill_no),
    KEY idx_table_sessions_status_opened (status, opened_at),
    KEY idx_table_sessions_shift (shift_id, status),
    KEY idx_table_sessions_closed_at (closed_at),
    CONSTRAINT fk_table_sessions_shift     FOREIGN KEY (shift_id)          REFERENCES shifts (id) ON DELETE RESTRICT,
    CONSTRAINT fk_table_sessions_opened_by FOREIGN KEY (opened_by_user_id) REFERENCES users (id)  ON DELETE RESTRICT,
    CONSTRAINT fk_table_sessions_closed_by FOREIGN KEY (closed_by_user_id) REFERENCES users (id)  ON DELETE RESTRICT,
    CONSTRAINT fk_table_sessions_voided_by FOREIGN KEY (voided_by_user_id) REFERENCES users (id)  ON DELETE RESTRICT,
    -- fk_table_sessions_promotion thêm bằng ALTER TABLE ở cuối file (mục 18) —
    -- bảng promotions định nghĩa SAU table_sessions trong file này, CREATE TABLE
    -- không thể tham chiếu tới một bảng chưa tồn tại.
    CONSTRAINT ck_table_sessions_discount CHECK (discount_amount <= subtotal_amount),
    CONSTRAINT ck_table_sessions_total    CHECK (total_amount + discount_amount = subtotal_amount),
    CONSTRAINT ck_table_sessions_discount_reason CHECK (discount_amount = 0 OR discount_reason IS NOT NULL),
    CONSTRAINT ck_table_sessions_void     CHECK (status <> 'void' OR (voided_at IS NOT NULL AND void_reason IS NOT NULL)),
    CONSTRAINT ck_table_sessions_closed   CHECK (status <> 'closed' OR (closed_at IS NOT NULL AND paid_amount >= total_amount))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- 6. LƯỢT KHÁCH ĐANG CHIẾM BÀN NÀO  (GHÉP BÀN + CHỐNG MỞ TRÙNG)
CREATE TABLE table_session_tables (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    table_session_id    BIGINT UNSIGNED NOT NULL,
    dining_table_id     BIGINT UNSIGNED NOT NULL,
    is_primary          TINYINT(1)      NOT NULL DEFAULT 0 COMMENT 'Bàn chính, dùng để gọi tên lượt khách trên màn hình',
    attached_at         DATETIME        NOT NULL COMMENT 'Bắt đầu chiếm bàn này',
    detached_at         DATETIME        NULL     COMMENT 'Nhả bàn ra lúc nào; NULL = đang còn chiếm',
    attached_by_user_id BIGINT UNSIGNED NOT NULL,

    -- CHỐT CHẶN QUAN TRỌNG NHẤT CỦA HỆ THỐNG:
    -- cột này chỉ có giá trị khi bàn đang bị chiếm (detached_at IS NULL).
    -- Khoá duy nhất bên dưới khiến MySQL TỰ TỪ CHỐI người thứ hai mở cùng một bàn,
    -- kể cả khi hai người bấm trong cùng một phần nghìn giây.
    occupied_table_id   BIGINT UNSIGNED
                        GENERATED ALWAYS AS (IF(detached_at IS NULL, dining_table_id, NULL)) STORED,

    created_at          TIMESTAMP       NULL,
    updated_at          TIMESTAMP       NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tst_one_session_per_table (occupied_table_id),
    KEY idx_tst_session (table_session_id, detached_at),
    KEY idx_tst_table_history (dining_table_id, attached_at),
    CONSTRAINT fk_tst_session FOREIGN KEY (table_session_id)    REFERENCES table_sessions (id) ON DELETE RESTRICT,
    CONSTRAINT fk_tst_table   FOREIGN KEY (dining_table_id)     REFERENCES dining_tables (id)  ON DELETE RESTRICT,
    CONSTRAINT fk_tst_user    FOREIGN KEY (attached_by_user_id) REFERENCES users (id)          ON DELETE RESTRICT,
    CONSTRAINT ck_tst_time    CHECK (detached_at IS NULL OR detached_at >= attached_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- =====================================================================
-- NHÓM C — THỰC ĐƠN
-- =====================================================================

-- 7. NHÓM MÓN
CREATE TABLE categories (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name                VARCHAR(100)    NOT NULL COMMENT 'Bia, Mồi khô, Lẩu, Nước ngọt',
    station             ENUM('kitchen','bar') NOT NULL DEFAULT 'kitchen'
                        COMMENT 'Món thuộc nhóm này in tem ở đâu: kitchen=bếp, bar=quầy pha chế',
    sort_order          SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    is_active           TINYINT(1)      NOT NULL DEFAULT 1,
    created_at          TIMESTAMP       NULL,
    updated_at          TIMESTAMP       NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_categories_name (name),
    KEY idx_categories_menu (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- 8. MÓN
CREATE TABLE products (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    category_id         BIGINT UNSIGNED NOT NULL,
    code                VARCHAR(30)     NOT NULL COMMENT 'Mã món để gõ nhanh: TIGER, GANUONG',
    name                VARCHAR(150)    NOT NULL COMMENT 'Tên món in trên tem và bill',
    description         VARCHAR(500)    NULL,
    station_override    ENUM('kitchen','bar') NULL
                        COMMENT 'Bỏ trống = theo nhóm món. Chỉ điền khi món đi ngược nhóm',
    is_active           TINYINT(1)      NOT NULL DEFAULT 1 COMMENT 'Ngưng bán thì tắt cờ, KHÔNG xoá',
    sort_order          SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    image_path          VARCHAR(255)    NULL,
    created_at          TIMESTAMP       NULL,
    updated_at          TIMESTAMP       NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_products_code (code),
    KEY idx_products_menu (is_active, category_id, sort_order),
    KEY idx_products_name (name),
    CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- 9. BIẾN THỂ CỦA MÓN — NƠI DUY NHẤT CHỨA GIÁ BÁN
CREATE TABLE product_variants (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id          BIGINT UNSIGNED NOT NULL,
    name                VARCHAR(100)    NOT NULL COMMENT 'Lon, Chai, Thùng, Phần nhỏ. Món không có biến thể ghi "Mặc định"',
    price               BIGINT UNSIGNED NOT NULL COMMENT 'Giá bán hiện hành (đồng). Sửa giá KHÔNG ảnh hưởng hoá đơn cũ',
    is_default          TINYINT(1)      NOT NULL DEFAULT 0 COMMENT 'Biến thể chọn sẵn khi bấm vào món',
    is_active           TINYINT(1)      NOT NULL DEFAULT 1,
    sort_order          SMALLINT UNSIGNED NOT NULL DEFAULT 0,

    -- Ba cột chừa sẵn cho Phase 3, hiện chưa dùng
    tracks_inventory    TINYINT(1)      NOT NULL DEFAULT 0 COMMENT 'PHASE 3: món này có trừ kho không',
    stock_unit          VARCHAR(20)     NULL     COMMENT 'PHASE 3: đơn vị kho — lon, chai, gam, ml',
    stock_factor        INT UNSIGNED    NOT NULL DEFAULT 1 COMMENT 'PHASE 3: bán 1 đơn vị này trừ bao nhiêu đơn vị kho. Thùng bia = 24',

    created_at          TIMESTAMP       NULL,
    updated_at          TIMESTAMP       NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_variants_product_name (product_id, name),
    KEY idx_variants_active (product_id, is_active, sort_order),
    CONSTRAINT fk_variants_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE RESTRICT,
    CONSTRAINT ck_variants_factor  CHECK (stock_factor >= 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- 10. NHÓM TÙY CHỌN
CREATE TABLE option_groups (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name                VARCHAR(100)    NOT NULL COMMENT 'Độ cay, Đá, Rau ăn kèm',

    -- Gắn cho MỘT món cụ thể, HOẶC cho cả một nhóm món. Đúng một trong hai.
    product_id          BIGINT UNSIGNED NULL COMMENT 'Áp cho riêng món này',
    category_id         BIGINT UNSIGNED NULL COMMENT 'Áp cho mọi món trong nhóm này',

    is_required         TINYINT(1)      NOT NULL DEFAULT 0 COMMENT 'Bắt buộc khách phải chọn mới gửi bếp được',
    min_select          TINYINT UNSIGNED NOT NULL DEFAULT 0,
    max_select          TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1 = chỉ chọn một (ít đá HOẶC nhiều đá)',
    sort_order          SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    is_active           TINYINT(1)      NOT NULL DEFAULT 1,
    created_at          TIMESTAMP       NULL,
    updated_at          TIMESTAMP       NULL,
    PRIMARY KEY (id),
    KEY idx_option_groups_product  (product_id, is_active),
    KEY idx_option_groups_category (category_id, is_active),
    CONSTRAINT fk_option_groups_product  FOREIGN KEY (product_id)  REFERENCES products (id)   ON DELETE RESTRICT,
    CONSTRAINT fk_option_groups_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE RESTRICT,
    CONSTRAINT ck_option_groups_scope CHECK (
        (product_id IS NOT NULL AND category_id IS NULL)
     OR (product_id IS NULL     AND category_id IS NOT NULL)
    ),
    CONSTRAINT ck_option_groups_select CHECK (
        max_select >= 1 AND min_select <= max_select
        AND (is_required = 0 OR min_select >= 1)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- 11. TÙY CHỌN CỤ THỂ
CREATE TABLE options (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    option_group_id     BIGINT UNSIGNED NOT NULL,
    name                VARCHAR(100)    NOT NULL COMMENT 'Thêm ớt, Ít đá, Không rau, Thêm mì',
    price_delta         BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Tiền cộng thêm cho MỘT đơn vị món (đồng). Phần lớn là 0',
    is_default          TINYINT(1)      NOT NULL DEFAULT 0,
    sort_order          SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    is_active           TINYINT(1)      NOT NULL DEFAULT 1,
    created_at          TIMESTAMP       NULL,
    updated_at          TIMESTAMP       NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_options_group_name (option_group_id, name),
    KEY idx_options_active (option_group_id, is_active, sort_order),
    CONSTRAINT fk_options_group FOREIGN KEY (option_group_id) REFERENCES option_groups (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- =====================================================================
-- NHÓM D — GIAO DỊCH BÁN HÀNG
-- =====================================================================

-- 12. PHIẾU GỌI MÓN = MỘT TỜ TEM
CREATE TABLE orders (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    -- "Vân tay" do máy POS sinh TRƯỚC KHI gửi lên. Bấm gửi hai lần vì mạng lag
    -- thì lần thứ hai mang cùng mã này và bị từ chối => bếp chỉ nhận đúng một tem.
    uuid                CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    -- Một lần bấm "Gửi" có thể sinh 2 phiếu (bếp + quầy). Mã này gom chúng lại.
    submit_batch_uuid   CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,

    table_session_id    BIGINT UNSIGNED NOT NULL COMMENT 'RÀNG BUỘC: gắn vào LƯỢT KHÁCH, không gắn vào bàn',
    sequence_no         SMALLINT UNSIGNED NOT NULL COMMENT 'Lượt gọi thứ mấy của lượt khách này: 1, 2, 3...',
    station             ENUM('kitchen','bar') NOT NULL COMMENT 'Phiếu này in ở bếp hay ở quầy',

    status              ENUM('sent','preparing','served','cancelled') NOT NULL DEFAULT 'sent',

    created_by_user_id  BIGINT UNSIGNED NOT NULL COMMENT 'Phục vụ nào gửi',
    sent_at             DATETIME        NOT NULL,
    printed_at          DATETIME        NULL COMMENT 'Lần cuối in tem thành công',
    print_count         SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'In lại mấy lần (máy in kẹt giấy)',
    served_at           DATETIME        NULL,

    cancelled_by_user_id BIGINT UNSIGNED NULL,
    cancelled_at        DATETIME        NULL,
    cancel_reason       VARCHAR(255)    NULL,

    note                VARCHAR(500)    NULL COMMENT 'Ghi chú cho cả phiếu: "làm nhanh giúp"',
    created_at          TIMESTAMP       NULL,
    updated_at          TIMESTAMP       NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_orders_uuid (uuid),
    UNIQUE KEY uq_orders_session_seq_station (table_session_id, sequence_no, station),
    KEY idx_orders_station_board (station, status, sent_at),
    KEY idx_orders_session (table_session_id, sent_at),
    KEY idx_orders_batch (submit_batch_uuid),
    CONSTRAINT fk_orders_session      FOREIGN KEY (table_session_id)     REFERENCES table_sessions (id) ON DELETE RESTRICT,
    CONSTRAINT fk_orders_created_by   FOREIGN KEY (created_by_user_id)   REFERENCES users (id)          ON DELETE RESTRICT,
    CONSTRAINT fk_orders_cancelled_by FOREIGN KEY (cancelled_by_user_id) REFERENCES users (id)          ON DELETE RESTRICT,
    CONSTRAINT ck_orders_cancel CHECK (
        status <> 'cancelled' OR (cancelled_at IS NOT NULL AND cancel_reason IS NOT NULL AND cancelled_by_user_id IS NOT NULL)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- 13. DÒNG MÓN TRÊN PHIẾU
CREATE TABLE order_items (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    -- Phase 2 Bước 2: vân tay do máy POS sinh. NULL tạm thời cho dữ liệu cũ —
    -- xem lệnh `pos:backfill-uuid`.
    uuid                CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,

    order_id            BIGINT UNSIGNED NOT NULL,

    -- Trỏ về thực đơn: dùng để thống kê và để Phase 3 trừ kho.
    -- KHÔNG dùng để hiển thị tên hay tính tiền.
    product_id          BIGINT UNSIGNED NOT NULL,
    product_variant_id  BIGINT UNSIGNED NOT NULL,

    -- BẢN SAO tại thời điểm gọi món. Đây mới là thứ hiện trên tem và bill.
    product_name        VARCHAR(150)    NOT NULL COMMENT 'Ảnh chụp tên món lúc gọi',
    variant_name        VARCHAR(100)    NOT NULL COMMENT 'Ảnh chụp tên biến thể lúc gọi: Lon, Thùng',
    unit_price          BIGINT UNSIGNED NOT NULL COMMENT 'Ảnh chụp giá một đơn vị lúc gọi (đồng)',
    options_amount      BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Tổng tiền tùy chọn cộng thêm cho MỘT đơn vị (đồng)',
    quantity            SMALLINT UNSIGNED NOT NULL COMMENT 'Số lượng. 5 lon Tiger giống nhau = 1 dòng, quantity = 5',

    -- Máy tự tính, không ai nhập tay được => không bao giờ sai phép nhân
    line_amount         BIGINT UNSIGNED
                        GENERATED ALWAYS AS ((unit_price + options_amount) * quantity) STORED
                        COMMENT 'Thành tiền dòng này (đồng)',

    status              ENUM('ordered','served','cancelled') NOT NULL DEFAULT 'ordered',
    served_at           DATETIME        NULL,

    -- Huỷ món: không xoá dòng, chỉ đổi trạng thái
    cancelled_by_user_id BIGINT UNSIGNED NULL,
    cancelled_at        DATETIME        NULL,
    cancel_reason       VARCHAR(255)    NULL COMMENT 'Bấm nhầm, khách trả, bếp làm hỏng, hết hàng',

    -- Huỷ 1 trong 5 lon: hệ thống tách dòng 5 thành dòng 4 + dòng 1, rồi huỷ dòng 1.
    split_from_item_id  BIGINT UNSIGNED NULL,

    note                VARCHAR(255)    NULL COMMENT 'Ghi chú riêng dòng này: "nướng cháy cạnh"',
    created_at          TIMESTAMP       NULL,
    updated_at          TIMESTAMP       NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_order_items_uuid (uuid),
    KEY idx_order_items_order (order_id, status),
    KEY idx_order_items_product_stats (product_id, created_at),
    KEY idx_order_items_variant (product_variant_id),
    KEY idx_order_items_split (split_from_item_id),
    CONSTRAINT fk_order_items_order        FOREIGN KEY (order_id)             REFERENCES orders (id)           ON DELETE RESTRICT,
    CONSTRAINT fk_order_items_product      FOREIGN KEY (product_id)           REFERENCES products (id)         ON DELETE RESTRICT,
    CONSTRAINT fk_order_items_variant      FOREIGN KEY (product_variant_id)   REFERENCES product_variants (id) ON DELETE RESTRICT,
    CONSTRAINT fk_order_items_cancelled_by FOREIGN KEY (cancelled_by_user_id) REFERENCES users (id)            ON DELETE RESTRICT,
    CONSTRAINT fk_order_items_split        FOREIGN KEY (split_from_item_id)   REFERENCES order_items (id)      ON DELETE RESTRICT,
    CONSTRAINT ck_order_items_qty    CHECK (quantity >= 1),
    CONSTRAINT ck_order_items_cancel CHECK (
        status <> 'cancelled' OR (cancelled_at IS NOT NULL AND cancel_reason IS NOT NULL AND cancelled_by_user_id IS NOT NULL)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- 14. TÙY CHỌN ĐÃ CHỌN CHO TỪNG DÒNG MÓN
CREATE TABLE order_item_options (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    -- Phase 2 Bước 2: vân tay do máy POS sinh. NULL tạm thời cho dữ liệu cũ —
    -- xem lệnh `pos:backfill-uuid`.
    uuid                CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,

    order_item_id       BIGINT UNSIGNED NOT NULL,
    option_id           BIGINT UNSIGNED NULL COMMENT 'Trỏ về thực đơn, để Phase 3 trừ kho. NULL nếu là ghi chú tự do',

    -- BẢN SAO tại thời điểm gọi món
    option_group_name   VARCHAR(100)    NOT NULL COMMENT 'Ảnh chụp tên nhóm: Độ cay',
    option_name         VARCHAR(100)    NOT NULL COMMENT 'Ảnh chụp tên tùy chọn: Thêm ớt',
    price_delta         BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Ảnh chụp tiền cộng thêm cho MỘT đơn vị món (đồng)',

    created_at          TIMESTAMP       NULL,
    updated_at          TIMESTAMP       NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_order_item_options_uuid (uuid),
    KEY idx_oio_item (order_item_id),
    KEY idx_oio_option (option_id),
    CONSTRAINT fk_oio_item   FOREIGN KEY (order_item_id) REFERENCES order_items (id) ON DELETE RESTRICT,
    CONSTRAINT fk_oio_option FOREIGN KEY (option_id)     REFERENCES options (id)     ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- =====================================================================
-- NHÓM E — TIỀN KHÁCH TRẢ
-- =====================================================================

-- 15. CÁC LẦN THU TIỀN
CREATE TABLE payments (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    -- Chống thu trùng khi thu ngân bấm hai lần / mạng lag
    uuid                CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,

    table_session_id    BIGINT UNSIGNED NOT NULL,
    shift_id            BIGINT UNSIGNED NOT NULL COMMENT 'Thu trong ca nào — dùng để đối soát cuối ca',

    method              ENUM('cash','transfer') NOT NULL,
    amount              BIGINT UNSIGNED NOT NULL COMMENT 'Số tiền GHI NHẬN vào doanh thu (đồng)',
    tendered_amount     BIGINT UNSIGNED NULL COMMENT 'Tiền mặt khách đưa ra (đồng). Chỉ dùng khi method = cash',
    change_amount       BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Tiền thối lại (đồng)',
    reference           VARCHAR(100)    NULL COMMENT 'Mã giao dịch / nội dung chuyển khoản, để dò sao kê ngân hàng',

    status              ENUM('completed','voided') NOT NULL DEFAULT 'completed',
    received_by_user_id BIGINT UNSIGNED NOT NULL,
    paid_at             DATETIME        NOT NULL,

    voided_by_user_id   BIGINT UNSIGNED NULL,
    voided_at           DATETIME        NULL,
    void_reason         VARCHAR(255)    NULL COMMENT 'Thu nhầm, khách đòi đổi hình thức trả',

    created_at          TIMESTAMP       NULL,
    updated_at          TIMESTAMP       NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_payments_uuid (uuid),
    KEY idx_payments_session (table_session_id, status),
    KEY idx_payments_shift_recon (shift_id, status, method),
    KEY idx_payments_paid_at (paid_at),
    CONSTRAINT fk_payments_session     FOREIGN KEY (table_session_id)    REFERENCES table_sessions (id) ON DELETE RESTRICT,
    CONSTRAINT fk_payments_shift       FOREIGN KEY (shift_id)            REFERENCES shifts (id)         ON DELETE RESTRICT,
    CONSTRAINT fk_payments_received_by FOREIGN KEY (received_by_user_id) REFERENCES users (id)          ON DELETE RESTRICT,
    CONSTRAINT fk_payments_voided_by   FOREIGN KEY (voided_by_user_id)   REFERENCES users (id)          ON DELETE RESTRICT,
    CONSTRAINT ck_payments_amount CHECK (amount > 0),
    CONSTRAINT ck_payments_cash CHECK (
        (method = 'cash'     AND tendered_amount IS NOT NULL AND tendered_amount = amount + change_amount)
     OR (method = 'transfer' AND tendered_amount IS NULL     AND change_amount = 0)
    ),
    CONSTRAINT ck_payments_void CHECK (
        status <> 'voided' OR (voided_at IS NOT NULL AND void_reason IS NOT NULL AND voided_by_user_id IS NOT NULL)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- 16. XUNG ĐỘT ĐỒNG BỘ (Phase 2 Bước 4) — docs/thiet-ke-dong-bo.md
CREATE TABLE sync_conflicts (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    op_uuid             CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    batch_uuid          CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    device_id           VARCHAR(50)     NOT NULL,

    op_type             VARCHAR(40)     NOT NULL COMMENT 'open_session, place_order, record_payment...',
    conflict_kind       VARCHAR(40)     NOT NULL COMMENT '10 loại ở ma trận docs/thiet-ke-dong-bo.md mục 5',
    is_urgent           TINYINT(1)      NOT NULL DEFAULT 0 COMMENT 'Chặn đường thanh toán — VD dòng 2: tem đã in, bếp đang nấu, bàn chưa thanh toán được',

    occurred_at         DATETIME        NOT NULL COMMENT 'Giờ máy POS khai',
    received_at         DATETIME        NOT NULL COMMENT 'Giờ server nhận',

    payload             JSON            NOT NULL COMMENT 'Thao tác gốc, đủ để áp dụng lại. Với xung đột theo CỤM (dòng 2/6/9/10): chứa cả cụm — gốc + con, giữ nguyên thứ tự và depends_on',
    server_state        JSON            NOT NULL COMMENT 'Trạng thái server lúc phát hiện',
    auto_action         VARCHAR(40)     NULL COMMENT 'Server đã tự làm gì: nhan_mon, khong_lam_gi...',

    message_vi          TEXT            NOT NULL COMMENT 'Câu giải thích cho chủ quán',
    options             JSON            NOT NULL COMMENT 'Các lựa chọn + hậu quả từng cái',

    table_session_id    BIGINT UNSIGNED NULL,
    status              ENUM('pending','resolved','dismissed') NOT NULL DEFAULT 'pending',
    resolution          VARCHAR(40)     NULL,
    resolution_note     TEXT            NULL,
    resolved_by_user_id BIGINT UNSIGNED NULL,
    resolved_at         DATETIME        NULL,

    created_at          TIMESTAMP       NULL,
    updated_at          TIMESTAMP       NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sync_conflicts_op (op_uuid),
    KEY idx_sync_conflicts_pending (status, is_urgent, created_at),
    KEY idx_sync_conflicts_session (table_session_id),
    CONSTRAINT fk_sync_conflicts_session FOREIGN KEY (table_session_id)    REFERENCES table_sessions (id) ON DELETE RESTRICT,
    CONSTRAINT fk_sync_conflicts_user    FOREIGN KEY (resolved_by_user_id) REFERENCES users (id)          ON DELETE RESTRICT,
    CONSTRAINT ck_sync_conflicts_resolved CHECK (
        status = 'pending'
     OR (resolved_at IS NOT NULL AND resolved_by_user_id IS NOT NULL AND resolution IS NOT NULL)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- 17. SỔ CÁI CHỐNG TRÙNG op_uuid Ở TẦNG ĐỒNG BỘ (Phase 2 Bước 4)
-- Độc lập với uuid nghiệp vụ trên từng bảng — xem docs/thiet-ke-dong-bo.md
-- mục 3.3.1. Chỉ ghi thao tác Action đã chạy THÀNH CÔNG (status "applied").
-- Không phải dữ liệu giao dịch — được phép dọn định kỳ bằng lệnh artisan
-- `sync:cleanup-applied-ops` (mặc định giữ 7 ngày), khác payments/orders.
CREATE TABLE sync_applied_ops (
    op_uuid         CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    op_type         VARCHAR(40)  NOT NULL,
    device_id       VARCHAR(50)  NOT NULL,
    result_payload  JSON         NOT NULL COMMENT 'Đủ để dựng lại response applied cũ khi gửi lại',
    applied_at      DATETIME     NOT NULL,
    PRIMARY KEY (op_uuid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- 18. KHUYẾN MÃI (Phase 2 Bước 6)
-- target_id KHÔNG có khoá ngoại — trỏ tới categories.id HOẶC products.id tuỳ
-- applies_to, một cột không thể có hai khoá ngoại cùng lúc.
CREATE TABLE promotions (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code                VARCHAR(30)     NOT NULL COMMENT 'Mã khuyến mãi, thu ngân gõ hoặc chọn: HAPPYHOUR20',
    name                VARCHAR(100)    NOT NULL COMMENT 'Tên hiển thị: "Giờ vàng bia 17h-19h"',

    type                ENUM('percent','amount','happy_hour') NOT NULL
                        COMMENT 'percent/happy_hour: value là %. amount: value là số tiền cố định (đồng)',
    value               BIGINT UNSIGNED NOT NULL COMMENT 'percent/happy_hour: 1-100. amount: số đồng',

    min_order_amount    BIGINT UNSIGNED NULL COMMENT 'Tạm tính tối thiểu của cả lượt khách, NULL = không giới hạn',
    max_discount_amount BIGINT UNSIGNED NULL COMMENT 'Trần số tiền được giảm, NULL = không trần',

    starts_at           DATETIME        NULL COMMENT 'NULL = không giới hạn ngày bắt đầu',
    ends_at             DATETIME        NULL COMMENT 'NULL = không giới hạn ngày kết thúc',
    time_rules          JSON            NULL COMMENT '{"days":[0..6],"from":"17:00","to":"19:00"} — NULL = mọi giờ mọi ngày. Bắt buộc có khi type=happy_hour',

    applies_to          ENUM('all','category','product') NOT NULL DEFAULT 'all',
    target_id           BIGINT UNSIGNED NULL COMMENT 'id của categories/products khi applies_to khác all — không có FK, xem ghi chú đầu mục',

    max_usage           INT UNSIGNED    NULL COMMENT 'Tổng số lần được dùng, NULL = không giới hạn',
    used_count          INT UNSIGNED    NOT NULL DEFAULT 0,

    is_active           TINYINT(1)      NOT NULL DEFAULT 1,

    created_at          TIMESTAMP       NULL,
    updated_at          TIMESTAMP       NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_promotions_code (code),
    KEY idx_promotions_active_dates (is_active, starts_at, ends_at),
    CONSTRAINT ck_promotions_value   CHECK (value > 0),
    CONSTRAINT ck_promotions_percent CHECK (type NOT IN ('percent', 'happy_hour') OR value <= 100),
    CONSTRAINT ck_promotions_target  CHECK (applies_to = 'all' OR target_id IS NOT NULL),
    CONSTRAINT ck_promotions_usage   CHECK (max_usage IS NULL OR used_count <= max_usage),
    CONSTRAINT ck_promotions_dates   CHECK (starts_at IS NULL OR ends_at IS NULL OR starts_at < ends_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- table_sessions.promotion_id (mục 5) chỉ giành được khoá ngoại ở đây, SAU KHI
-- promotions đã tồn tại — CREATE TABLE ở trên không thể tự tham chiếu tới một
-- bảng định nghĩa sau nó trong cùng file này.
ALTER TABLE table_sessions
    ADD CONSTRAINT fk_table_sessions_promotion FOREIGN KEY (promotion_id) REFERENCES promotions (id) ON DELETE RESTRICT;

-- 19. TỔNG HỢP NGÀY (Phase 2 Bước 8)
-- Ghi bởi App\Domain\Reporting\Actions\SummarizeDailyReport, chạy lúc đóng ca
-- (đẩy vào hàng đợi, KHÔNG cùng transaction với CloseShift) hoặc chạy tay lại
-- bằng lệnh `report:summarize`. Luôn tính lại từ đầu rồi ghi đè theo `date`
-- (UNIQUE) — không cộng dồn khi chạy lại nhiều lần cho cùng một ngày.
-- cash_variance_amount là cột DUY NHẤT trong toàn dự án CỐ Ý cho phép ÂM.
CREATE TABLE daily_summaries (
    id                     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    date                   DATE            NOT NULL COMMENT 'Ngày kinh doanh — theo opened_at của từng lượt khách/ca',

    revenue_amount         BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Doanh thu = tiền mặt + chuyển khoản đã thu',
    cash_amount            BIGINT UNSIGNED NOT NULL DEFAULT 0,
    transfer_amount        BIGINT UNSIGNED NOT NULL DEFAULT 0,
    discount_amount        BIGINT UNSIGNED NOT NULL DEFAULT 0,

    table_session_count    INT UNSIGNED    NOT NULL DEFAULT 0,
    guest_count            INT UNSIGNED    NOT NULL DEFAULT 0,

    cancelled_item_count   INT UNSIGNED    NOT NULL DEFAULT 0,
    cancelled_item_amount  BIGINT UNSIGNED NOT NULL DEFAULT 0,

    cash_variance_amount   BIGINT          NOT NULL DEFAULT 0 COMMENT 'Âm = thiếu, dương = thừa — CỐ Ý không unsigned',

    created_at             TIMESTAMP       NULL,
    updated_at             TIMESTAMP       NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_daily_summaries_date (date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- 20. BÁN HÀNG THEO MÓN, THEO NGÀY (Phase 2 Bước 8)
-- Cùng vòng đời với daily_summaries (mục 19).
CREATE TABLE product_sales_daily (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    date                DATE            NOT NULL,
    product_id          BIGINT UNSIGNED NOT NULL,
    product_variant_id  BIGINT UNSIGNED NOT NULL,

    quantity_sold       INT UNSIGNED    NOT NULL DEFAULT 0,
    revenue_amount      BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Thành tiền các dòng món CHƯA HUỶ',

    created_at          TIMESTAMP       NULL,
    updated_at          TIMESTAMP       NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_product_sales_daily_date_variant (date, product_variant_id),
    KEY idx_product_sales_daily_date_product (date, product_id),
    CONSTRAINT fk_product_sales_daily_product FOREIGN KEY (product_id)         REFERENCES products (id)         ON DELETE RESTRICT,
    CONSTRAINT fk_product_sales_daily_variant FOREIGN KEY (product_variant_id) REFERENCES product_variants (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
