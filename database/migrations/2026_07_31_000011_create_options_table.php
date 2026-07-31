<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bảng 11 trong docs/schema.md — TÙY CHỌN CỤ THỂ.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('options', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->foreignId('option_group_id')->constrained('option_groups', indexName: 'fk_options_group')->restrictOnDelete();
            $table->string('name', 100)->comment('Thêm ớt, Ít đá, Không rau, Thêm mì');
            $table->unsignedBigInteger('price_delta')->default(0)->comment('Tiền cộng thêm cho MỘT đơn vị món (đồng). Phần lớn là 0');
            $table->boolean('is_default')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['option_group_id', 'name'], 'uq_options_group_name');
            $table->index(['option_group_id', 'is_active', 'sort_order'], 'idx_options_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('options');
    }
};
