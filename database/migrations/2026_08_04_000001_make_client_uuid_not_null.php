<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bước 2 Phase 2 — siết `uuid` thành NOT NULL cho ba bảng
 * `table_sessions`, `order_items`, `order_item_options`.
 *
 * Ba cột này được thêm nullable ở các migration
 * 2026_08_02_000001/000002/000003 để dữ liệu cũ không vỡ, với kế hoạch
 * siết chặt sau khi backfill xong. KHÔNG tự backfill trong migration này —
 * chỉ đếm và dừng nếu còn dòng rỗng, bắt buộc chạy `pos:backfill-uuid`
 * trước, để tránh migration tự ý sinh uuid giả cho dữ liệu giao dịch cũ.
 */
return new class extends Migration
{
    public function up(): void
    {
        $conNullTableSessions = DB::table('table_sessions')->whereNull('uuid')->count();
        $conNullOrderItems = DB::table('order_items')->whereNull('uuid')->count();
        $conNullOrderItemOptions = DB::table('order_item_options')->whereNull('uuid')->count();

        $tongConNull = $conNullTableSessions + $conNullOrderItems + $conNullOrderItemOptions;

        if ($tongConNull > 0) {
            throw new RuntimeException(
                "Còn {$tongConNull} dòng chưa có uuid. Chạy php artisan pos:backfill-uuid trước."
            );
        }

        Schema::table('table_sessions', function (Blueprint $table) {
            $table->char('uuid', 36)->charset('ascii')->collation('ascii_bin')->nullable(false)->change();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->char('uuid', 36)->charset('ascii')->collation('ascii_bin')->nullable(false)->change();
        });

        Schema::table('order_item_options', function (Blueprint $table) {
            $table->char('uuid', 36)->charset('ascii')->collation('ascii_bin')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('table_sessions', function (Blueprint $table) {
            $table->char('uuid', 36)->charset('ascii')->collation('ascii_bin')->nullable()->change();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->char('uuid', 36)->charset('ascii')->collation('ascii_bin')->nullable()->change();
        });

        Schema::table('order_item_options', function (Blueprint $table) {
            $table->char('uuid', 36)->charset('ascii')->collation('ascii_bin')->nullable()->change();
        });
    }
};
