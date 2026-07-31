<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bảng 9 trong docs/schema.md — BIẾN THỂ CỦA MÓN, nơi duy nhất chứa giá bán.
 *
 * Ba cột tracks_inventory/stock_unit/stock_factor chừa sẵn cho Phase 3,
 * chưa dùng ở Phase 1 — KHÔNG được đụng vào trước khi tới Phase 3.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->foreignId('product_id')->constrained('products', indexName: 'fk_variants_product')->restrictOnDelete();
            $table->string('name', 100)->comment('Lon, Chai, Thùng, Phần nhỏ, Phần lớn. Món không có biến thể ghi "Mặc định"');
            $table->unsignedBigInteger('price')->comment('Giá bán hiện hành (đồng). Sửa giá KHÔNG ảnh hưởng hoá đơn cũ');
            $table->boolean('is_default')->default(false)->comment('Biến thể chọn sẵn khi bấm vào món');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->boolean('tracks_inventory')->default(false)->comment('PHASE 3: món này có trừ kho không');
            $table->string('stock_unit', 20)->nullable()->comment('PHASE 3: đơn vị kho — lon, chai, gam, ml');
            $table->unsignedInteger('stock_factor')->default(1)->comment('PHASE 3: bán 1 đơn vị này trừ bao nhiêu đơn vị kho. Thùng bia = 24');

            $table->timestamps();

            $table->unique(['product_id', 'name'], 'uq_variants_product_name');
            $table->index(['product_id', 'is_active', 'sort_order'], 'idx_variants_active');
        });

        DB::statement('ALTER TABLE product_variants ADD CONSTRAINT ck_variants_factor CHECK (stock_factor >= 1)');
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
