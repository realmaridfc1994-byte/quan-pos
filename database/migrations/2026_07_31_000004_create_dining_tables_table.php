<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bảng 4 trong docs/schema.md — SƠ ĐỒ BÀN.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dining_tables', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->string('code', 20)->comment('Mã bàn ngắn in trên tem: B01, VIP1');
            $table->string('name', 50)->comment('Tên hiển thị: Bàn 1, Bàn sân');
            $table->string('area', 50)->nullable()->comment('Khu vực: Trong nhà, Sân, Lầu 1');
            $table->unsignedTinyInteger('seats')->default(4)->comment('Số ghế, chỉ để gợi ý xếp bàn');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->comment('Bàn dẹp đi thì tắt cờ, KHÔNG xoá');
            $table->timestamps();

            $table->unique('code', 'uq_dining_tables_code');
            $table->index(['is_active', 'area', 'sort_order'], 'idx_dining_tables_layout');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dining_tables');
    }
};
