<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3 Bước 10 — mã vân tay cho từng dòng sổ cái kho.
 *
 * Vì sao cần thêm dù đã có uq_stock_movements_ref: khoá đó gồm
 * (ref_type, ref_id, ingredient_id), mà hao hụt và điều chỉnh tay luôn ghi
 * ref_id rỗng. Trong MariaDB nhiều dòng cùng rỗng KHÔNG bị coi là trùng nhau,
 * nên hai đường đó không được khoá cũ chặn: bấm ghi "5 lon vỡ" hai lần vì mạng
 * lag là kho trừ 10 lon, không lỗi nào nổ ra.
 *
 * Nullable tạm thời; backfill bằng lệnh `stock:backfill-uuid`, KHÔNG backfill
 * trong migration. Siết NOT NULL ở migration 2026_08_11_000002 (chạy sau khi
 * đã backfill xong trên dữ liệu thật).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->char('uuid', 36)->charset('ascii')->collation('ascii_bin')->nullable()->after('id');
            $table->unique('uuid', 'uq_stock_movements_uuid');
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropUnique('uq_stock_movements_uuid');
            $table->dropColumn('uuid');
        });
    }
};
