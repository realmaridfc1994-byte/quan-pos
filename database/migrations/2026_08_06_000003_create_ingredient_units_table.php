<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3 Bước 2 — bảng 18 trong docs/schema.md K.5: QUY ĐỔI ĐƠN VỊ, MỘT CẤP.
 *
 * Không quy đổi bắc cầu: mỗi dòng là "1 đơn vị này = factor đơn vị GỐC của
 * nguyên liệu", không phải đơn vị trung gian khác.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ingredient_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ingredient_id')->constrained('ingredients')->restrictOnDelete();
            $table->string('unit_name', 30)->comment('Thùng, Kg, Lít, Két');

            $table->unsignedInteger('factor')
                ->comment('1 đơn vị này = factor đơn vị gốc. Thùng bia = 24. Kg gà = 1000. Bắt buộc số nguyên (K8)');

            $table->boolean('is_purchase_default')->default(false)
                ->comment('Đơn vị chọn sẵn khi nhập hàng');

            $table->unsignedBigInteger('purchase_default_guard')
                ->storedAs('IF(is_purchase_default = 1, ingredient_id, NULL)')
                ->nullable();

            $table->timestamps();

            $table->unique(['ingredient_id', 'unit_name'], 'uq_ingredient_units_name');
            $table->unique('purchase_default_guard', 'uq_ingredient_units_default');
        });

        DB::statement('ALTER TABLE ingredient_units ADD CONSTRAINT ck_ingredient_units_factor CHECK (factor >= 1)');
    }

    public function down(): void
    {
        Schema::dropIfExists('ingredient_units');
    }
};
