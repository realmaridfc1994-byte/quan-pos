<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3 Bước 4 — bảng 23 trong docs/schema.md: DÒNG PHIẾU NHẬP.
 *
 * qty_base và line_cost là GENERATED ALWAYS ... STORED — máy tự tính, không
 * ai (kể cả code) ghi tay được. Xem tests/Feature/Database/GeneratedColumnsTest.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_id');
            $table->unsignedBigInteger('ingredient_id');

            $table->string('unit_name', 30)->comment('Người nhập gõ theo đơn vị tiện dùng: "5 thùng"');
            $table->unsignedInteger('qty_input')->comment('Số lượng theo unit_name');

            $table->unsignedInteger('factor_snapshot')->comment('Chụp lại hệ số quy đổi tại thời điểm nhập, đổi quy đổi sau này không ảnh hưởng');

            $table->unsignedBigInteger('qty_base')
                ->storedAs('qty_input * factor_snapshot')
                ->comment('Máy tự tính, không ai nhập tay được');

            $table->unsignedBigInteger('unit_cost')->comment('Giá một đơn vị nhập (đồng/thùng)');

            $table->unsignedBigInteger('line_cost')
                ->storedAs('qty_input * unit_cost')
                ->comment('Máy tự tính, không ai nhập tay được');

            $table->timestamps();

            $table->unique(['purchase_id', 'ingredient_id'], 'uq_purchase_items_ingredient');
            $table->index(['ingredient_id', 'id'], 'idx_purchase_items_ingredient');

            $table->foreign('purchase_id', 'fk_purchase_items_purchase')
                ->references('id')->on('purchases')->restrictOnDelete();
            $table->foreign('ingredient_id', 'fk_purchase_items_ingredient')
                ->references('id')->on('ingredients')->restrictOnDelete();
        });

        DB::statement('ALTER TABLE purchase_items ADD CONSTRAINT ck_purchase_items_qty CHECK (qty_input >= 1)');
        DB::statement('ALTER TABLE purchase_items ADD CONSTRAINT ck_purchase_items_factor CHECK (factor_snapshot >= 1)');
        DB::statement('ALTER TABLE purchase_items ADD CONSTRAINT ck_purchase_items_cost CHECK (unit_cost >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_items');
    }
};
