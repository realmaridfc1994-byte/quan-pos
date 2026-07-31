<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bảng 13 trong docs/schema.md — DÒNG MÓN TRÊN PHIẾU.
 *
 * product_name/variant_name/unit_price là BẢN SAO tại thời điểm gọi món —
 * sửa giá thực đơn về sau KHÔNG được đổi một chữ nào trên hoá đơn cũ (M4).
 * line_amount là cột sinh, không ai nhập tay được (M5).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->foreignId('order_id')->constrained('orders', indexName: 'fk_order_items_order')->restrictOnDelete();

            $table->foreignId('product_id')->constrained('products', indexName: 'fk_order_items_product')->restrictOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants', indexName: 'fk_order_items_variant')->restrictOnDelete();

            $table->string('product_name', 150)->comment('Ảnh chụp tên món lúc gọi');
            $table->string('variant_name', 100)->comment('Ảnh chụp tên biến thể lúc gọi: Lon, Thùng');
            $table->unsignedBigInteger('unit_price')->comment('Ảnh chụp giá một đơn vị lúc gọi (đồng)');
            $table->unsignedBigInteger('options_amount')->default(0)->comment('Tổng tiền tùy chọn cộng thêm cho MỘT đơn vị (đồng)');
            $table->unsignedSmallInteger('quantity')->comment('Số lượng. 5 lon Tiger giống nhau = 1 dòng, quantity = 5');

            $table->unsignedBigInteger('line_amount')
                ->storedAs('(unit_price + options_amount) * quantity')
                ->comment('Thành tiền dòng này (đồng)');

            $table->enum('status', ['ordered', 'served', 'cancelled'])->default('ordered');
            $table->dateTime('served_at')->nullable();

            $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users', indexName: 'fk_order_items_cancelled_by')->restrictOnDelete();
            $table->dateTime('cancelled_at')->nullable();
            $table->string('cancel_reason', 255)->nullable()->comment('Bấm nhầm, khách trả, bếp làm hỏng, hết hàng');

            $table->foreignId('split_from_item_id')->nullable()
                ->constrained('order_items', indexName: 'fk_order_items_split')->restrictOnDelete();

            $table->string('note', 255)->nullable()->comment('Ghi chú riêng dòng này: "nướng cháy cạnh"');
            $table->timestamps();

            $table->index(['order_id', 'status'], 'idx_order_items_order');
            $table->index(['product_id', 'created_at'], 'idx_order_items_product_stats');
            $table->index('product_variant_id', 'idx_order_items_variant');
            $table->index('split_from_item_id', 'idx_order_items_split');
        });

        DB::statement('ALTER TABLE order_items ADD CONSTRAINT ck_order_items_qty CHECK (quantity >= 1)');
        DB::statement(<<<'SQL'
            ALTER TABLE order_items ADD CONSTRAINT ck_order_items_cancel CHECK (
                status <> 'cancelled' OR (cancelled_at IS NOT NULL AND cancel_reason IS NOT NULL AND cancelled_by_user_id IS NOT NULL)
            )
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
