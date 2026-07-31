<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bảng 1 trong docs/schema.md — SỔ NHÂN VIÊN.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->string('name', 100)->comment('Tên hiển thị trên bill và tem');
            $table->string('username', 50)->comment('Tên đăng nhập');
            $table->string('phone', 20)->comment('Số điện thoại, dùng để đăng nhập trên máy POS');
            $table->string('password', 255)->comment('Mật khẩu đã mã hoá (bcrypt)');
            $table->string('pin_code', 255)->nullable()->comment('Mã PIN 4-6 số đã mã hoá, dùng để duyệt nhanh việc hủy món');
            $table->enum('role', ['owner', 'manager', 'staff', 'kitchen'])->default('staff');
            $table->boolean('is_active')->default(true)->comment('Nghỉ việc thì tắt cờ này, KHÔNG xoá');
            $table->rememberToken();
            $table->timestamps();

            $table->unique('username', 'uq_users_username');
            $table->unique('phone', 'uq_users_phone');
            $table->index(['is_active', 'role'], 'idx_users_active_role');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
