<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 Bước 6 — "một lượt khách một khuyến mãi" (không chồng nhiều khuyến
 * mãi). NULL nghĩa là lượt khách này chưa áp khuyến mãi nào — chốt ngay khi
 * `ApplyPromotion` áp thành công, không đổi/xoá sau đó (huỷ khuyến mãi không
 * xoá tham chiếu, hoá đơn cũ vẫn trỏ đúng chương trình đã dùng).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('table_sessions', function (Blueprint $table) {
            $table->foreignId('promotion_id')
                ->nullable()
                ->after('discount_reason')
                ->constrained('promotions')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('table_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('promotion_id');
        });
    }
};
