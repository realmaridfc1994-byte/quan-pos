<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bảng 14 trong docs/schema.md — TÙY CHỌN ĐÃ CHỌN CHO TỪNG DÒNG MÓN.
 * option_group_name/option_name/price_delta là BẢN SAO tại thời điểm gọi món.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_item_options', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->foreignId('order_item_id')->constrained('order_items', indexName: 'fk_oio_item')->restrictOnDelete();
            $table->foreignId('option_id')->nullable()->comment('Trỏ về thực đơn, để Phase 3 trừ kho. NULL nếu là ghi chú tự do')
                ->constrained('options', indexName: 'fk_oio_option')->restrictOnDelete();

            $table->string('option_group_name', 100)->comment('Ảnh chụp tên nhóm: Độ cay');
            $table->string('option_name', 100)->comment('Ảnh chụp tên tùy chọn: Thêm ớt');
            $table->unsignedBigInteger('price_delta')->default(0)->comment('Ảnh chụp tiền cộng thêm cho MỘT đơn vị món (đồng)');

            $table->timestamps();

            $table->index('order_item_id', 'idx_oio_item');
            $table->index('option_id', 'idx_oio_option');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_item_options');
    }
};
