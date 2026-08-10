<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3 Bước 4 — bảng 22 trong docs/schema.md: PHIẾU NHẬP HÀNG.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->comment('NH-20260805-0042');
            $table->unsignedBigInteger('supplier_id');

            $table->enum('status', ['draft', 'received', 'cancelled'])->default('draft');
            $table->unsignedBigInteger('total_cost')->default(0)->comment('Tổng tiền phiếu (đồng)');

            $table->string('note', 255)->nullable();
            $table->string('invoice_no', 50)->nullable()->comment('Số hoá đơn của nhà cung cấp');

            $table->dateTime('received_at')->nullable();
            $table->unsignedBigInteger('received_by_user_id')->nullable();
            $table->string('cancel_reason', 255)->nullable();

            $table->unsignedBigInteger('created_by_user_id');
            $table->timestamps();

            $table->unique('code', 'uq_purchases_code');
            $table->index(['supplier_id', 'received_at'], 'idx_purchases_supplier_date');
            $table->index(['status', 'created_at'], 'idx_purchases_status');

            $table->foreign('supplier_id', 'fk_purchases_supplier')
                ->references('id')->on('suppliers')->restrictOnDelete();
            $table->foreign('received_by_user_id', 'fk_purchases_receiver')
                ->references('id')->on('users')->restrictOnDelete();
            $table->foreign('created_by_user_id', 'fk_purchases_creator')
                ->references('id')->on('users')->restrictOnDelete();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE purchases ADD CONSTRAINT ck_purchases_received CHECK (
                status <> 'received'
             OR (received_at IS NOT NULL AND received_by_user_id IS NOT NULL)
            )
        SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE purchases ADD CONSTRAINT ck_purchases_cancelled CHECK (
                status <> 'cancelled' OR cancel_reason IS NOT NULL
            )
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};
