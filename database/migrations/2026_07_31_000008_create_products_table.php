<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bảng 8 trong docs/schema.md — MÓN. Món không mang giá — giá nằm ở biến thể.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->foreignId('category_id')->constrained('categories', indexName: 'fk_products_category')->restrictOnDelete();
            $table->string('code', 30)->comment('Mã món để gõ nhanh: TIGER, GANUONG');
            $table->string('name', 150)->comment('Tên món in trên tem và bill');
            $table->string('description', 500)->nullable();
            $table->enum('station_override', ['kitchen', 'bar'])->nullable()
                ->comment('Bỏ trống = theo nhóm món. Chỉ điền khi món đi ngược nhóm, ví dụ Trà đá nằm nhóm Mồi nhưng do quầy làm');
            $table->boolean('is_active')->default(true)->comment('Ngưng bán thì tắt cờ, KHÔNG xoá');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('image_path', 255)->nullable();
            $table->timestamps();

            $table->unique('code', 'uq_products_code');
            $table->index(['is_active', 'category_id', 'sort_order'], 'idx_products_menu');
            $table->index('name', 'idx_products_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
