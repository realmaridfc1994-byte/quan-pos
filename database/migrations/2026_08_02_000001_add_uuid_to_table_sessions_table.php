<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bước 2 Phase 2 — định danh do máy POS sinh cho table_sessions.
 *
 * Nullable tạm thời để dữ liệu cũ không vỡ. Dữ liệu cũ backfill bằng lệnh
 * `pos:backfill-uuid`, không backfill trong migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('table_sessions', function (Blueprint $table) {
            $table->char('uuid', 36)->charset('ascii')->collation('ascii_bin')->nullable()->after('id');
            $table->unique('uuid', 'uq_table_sessions_uuid');
        });
    }

    public function down(): void
    {
        Schema::table('table_sessions', function (Blueprint $table) {
            $table->dropUnique('uq_table_sessions_uuid');
            $table->dropColumn('uuid');
        });
    }
};
