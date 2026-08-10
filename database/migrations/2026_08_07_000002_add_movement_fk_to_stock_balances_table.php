<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3 Bước 4 — thêm khoá ngoại fk_stock_balances_movement còn thiếu.
 *
 * Bảng stock_balances tạo ở Bước 3, trước khi bảng stock_movements tồn tại,
 * nên cột last_movement_id để trống khoá ngoại — xem ghi chú trong
 * 2026_08_06_000006_create_stock_balances_table.php. Bảng đích đã có ở
 * migration ngay trước, giờ thêm khoá ngoại cho đúng docs/schema.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_balances', function (Blueprint $table) {
            $table->foreign('last_movement_id', 'fk_stock_balances_movement')
                ->references('id')->on('stock_movements')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_balances', function (Blueprint $table) {
            $table->dropForeign('fk_stock_balances_movement');
        });
    }
};
