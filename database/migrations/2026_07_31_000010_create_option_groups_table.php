<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bảng 10 trong docs/schema.md — NHÓM TÙY CHỌN.
 * Gắn cho MỘT món cụ thể, HOẶC cho cả một nhóm món — đúng một trong hai (ck_option_groups_scope).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('option_groups', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->string('name', 100)->comment('Độ cay, Đá, Rau ăn kèm');

            $table->foreignId('product_id')->nullable()->comment('Áp cho riêng món này')
                ->constrained('products', indexName: 'fk_option_groups_product')->restrictOnDelete();
            $table->foreignId('category_id')->nullable()->comment('Áp cho mọi món trong nhóm này')
                ->constrained('categories', indexName: 'fk_option_groups_category')->restrictOnDelete();

            $table->boolean('is_required')->default(false)->comment('Bắt buộc khách phải chọn mới gửi bếp được');
            $table->unsignedTinyInteger('min_select')->default(0);
            $table->unsignedTinyInteger('max_select')->default(1)->comment('1 = chỉ chọn một (ít đá HOẶC nhiều đá). Lớn hơn = chọn nhiều');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['product_id', 'is_active'], 'idx_option_groups_product');
            $table->index(['category_id', 'is_active'], 'idx_option_groups_category');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE option_groups ADD CONSTRAINT ck_option_groups_scope CHECK (
                (product_id IS NOT NULL AND category_id IS NULL)
             OR (product_id IS NULL     AND category_id IS NOT NULL)
            )
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE option_groups ADD CONSTRAINT ck_option_groups_select CHECK (
                max_select >= 1 AND min_select <= max_select
                AND (is_required = 0 OR min_select >= 1)
            )
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('option_groups');
    }
};
