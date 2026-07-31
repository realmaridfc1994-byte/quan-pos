<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Quán 5-15 bàn không có tầng "quản lý" riêng — người đứng quầy (thu ngân) chính
 * là người duyệt các hành động nhạy cảm (huỷ món đã phục vụ, giảm giá, void bill).
 * Đổi role 'manager' thành 'cashier' cho khớp thực tế nghiệp vụ.
 *
 * Không sửa migration 2026_07_31_000001_create_users_table.php đã chạy — theo
 * đúng quy tắc CLAUDE.md mục 7.1. Đổi ENUM qua ba bước để không mất dữ liệu:
 * thêm 'cashier' vào ENUM trước, chuyển dữ liệu, rồi mới bỏ 'manager' ra khỏi ENUM.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE users MODIFY role ENUM('owner','manager','cashier','staff','kitchen') NOT NULL DEFAULT 'staff'");
        DB::statement("UPDATE users SET role = 'cashier' WHERE role = 'manager'");
        DB::statement("ALTER TABLE users MODIFY role ENUM('owner','cashier','staff','kitchen') NOT NULL DEFAULT 'staff'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE users MODIFY role ENUM('owner','manager','cashier','staff','kitchen') NOT NULL DEFAULT 'staff'");
        DB::statement("UPDATE users SET role = 'manager' WHERE role = 'cashier'");
        DB::statement("ALTER TABLE users MODIFY role ENUM('owner','manager','staff','kitchen') NOT NULL DEFAULT 'staff'");
    }
};
