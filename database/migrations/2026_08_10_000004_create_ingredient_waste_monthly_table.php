<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3 Bước 8 — bảng 27 trong docs/schema.md K.1: HAO HỤT THEO NGUYÊN
 * LIỆU, THEO THÁNG.
 *
 * Nguồn cho mục "hao hụt tháng này so tháng trước" trên màn hình chủ quán —
 * màn hình không được phép đọc thẳng stock_movements (mục 4 đề bài Bước 8).
 * Ghi bởi SummarizeIngredientWasteMonthly lúc đóng ca, tính lại từ đầu rồi
 * ghi đè đúng tháng đang chạy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ingredient_waste_monthly', function (Blueprint $table) {
            $table->id();
            $table->date('month')->comment('Luôn là ngày 01 của tháng, ví dụ 2026-08-01');
            $table->unsignedBigInteger('ingredient_id');

            $table->unsignedBigInteger('waste_qty')->default(0)->comment('Tổng số lượng hao hụt trong tháng, theo đơn vị gốc');
            $table->unsignedBigInteger('waste_cost')->default(0)->comment('Tổng giá trị hao hụt trong tháng (đồng)');

            $table->timestamps();

            $table->unique(['month', 'ingredient_id'], 'uq_ingredient_waste_monthly_month_ingredient');

            $table->foreign('ingredient_id', 'fk_ingredient_waste_monthly_ingredient')
                ->references('id')->on('ingredients')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingredient_waste_monthly');
    }
};
