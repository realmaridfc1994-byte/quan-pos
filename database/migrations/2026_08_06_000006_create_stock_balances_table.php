<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3 Bước 3 — bảng 21 trong docs/schema.md K.5: TỒN HIỆN TẠI.
 *
 * Tạo trước để Bước 3 tính được "giá vốn ước tính" của món (đọc total_cost/qty
 * hiện tại — toàn 0 vì chưa nhập hàng). Chưa tạo bảng `stock_movements` (thuộc
 * Bước 4/5) nên CHƯA có khoá ngoại `last_movement_id → stock_movements.id` —
 * cột này để trống, khoá ngoại thêm ở Bước 4 khi bảng đó ra đời.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_balances', function (Blueprint $table) {
            $table->unsignedBigInteger('ingredient_id')->comment('Khoá chính — mỗi nguyên liệu đúng một dòng');
            $table->bigInteger('qty')->default(0)->comment('Còn bao nhiêu, theo đơn vị gốc. ÂM ĐƯỢC PHÉP — xem K.3');
            $table->bigInteger('total_cost')->default(0)->comment('Trị giá số tồn (đồng). Giá TB = total_cost / qty, KHÔNG lưu');
            $table->unsignedBigInteger('last_movement_id')->nullable()->comment('Dòng sổ cái gần nhất — FK thêm ở Bước 4 khi có bảng stock_movements');
            $table->timestamp('updated_at')->nullable();

            $table->primary('ingredient_id');
            $table->index('qty', 'idx_stock_balances_low');

            $table->foreign('ingredient_id', 'fk_stock_balances_ingredient')
                ->references('id')->on('ingredients')->restrictOnDelete();
        });

        DB::statement('ALTER TABLE stock_balances ADD CONSTRAINT ck_stock_balances_zero CHECK (qty <> 0 OR total_cost = 0)');
        DB::statement('ALTER TABLE stock_balances ADD CONSTRAINT ck_stock_balances_cost CHECK (qty <= 0 OR total_cost >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_balances');
    }
};
