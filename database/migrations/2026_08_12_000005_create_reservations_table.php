<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bảng 22 trong docs/schema.md PHẦN M — SỔ ĐẶT BÀN.
 *
 * Không khoá cứng dining_tables (M3) — dining_table_id chỉ là gợi ý. Chuyển
 * sang cancelled bắt buộc có lý do + người đổi (M2), giống ck_payments_void.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('customer_id')->nullable()
                ->comment('Khách vãng lai đặt bàn không cần hồ sơ')
                ->constrained('customers')->restrictOnDelete();

            $table->foreignId('dining_table_id')->nullable()
                ->comment('Gợi ý bàn — KHÔNG giữ chỗ độc quyền (M3)')
                ->constrained('dining_tables')->restrictOnDelete();

            $table->foreignId('table_session_id')->nullable()
                ->comment('Gắn sau khi SeatReservation nối vào lượt khách đang mở (M4)')
                ->constrained('table_sessions')->restrictOnDelete();

            $table->unsignedInteger('guest_count');
            $table->dateTime('reserved_at');
            $table->enum('status', ['pending', 'confirmed', 'seated', 'no_show', 'cancelled'])->default('pending');
            $table->string('note', 255)->nullable();

            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('status_changed_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('status_changed_at')->nullable();

            $table->timestamps();

            $table->index(['dining_table_id', 'reserved_at'], 'idx_reservations_table_time');
            $table->index(['status', 'reserved_at'], 'idx_reservations_status');
            $table->index('customer_id', 'idx_reservations_customer');
        });

        DB::statement('ALTER TABLE reservations ADD CONSTRAINT ck_reservations_guest_count CHECK (guest_count > 0)');

        DB::statement(<<<'SQL'
            ALTER TABLE reservations ADD CONSTRAINT ck_reservations_cancel_reason CHECK (
                status <> 'cancelled'
                OR (note IS NOT NULL AND status_changed_by_user_id IS NOT NULL AND status_changed_at IS NOT NULL)
            )
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
