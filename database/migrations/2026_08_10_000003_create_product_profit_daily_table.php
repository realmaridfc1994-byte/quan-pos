<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3 Bước 8 — bảng 26 trong docs/schema.md K.1: LÃI GỘP THEO MÓN, THEO NGÀY.
 *
 * Cùng vòng đời với product_sales_daily — ghi bởi SummarizeProductProfit lúc
 * đóng ca, tính lại từ đầu rồi ghi đè mỗi lần chạy. Màn hình chủ quán CHỈ
 * đọc bảng này, không bao giờ đọc thẳng order_items/stock_movements.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_profit_daily', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('product_variant_id');

            $table->unsignedInteger('quantity_sold')->default(0);
            $table->unsignedBigInteger('revenue_amount')->default(0)->comment('Doanh thu ĐÃ phân bổ giảm giá theo tỉ lệ');
            $table->unsignedBigInteger('cost_amount')->default(0)->comment('Giá vốn thật tại thời điểm bán, từ stock_movements');

            $table->bigInteger('profit_amount')
                ->storedAs('CAST(revenue_amount AS SIGNED) - CAST(cost_amount AS SIGNED)')
                ->comment('Máy tự tính = doanh thu - giá vốn. CỐ Ý có dấu vì bán lỗ vẫn ghi được');

            $table->timestamps();

            $table->unique(['date', 'product_variant_id'], 'uq_product_profit_daily_date_variant');
            $table->index(['date', 'product_id'], 'idx_product_profit_daily_date_product');

            $table->foreign('product_id', 'fk_product_profit_daily_product')
                ->references('id')->on('products')->restrictOnDelete();
            $table->foreign('product_variant_id', 'fk_product_profit_daily_variant')
                ->references('id')->on('product_variants')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_profit_daily');
    }
};
