<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bảng 15 trong docs/schema.md — CÁC LẦN THU TIỀN.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();

            $table->char('uuid', 36)->charset('ascii')->collation('ascii_bin');

            $table->foreignId('table_session_id')->constrained('table_sessions', indexName: 'fk_payments_session')->restrictOnDelete();
            $table->foreignId('shift_id')->comment('Thu trong ca nào — dùng để đối soát cuối ca')
                ->constrained('shifts', indexName: 'fk_payments_shift')->restrictOnDelete();

            $table->enum('method', ['cash', 'transfer']);
            $table->unsignedBigInteger('amount')->comment('Số tiền GHI NHẬN vào doanh thu (đồng)');
            $table->unsignedBigInteger('tendered_amount')->nullable()->comment('Tiền mặt khách đưa ra (đồng). Chỉ dùng khi method = cash');
            $table->unsignedBigInteger('change_amount')->default(0)->comment('Tiền thối lại (đồng)');
            $table->string('reference', 100)->nullable()->comment('Mã giao dịch / nội dung chuyển khoản, để dò sao kê ngân hàng');

            $table->enum('status', ['completed', 'voided'])->default('completed');
            $table->foreignId('received_by_user_id')->constrained('users', indexName: 'fk_payments_received_by')->restrictOnDelete();
            $table->dateTime('paid_at');

            $table->foreignId('voided_by_user_id')->nullable()->constrained('users', indexName: 'fk_payments_voided_by')->restrictOnDelete();
            $table->dateTime('voided_at')->nullable();
            $table->string('void_reason', 255)->nullable()->comment('Thu nhầm, khách đòi đổi hình thức trả');

            $table->timestamps();

            $table->unique('uuid', 'uq_payments_uuid');
            $table->index(['table_session_id', 'status'], 'idx_payments_session');
            $table->index(['shift_id', 'status', 'method'], 'idx_payments_shift_recon');
            $table->index('paid_at', 'idx_payments_paid_at');
        });

        DB::statement('ALTER TABLE payments ADD CONSTRAINT ck_payments_amount CHECK (amount > 0)');
        DB::statement(<<<'SQL'
            ALTER TABLE payments ADD CONSTRAINT ck_payments_cash CHECK (
                (method = 'cash'     AND tendered_amount IS NOT NULL AND tendered_amount = amount + change_amount)
             OR (method = 'transfer' AND tendered_amount IS NULL     AND change_amount = 0)
            )
        SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE payments ADD CONSTRAINT ck_payments_void CHECK (
                status <> 'voided' OR (voided_at IS NOT NULL AND void_reason IS NOT NULL AND voided_by_user_id IS NOT NULL)
            )
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
