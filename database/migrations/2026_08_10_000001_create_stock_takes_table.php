<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3 Bước 7 — bảng 24 trong docs/schema.md K.5: PHIẾU KIỂM KÊ.
 *
 * K13: open_guard là cột sinh tự động, khoá UNIQUE trên nó chỉ cho phép
 * đúng một phiếu đang mở — giống uq_shifts_only_one_open.
 * K10: phiếu đã chốt phải có đủ closed_at/closed_by_user_id/total_diff_cost
 * và không sửa lại được nữa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_takes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->comment('KK-20260805-01');

            $table->enum('status', ['open', 'closed', 'cancelled'])->default('open');

            $table->unsignedTinyInteger('open_guard')
                ->storedAs("IF(status = 'open', 1, NULL)")
                ->nullable();

            $table->bigInteger('total_diff_cost')->nullable()->comment('Tổng giá trị chênh lệch (đồng), có dấu');

            $table->string('note', 255)->nullable();
            $table->dateTime('opened_at');
            $table->unsignedBigInteger('opened_by_user_id');
            $table->dateTime('closed_at')->nullable();
            $table->unsignedBigInteger('closed_by_user_id')->nullable();

            $table->timestamps();

            $table->unique('code', 'uq_stock_takes_code');
            $table->unique('open_guard', 'uq_stock_takes_only_one_open');
            $table->index(['status', 'opened_at'], 'idx_stock_takes_status');

            $table->foreign('opened_by_user_id', 'fk_stock_takes_opener')
                ->references('id')->on('users')->restrictOnDelete();
            $table->foreign('closed_by_user_id', 'fk_stock_takes_closer')
                ->references('id')->on('users')->restrictOnDelete();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE stock_takes ADD CONSTRAINT ck_stock_takes_closed CHECK (
                status <> 'closed'
             OR (closed_at IS NOT NULL AND closed_by_user_id IS NOT NULL
                 AND total_diff_cost IS NOT NULL)
            )
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_takes');
    }
};
