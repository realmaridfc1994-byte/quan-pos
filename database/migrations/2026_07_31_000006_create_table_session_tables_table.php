<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bảng 6 trong docs/schema.md — LƯỢT KHÁCH ĐANG CHIẾM BÀN NÀO.
 *
 * `occupied_table_id` là cột sinh: chỉ có giá trị khi bàn đang bị chiếm
 * (detached_at IS NULL). Khoá duy nhất trên cột này (uq_tst_one_session_per_table)
 * là chốt chặn quan trọng nhất hệ thống — không cho hai lượt khách cùng chiếm một bàn.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('table_session_tables', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->foreignId('table_session_id')->constrained('table_sessions', indexName: 'fk_tst_session')->restrictOnDelete();
            $table->foreignId('dining_table_id')->constrained('dining_tables', indexName: 'fk_tst_table')->restrictOnDelete();
            $table->boolean('is_primary')->default(false)->comment('Bàn chính, dùng để gọi tên lượt khách trên màn hình');
            $table->dateTime('attached_at')->comment('Bắt đầu chiếm bàn này');
            $table->dateTime('detached_at')->nullable()->comment('Nhả bàn ra lúc nào; NULL = đang còn chiếm');
            $table->foreignId('attached_by_user_id')->constrained('users', indexName: 'fk_tst_user')->restrictOnDelete();

            $table->unsignedBigInteger('occupied_table_id')
                ->storedAs('IF(detached_at IS NULL, dining_table_id, NULL)')
                ->nullable();

            $table->timestamps();

            $table->unique('occupied_table_id', 'uq_tst_one_session_per_table');
            $table->index(['table_session_id', 'detached_at'], 'idx_tst_session');
            $table->index(['dining_table_id', 'attached_at'], 'idx_tst_table_history');
        });

        DB::statement('ALTER TABLE table_session_tables ADD CONSTRAINT ck_tst_time CHECK (detached_at IS NULL OR detached_at >= attached_at)');
    }

    public function down(): void
    {
        Schema::dropIfExists('table_session_tables');
    }
};
