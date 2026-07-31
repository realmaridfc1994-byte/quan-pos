<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bảng 3 trong docs/schema.md — SỔ THU CHI VẶT TRONG CA.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_movements', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->foreignId('shift_id')->constrained('shifts', indexName: 'fk_cash_movements_shift')->restrictOnDelete();
            $table->enum('direction', ['in', 'out'])->comment('in = bỏ thêm tiền vào két, out = lấy tiền ra');
            $table->unsignedBigInteger('amount')->comment('Luôn là số dương (đồng); chiều tiền nằm ở cột direction');
            $table->string('reason', 255)->comment('Bắt buộc ghi lý do: "mua đá", "chủ rút tiền"');
            $table->foreignId('created_by_user_id')->constrained('users', indexName: 'fk_cash_movements_user')->restrictOnDelete();
            $table->dateTime('occurred_at');
            $table->timestamps();

            $table->index(['shift_id', 'occurred_at'], 'idx_cash_movements_shift');
        });

        DB::statement('ALTER TABLE cash_movements ADD CONSTRAINT ck_cash_movements_amount CHECK (amount > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_movements');
    }
};
