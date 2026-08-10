<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3 Bước 7 — bảng 25 trong docs/schema.md K.5: DÒNG KIỂM KÊ.
 *
 * system_qty chụp lại tồn hệ thống TẠI THỜI ĐIỂM MỞ PHIẾU (OpenStockTake),
 * không đổi về sau. diff_qty do MySQL tự tính, NULL khi chưa đếm.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_take_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('stock_take_id');
            $table->unsignedBigInteger('ingredient_id');

            $table->bigInteger('system_qty')->comment('Tồn hệ thống tại thời điểm mở phiếu, không đổi về sau');
            $table->bigInteger('counted_qty')->nullable()->comment('NULL = chưa đếm');

            $table->bigInteger('diff_qty')
                ->storedAs('counted_qty - system_qty')
                ->nullable()
                ->comment('Máy tự tính. NULL khi chưa đếm');

            $table->string('note', 255)->nullable();

            $table->timestamps();

            $table->unique(['stock_take_id', 'ingredient_id'], 'uq_stock_take_items_ingredient');
            $table->index(['stock_take_id', 'diff_qty'], 'idx_stock_take_items_diff');

            $table->foreign('stock_take_id', 'fk_stock_take_items_take')
                ->references('id')->on('stock_takes')->restrictOnDelete();
            $table->foreign('ingredient_id', 'fk_stock_take_items_ingredient')
                ->references('id')->on('ingredients')->restrictOnDelete();
        });

        DB::statement('ALTER TABLE stock_take_items ADD CONSTRAINT ck_stock_take_items_counted CHECK (counted_qty IS NULL OR counted_qty >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_take_items');
    }
};
