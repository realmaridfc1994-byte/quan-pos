<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bảng 16 — nơi ghi lại mọi trường hợp đồng bộ (Phase 2 Bước 4) cần chủ quán
 * quyết, theo đúng thiết kế docs/thiet-ke-dong-bo.md mục 6.
 *
 * Ràng buộc CHECK viết bằng SQL thô vì Laravel Blueprint chưa hỗ trợ CHECK,
 * giống mọi bảng giao dịch khác trong dự án (xem payments, order_items).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_conflicts', function (Blueprint $table) {
            $table->id();

            $table->char('op_uuid', 36)->charset('ascii')->collation('ascii_bin');
            $table->char('batch_uuid', 36)->charset('ascii')->collation('ascii_bin');
            $table->string('device_id', 50);

            $table->string('op_type', 40)->comment('open_session, place_order, record_payment...');
            $table->string('conflict_kind', 40)->comment('10 loại ở ma trận mục 5 docs/thiet-ke-dong-bo.md');
            $table->boolean('is_urgent')->default(false)->comment('Chặn đường thanh toán — VD dòng 2: tem đã in, bếp đang nấu, bàn chưa thanh toán được');

            $table->dateTime('occurred_at')->comment('Giờ máy POS khai');
            $table->dateTime('received_at')->comment('Giờ server nhận');

            $table->json('payload')->comment('Thao tác gốc, đủ để áp dụng lại. Với xung đột theo cụm (dòng 2/6/9/10): chứa cả cụm — gốc + con, giữ nguyên thứ tự và depends_on');
            $table->json('server_state')->comment('Trạng thái server lúc phát hiện');
            $table->string('auto_action', 40)->nullable()->comment('Server đã tự làm gì: nhan_mon, khong_lam_gi...');

            $table->text('message_vi')->comment('Câu giải thích cho chủ quán');
            $table->json('options')->comment('Các lựa chọn + hậu quả từng cái');

            $table->foreignId('table_session_id')->nullable()->constrained('table_sessions')->restrictOnDelete();
            $table->enum('status', ['pending', 'resolved', 'dismissed'])->default('pending');
            $table->string('resolution', 40)->nullable();
            $table->text('resolution_note')->nullable();
            $table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('resolved_at')->nullable();

            $table->timestamps();

            $table->unique('op_uuid', 'uq_sync_conflicts_op');
            $table->index(['status', 'is_urgent', 'created_at'], 'idx_sync_conflicts_pending');
            $table->index('table_session_id', 'idx_sync_conflicts_session');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE sync_conflicts ADD CONSTRAINT ck_sync_conflicts_resolved CHECK (
                status = 'pending'
             OR (resolved_at IS NOT NULL AND resolved_by_user_id IS NOT NULL
                 AND resolution IS NOT NULL)
            )
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_conflicts');
    }
};
