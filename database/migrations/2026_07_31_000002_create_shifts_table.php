<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bảng 2 trong docs/schema.md — CA LÀM VIỆC.
 *
 * `open_guard` là cột sinh (generated): chỉ có giá trị 1 khi ca đang mở.
 * Khoá duy nhất trên cột này (uq_shifts_only_one_open) là chốt chặn C1 —
 * không bao giờ có hai ca mở cùng lúc.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->string('code', 30)->comment('Mã ca hiển thị, ví dụ CA-20260730-01');

            $table->foreignId('opened_by_user_id')->constrained('users', indexName: 'fk_shifts_opened_by')->restrictOnDelete();
            $table->dateTime('opened_at');
            $table->unsignedBigInteger('opening_cash')->default(0)->comment('Tiền lẻ có sẵn trong két khi mở ca (đồng)');

            $table->foreignId('closed_by_user_id')->nullable()->constrained('users', indexName: 'fk_shifts_closed_by')->restrictOnDelete();
            $table->dateTime('closed_at')->nullable();
            $table->unsignedBigInteger('counted_cash')->nullable()->comment('Số tiền mặt ĐẾM THỰC TẾ trong két cuối ca (đồng)');
            $table->unsignedBigInteger('expected_cash')->nullable()->comment('Số tiền mặt LẼ RA phải có, do hệ thống tính, chốt lại lúc đóng ca');

            $table->enum('status', ['open', 'closed'])->default('open');
            $table->string('note', 500)->nullable()->comment('Ghi chú đối soát: vì sao lệch');

            $table->unsignedTinyInteger('open_guard')
                ->storedAs("IF(status = 'open', 1, NULL)")
                ->nullable();

            $table->timestamps();

            $table->unique('code', 'uq_shifts_code');
            $table->unique('open_guard', 'uq_shifts_only_one_open');
            $table->index('opened_at', 'idx_shifts_opened_at');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE shifts ADD CONSTRAINT ck_shifts_closed_fields CHECK (
                (status = 'open'   AND closed_at IS NULL     AND counted_cash IS NULL)
             OR (status = 'closed' AND closed_at IS NOT NULL AND counted_cash IS NOT NULL
                                   AND closed_by_user_id IS NOT NULL AND expected_cash IS NOT NULL)
            )
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('shifts');
    }
};
