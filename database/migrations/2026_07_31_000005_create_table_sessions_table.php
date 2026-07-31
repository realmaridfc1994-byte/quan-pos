<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bảng 5 trong docs/schema.md — LƯỢT KHÁCH, trái tim hệ thống.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('table_sessions', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->string('code', 30)->comment('Mã lượt khách: PH-20260730-0007');
            $table->foreignId('shift_id')->comment('Lượt khách này mở trong ca nào')
                ->constrained('shifts', indexName: 'fk_table_sessions_shift')->restrictOnDelete();

            $table->unsignedSmallInteger('guest_count')->default(1)->comment('Số khách, dùng để thống kê chi tiêu bình quân');
            $table->enum('status', ['open', 'billing', 'closed', 'void'])->default('open')
                ->comment('open=đang nhậu, billing=đã in tạm tính đang chờ trả, closed=đã thu đủ, void=huỷ toàn bộ');

            $table->foreignId('opened_by_user_id')->constrained('users', indexName: 'fk_table_sessions_opened_by')->restrictOnDelete();
            $table->dateTime('opened_at');

            $table->unsignedBigInteger('subtotal_amount')->default(0)->comment('Tổng tiền món chưa giảm giá (đồng)');
            $table->unsignedBigInteger('discount_amount')->default(0)->comment('Số tiền giảm trên tổng bill (đồng)');
            $table->string('discount_reason', 255)->nullable()->comment('Bắt buộc ghi khi có giảm giá: "khách quen", "bớt lẻ"');
            $table->unsignedBigInteger('total_amount')->default(0)->comment('Số tiền khách phải trả = subtotal - discount');
            $table->unsignedBigInteger('paid_amount')->default(0)->comment('Đã thu được bao nhiêu (cộng dồn từ payments)');

            $table->string('bill_no', 30)->nullable()->comment('Số hoá đơn, chỉ sinh khi in bill chính thức');
            $table->dateTime('bill_printed_at')->nullable();
            $table->dateTime('provisional_printed_at')->nullable()->comment('Lần cuối in tạm tính');
            $table->unsignedSmallInteger('provisional_print_count')->default(0);

            $table->foreignId('closed_by_user_id')->nullable()->constrained('users', indexName: 'fk_table_sessions_closed_by')->restrictOnDelete();
            $table->dateTime('closed_at')->nullable();

            $table->foreignId('voided_by_user_id')->nullable()->constrained('users', indexName: 'fk_table_sessions_voided_by')->restrictOnDelete();
            $table->dateTime('voided_at')->nullable();
            $table->string('void_reason', 255)->nullable();

            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->unique('code', 'uq_table_sessions_code');
            $table->unique('bill_no', 'uq_table_sessions_bill_no');
            $table->index(['status', 'opened_at'], 'idx_table_sessions_status_opened');
            $table->index(['shift_id', 'status'], 'idx_table_sessions_shift');
            $table->index('closed_at', 'idx_table_sessions_closed_at');
        });

        DB::statement('ALTER TABLE table_sessions ADD CONSTRAINT ck_table_sessions_discount CHECK (discount_amount <= subtotal_amount)');
        DB::statement('ALTER TABLE table_sessions ADD CONSTRAINT ck_table_sessions_total CHECK (total_amount + discount_amount = subtotal_amount)');
        DB::statement('ALTER TABLE table_sessions ADD CONSTRAINT ck_table_sessions_discount_reason CHECK (discount_amount = 0 OR discount_reason IS NOT NULL)');
        DB::statement("ALTER TABLE table_sessions ADD CONSTRAINT ck_table_sessions_void CHECK (status <> 'void' OR (voided_at IS NOT NULL AND void_reason IS NOT NULL))");
        DB::statement("ALTER TABLE table_sessions ADD CONSTRAINT ck_table_sessions_closed CHECK (status <> 'closed' OR (closed_at IS NOT NULL AND paid_amount >= total_amount))");
    }

    public function down(): void
    {
        Schema::dropIfExists('table_sessions');
    }
};
