-- ===========================================================================
-- DỮ LIỆU MẪU ĐỂ THỬ NGHIỆM
--
-- Đây chỉ là dữ liệu giả để anh mở phpMyAdmin lên nhìn cho dễ hình dung,
-- và để kiểm tra các chốt chặn có hoạt động thật không.
--
-- KHI LÀM THẬT: xoá file này đi, hoặc để Laravel seeder tạo dữ liệu thật.
-- Mật khẩu bên dưới là mã hoá của chuỗi "123456" — PHẢI ĐỔI trước khi dùng thật.
-- ===========================================================================

USE quan_pos;

-- --- Nhân viên -------------------------------------------------------------
INSERT INTO users (name, username, password, role, is_active, created_at, updated_at) VALUES
('Chủ quán',      'chuquan', '$2y$12$0BSKB.Ov0Uu55w06ktRfQOhBjWZL2rvuegqDkmpp0MTRTZWaDKk9K', 'owner',   1, NOW(), NOW()),
('Thu ngân Hạnh', 'hanh',    '$2y$12$0BSKB.Ov0Uu55w06ktRfQOhBjWZL2rvuegqDkmpp0MTRTZWaDKk9K', 'cashier', 1, NOW(), NOW()),
('Phục vụ Nam',   'nam',     '$2y$12$0BSKB.Ov0Uu55w06ktRfQOhBjWZL2rvuegqDkmpp0MTRTZWaDKk9K', 'waiter',  1, NOW(), NOW()),
('Phục vụ Lan',   'lan',     '$2y$12$0BSKB.Ov0Uu55w06ktRfQOhBjWZL2rvuegqDkmpp0MTRTZWaDKk9K', 'waiter',  1, NOW(), NOW()),
('Bếp trưởng',    'bep',     '$2y$12$0BSKB.Ov0Uu55w06ktRfQOhBjWZL2rvuegqDkmpp0MTRTZWaDKk9K', 'kitchen', 1, NOW(), NOW());

-- --- Bàn -------------------------------------------------------------------
INSERT INTO dining_tables (code, name, area, seats, sort_order, is_active, created_at, updated_at) VALUES
('B01', 'Bàn 1',   'Trong nhà', 4,  1, 1, NOW(), NOW()),
('B02', 'Bàn 2',   'Trong nhà', 4,  2, 1, NOW(), NOW()),
('B03', 'Bàn 3',   'Trong nhà', 6,  3, 1, NOW(), NOW()),
('B04', 'Bàn 4',   'Trong nhà', 6,  4, 1, NOW(), NOW()),
('S01', 'Sân 1',   'Sân',       8,  5, 1, NOW(), NOW()),
('S02', 'Sân 2',   'Sân',       8,  6, 1, NOW(), NOW()),
('S03', 'Sân 3',   'Sân',       10, 7, 1, NOW(), NOW()),
('VIP', 'Phòng VIP','Lầu 1',    12, 8, 1, NOW(), NOW());

-- --- Nhóm món (kèm nơi in tem) ---------------------------------------------
INSERT INTO categories (name, station, sort_order, is_active, created_at, updated_at) VALUES
('Bia',       'bar',     1, 1, NOW(), NOW()),
('Nước ngọt', 'bar',     2, 1, NOW(), NOW()),
('Mồi khô',   'kitchen', 3, 1, NOW(), NOW()),
('Món nướng', 'kitchen', 4, 1, NOW(), NOW()),
('Lẩu',       'kitchen', 5, 1, NOW(), NOW());

-- --- Món -------------------------------------------------------------------
INSERT INTO products (category_id, code, name, station_override, sort_order, is_active, created_at, updated_at) VALUES
((SELECT id FROM categories WHERE name='Bia'),       'TIGER',   'Bia Tiger',            NULL, 1, 1, NOW(), NOW()),
((SELECT id FROM categories WHERE name='Bia'),       'SAIGON',  'Bia Sài Gòn',          NULL, 2, 1, NOW(), NOW()),
((SELECT id FROM categories WHERE name='Nước ngọt'), 'COCA',    'Coca Cola',            NULL, 1, 1, NOW(), NOW()),
((SELECT id FROM categories WHERE name='Mồi khô'),   'KHOMUC',  'Khô mực nướng',        NULL, 1, 1, NOW(), NOW()),
((SELECT id FROM categories WHERE name='Mồi khô'),   'DAUPHONG','Đậu phộng rang',       NULL, 2, 1, NOW(), NOW()),
((SELECT id FROM categories WHERE name='Món nướng'), 'GANUONG', 'Gà nướng muối ớt',     NULL, 1, 1, NOW(), NOW()),
((SELECT id FROM categories WHERE name='Lẩu'),       'LAUTHAI', 'Lẩu Thái hải sản',     NULL, 1, 1, NOW(), NOW());

-- --- Biến thể (NƠI CHỨA GIÁ) -----------------------------------------------
INSERT INTO product_variants (product_id, name, price, is_default, sort_order, tracks_inventory, stock_unit, stock_factor, is_active, created_at, updated_at) VALUES
((SELECT id FROM products WHERE code='TIGER'),   'Lon',       25000,  1, 1, 1, 'lon',  1,  1, NOW(), NOW()),
((SELECT id FROM products WHERE code='TIGER'),   'Chai',      27000,  0, 2, 1, 'chai', 1,  1, NOW(), NOW()),
((SELECT id FROM products WHERE code='TIGER'),   'Thùng',     550000, 0, 3, 1, 'lon',  24, 1, NOW(), NOW()),
((SELECT id FROM products WHERE code='SAIGON'),  'Lon',       22000,  1, 1, 1, 'lon',  1,  1, NOW(), NOW()),
((SELECT id FROM products WHERE code='SAIGON'),  'Thùng',     490000, 0, 2, 1, 'lon',  24, 1, NOW(), NOW()),
((SELECT id FROM products WHERE code='COCA'),    'Lon',       15000,  1, 1, 1, 'lon',  1,  1, NOW(), NOW()),
((SELECT id FROM products WHERE code='KHOMUC'),  'Phần nhỏ',  120000, 1, 1, 0, NULL,   1,  1, NOW(), NOW()),
((SELECT id FROM products WHERE code='KHOMUC'),  'Phần lớn',  200000, 0, 2, 0, NULL,   1,  1, NOW(), NOW()),
((SELECT id FROM products WHERE code='DAUPHONG'),'Mặc định',  30000,  1, 1, 0, NULL,   1,  1, NOW(), NOW()),
((SELECT id FROM products WHERE code='GANUONG'), 'Mặc định',  280000, 1, 1, 0, NULL,   1,  1, NOW(), NOW()),
((SELECT id FROM products WHERE code='LAUTHAI'), 'Nồi nhỏ',   250000, 1, 1, 0, NULL,   1,  1, NOW(), NOW()),
((SELECT id FROM products WHERE code='LAUTHAI'), 'Nồi lớn',   380000, 0, 2, 0, NULL,   1,  1, NOW(), NOW());

-- --- Nhóm tùy chọn ---------------------------------------------------------
-- "Đá" áp cho CẢ NHÓM Nước ngọt; "Độ cay" áp riêng cho Lẩu Thái
INSERT INTO option_groups (name, category_id, product_id, is_required, min_select, max_select, sort_order, is_active, created_at, updated_at) VALUES
('Đá',      (SELECT id FROM categories WHERE name='Nước ngọt'), NULL, 0, 0, 1, 1, 1, NOW(), NOW()),
('Độ cay',  NULL, (SELECT id FROM products WHERE code='LAUTHAI'), 1, 1, 1, 1, 1, NOW(), NOW()),
('Ăn thêm', NULL, (SELECT id FROM products WHERE code='LAUTHAI'), 0, 0, 3, 2, 1, NOW(), NOW());

-- --- Tùy chọn cụ thể -------------------------------------------------------
INSERT INTO options (option_group_id, name, price_delta, is_default, sort_order, is_active, created_at, updated_at) VALUES
((SELECT id FROM option_groups WHERE name='Đá'),      'Ít đá',      0,     0, 1, 1, NOW(), NOW()),
((SELECT id FROM option_groups WHERE name='Đá'),      'Nhiều đá',   0,     0, 2, 1, NOW(), NOW()),
((SELECT id FROM option_groups WHERE name='Đá'),      'Không đá',   0,     0, 3, 1, NOW(), NOW()),
((SELECT id FROM option_groups WHERE name='Độ cay'),  'Không cay',  0,     0, 1, 1, NOW(), NOW()),
((SELECT id FROM option_groups WHERE name='Độ cay'),  'Cay vừa',    0,     1, 2, 1, NOW(), NOW()),
((SELECT id FROM option_groups WHERE name='Độ cay'),  'Cay nhiều',  0,     0, 3, 1, NOW(), NOW()),
((SELECT id FROM option_groups WHERE name='Ăn thêm'), 'Thêm mì',    15000, 0, 1, 1, NOW(), NOW()),
((SELECT id FROM option_groups WHERE name='Ăn thêm'), 'Thêm rau',   20000, 0, 2, 1, NOW(), NOW()),
((SELECT id FROM option_groups WHERE name='Ăn thêm'), 'Không rau',  0,     0, 3, 1, NOW(), NOW());
