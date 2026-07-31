<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bảng 12 trong docs/schema.md — PHIẾU GỌI MÓN = MỘT TỜ TEM.
 * Gắn vào LƯỢT KHÁCH, không bao giờ gắn thẳng vào bàn (M1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();

            $table->char('uuid', 36)->charset('ascii')->collation('ascii_bin');
            $table->char('submit_batch_uuid', 36)->charset('ascii')->collation('ascii_bin')->nullable();

            $table->foreignId('table_session_id')->comment('RÀNG BUỘC: gắn vào LƯỢT KHÁCH, không gắn vào bàn')
                ->constrained('table_sessions', indexName: 'fk_orders_session')->restrictOnDelete();
            $table->unsignedSmallInteger('sequence_no')->comment('Lượt gọi thứ mấy của lượt khách này: 1, 2, 3...');
            $table->enum('station', ['kitchen', 'bar'])->comment('Phiếu này in ở bếp hay ở quầy');

            $table->enum('status', ['sent', 'preparing', 'served', 'cancelled'])->default('sent');

            $table->foreignId('created_by_user_id')->comment('Phục vụ nào gửi')
                ->constrained('users', indexName: 'fk_orders_created_by')->restrictOnDelete();
            $table->dateTime('sent_at');
            $table->dateTime('printed_at')->nullable()->comment('Lần cuối in tem thành công');
            $table->unsignedSmallInteger('print_count')->default(0)->comment('In lại mấy lần (máy in kẹt giấy)');
            $table->dateTime('served_at')->nullable();

            $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users', indexName: 'fk_orders_cancelled_by')->restrictOnDelete();
            $table->dateTime('cancelled_at')->nullable();
            $table->string('cancel_reason', 255)->nullable();

            $table->string('note', 500)->nullable()->comment('Ghi chú cho cả phiếu: "làm nhanh giúp", "khách gấp"');
            $table->timestamps();

            $table->unique('uuid', 'uq_orders_uuid');
            $table->unique(['table_session_id', 'sequence_no', 'station'], 'uq_orders_session_seq_station');
            $table->index(['station', 'status', 'sent_at'], 'idx_orders_station_board');
            $table->index(['table_session_id', 'sent_at'], 'idx_orders_session');
            $table->index('submit_batch_uuid', 'idx_orders_batch');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE orders ADD CONSTRAINT ck_orders_cancel CHECK (
                status <> 'cancelled' OR (cancelled_at IS NOT NULL AND cancel_reason IS NOT NULL AND cancelled_by_user_id IS NOT NULL)
            )
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
