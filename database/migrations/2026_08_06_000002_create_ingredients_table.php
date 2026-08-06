<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3 Bước 2 — bảng 17 trong docs/schema.md K.5: NGUYÊN LIỆU.
 * Bia, nước ngọt cũng là nguyên liệu — không có đường trừ kho riêng cho đồ uống.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ingredients', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->comment('Mã gõ nhanh: TIGER-LON, GA-TA');
            $table->string('name', 150);

            $table->enum('base_unit', ['g', 'ml', 'cai', 'lon', 'chai'])
                ->comment('Đơn vị gốc — mọi số lượng trong hệ thống tính theo đơn vị này, phải đủ nhỏ để luôn là số nguyên');

            $table->string('category', 50)->nullable()->comment('Bia rượu, Thịt, Rau, Gia vị...');
            $table->unsignedBigInteger('min_qty')->default(0)
                ->comment('Dưới mức này thì cảnh báo tồn thấp. 0 = không cảnh báo');
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique('code', 'uq_ingredients_code');
            $table->unique('name', 'uq_ingredients_name');
            $table->index(['is_active', 'category', 'name'], 'idx_ingredients_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingredients');
    }
};
