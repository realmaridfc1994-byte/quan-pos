<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 Bước 8 — số lượng/doanh thu bán ra của TỪNG BIẾN THỂ, theo NGÀY.
 * Cùng vòng đời với `daily_summaries` — xem docblock ở migration đó.
 *
 * `product_id` lưu lại dù suy được từ `product_variant_id` — tra "top món"
 * (gộp mọi biến thể của một món) không phải join ngược qua product_variants
 * mỗi lần đọc màn hình chủ quán.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_sales_daily', function (Blueprint $table) {
            $table->id();

            $table->date('date')->comment('Ngày kinh doanh, cùng quy ước với daily_summaries.date');
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants')->restrictOnDelete();

            $table->unsignedInteger('quantity_sold')->default(0);
            $table->unsignedBigInteger('revenue_amount')->default(0)->comment('Thành tiền các dòng món CHƯA HUỶ (đồng)');

            $table->timestamps();

            $table->unique(['date', 'product_variant_id'], 'uq_product_sales_daily_date_variant');
            $table->index(['date', 'product_id'], 'idx_product_sales_daily_date_product');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_sales_daily');
    }
};
