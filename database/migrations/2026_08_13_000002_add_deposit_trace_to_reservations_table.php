<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * docs/schema.md PHẦN M — dấu vết tiền cọc, Phase 4 Bước P4-4A.3.
 *
 * VÌ SAO CẦN: hệ thống CỐ Ý không tự quyết giữ hay hoàn cọc khi khách không
 * tới — đó là quyết định của người, thu ngân thao tác tay bằng VoidPayment.
 * Nhưng nếu không ghi lại gì thì ba tháng sau không ai trả lời được câu
 * "khách này không tới, cọc 200.000đ xử lý chưa?". Bốn cột deposit_* dưới đây
 * là chỗ ghi câu trả lời đó — DO NGƯỜI ĐÁNH DẤU, hệ thống KHÔNG suy diễn.
 *
 * status_reason — TÁCH LÝ DO ĐỔI TRẠNG THÁI RA KHỎI `note`:
 * `note` là ghi chú của KHÁCH ("bàn gần quạt", "có trẻ nhỏ", "sinh nhật").
 * Trước đây CancelReservation ghi đè lý do huỷ lên chính cột đó, nên huỷ một
 * cái là ghi chú của khách mất vĩnh viễn — và ràng buộc ck_reservations_cancel_reason
 * cũng canh nhầm cột. Từ đây hai thứ ở hai cột riêng.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->string('status_reason', 255)->nullable()->after('note')
                ->comment('Lý do huỷ hoặc lý do khách không tới — KHÁC note (ghi chú của khách)');

            $table->enum('deposit_status', ['unhandled', 'refunded', 'kept'])
                ->default('unhandled')->after('status_changed_at')
                ->comment('Cọc: chưa xử lý / đã hoàn khách / quán giữ lại — NGƯỜI đánh dấu, hệ thống không suy diễn');
            $table->foreignId('deposit_handled_by_user_id')->nullable()->after('deposit_status')
                ->constrained('users')->restrictOnDelete();
            $table->dateTime('deposit_handled_at')->nullable()->after('deposit_handled_by_user_id');
            $table->string('deposit_handled_note', 255)->nullable()->after('deposit_handled_at')
                ->comment('Vì sao xử lý như vậy — khách xin lại, quán giữ theo thoả thuận...');

            $table->index(['status', 'deposit_status'], 'idx_reservations_deposit');
        });

        // BẮT BUỘC KỸ THUẬT, không phải backfill nghiệp vụ: MariaDB kiểm ràng
        // buộc CHECK trên các dòng đang có, nên dòng cancelled cũ (lý do đang
        // nằm trong `note`) phải được chép sang cột mới trước, nếu không câu
        // ALTER bên dưới nổ. `note` giữ nguyên, không xoá chữ nào.
        DB::statement(<<<'SQL'
            UPDATE reservations SET status_reason = note
            WHERE status = 'cancelled' AND status_reason IS NULL
        SQL);

        DB::statement('ALTER TABLE reservations DROP CONSTRAINT ck_reservations_cancel_reason');

        // Mở rộng ràng buộc cũ: giờ canh CẢ no_show, và canh đúng cột lý do.
        // Luật 13 CLAUDE.md — huỷ = đổi trạng thái + ghi ai/lúc nào/vì sao.
        DB::statement(<<<'SQL'
            ALTER TABLE reservations ADD CONSTRAINT ck_reservations_status_reason CHECK (
                status NOT IN ('cancelled', 'no_show')
                OR (status_reason IS NOT NULL
                    AND status_changed_by_user_id IS NOT NULL
                    AND status_changed_at IS NOT NULL)
            )
        SQL);

        // Đánh dấu cọc đã xử lý cũng phải đủ ai/lúc nào/vì sao, cùng một luật.
        DB::statement(<<<'SQL'
            ALTER TABLE reservations ADD CONSTRAINT ck_reservations_deposit_handled CHECK (
                deposit_status = 'unhandled'
                OR (deposit_handled_by_user_id IS NOT NULL
                    AND deposit_handled_at IS NOT NULL
                    AND deposit_handled_note IS NOT NULL)
            )
        SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE reservations DROP CONSTRAINT ck_reservations_deposit_handled');
        DB::statement('ALTER TABLE reservations DROP CONSTRAINT ck_reservations_status_reason');

        DB::statement(<<<'SQL'
            ALTER TABLE reservations ADD CONSTRAINT ck_reservations_cancel_reason CHECK (
                status <> 'cancelled'
                OR (note IS NOT NULL AND status_changed_by_user_id IS NOT NULL AND status_changed_at IS NOT NULL)
            )
        SQL);

        Schema::table('reservations', function (Blueprint $table) {
            $table->dropIndex('idx_reservations_deposit');
            $table->dropConstrainedForeignId('deposit_handled_by_user_id');
            $table->dropColumn(['status_reason', 'deposit_status', 'deposit_handled_at', 'deposit_handled_note']);
        });
    }
};
