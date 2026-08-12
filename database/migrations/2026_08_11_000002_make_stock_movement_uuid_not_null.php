<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3 Bước 10 — siết `stock_movements.uuid` thành NOT NULL.
 *
 * Cột được thêm nullable ở migration 2026_08_11_000001 để dữ liệu kho cũ không
 * vỡ. KHÔNG tự backfill trong migration này — chỉ đếm và DỪNG nếu còn dòng
 * rỗng, bắt buộc chạy `php artisan stock:backfill-uuid` trước. Cùng khuôn với
 * 2026_08_04_000001_make_client_uuid_not_null.php.
 *
 * Trên máy dựng mới (migrate:fresh) bảng rỗng nên migration này chạy thẳng.
 * Trên máy có dữ liệu kho thật, nó sẽ dừng lại cho tới khi backfill xong.
 */
return new class extends Migration
{
    public function up(): void
    {
        $conNull = DB::table('stock_movements')->whereNull('uuid')->count();

        if ($conNull > 0) {
            throw new RuntimeException(
                "Còn {$conNull} dòng sổ cái kho chưa có uuid. Chạy php artisan stock:backfill-uuid trước."
            );
        }

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->char('uuid', 36)->charset('ascii')->collation('ascii_bin')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->char('uuid', 36)->charset('ascii')->collation('ascii_bin')->nullable()->change();
        });
    }
};
