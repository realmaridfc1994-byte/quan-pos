<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3 Bước 3 — bảng 19 trong docs/schema.md K.5: ĐỊNH LƯỢNG MÓN (BOM).
 *
 * Một biến thể ăn hết bao nhiêu nguyên liệu, theo đơn vị GỐC của nguyên liệu.
 * "1 lon Tiger" là một dòng: qty_base = 1. "Thùng" là một dòng: qty_base = 24.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->foreignId('ingredient_id')->constrained('ingredients')->restrictOnDelete();

            $table->unsignedInteger('qty_base')
                ->comment('Số lượng theo đơn vị gốc của nguyên liệu. 1 lẩu gà = 800 (gam gà)');

            $table->string('note', 255)->nullable();

            $table->timestamps();

            $table->unique(['product_variant_id', 'ingredient_id'], 'uq_recipes_variant_ingredient');
            $table->index('ingredient_id', 'idx_recipes_ingredient');
        });

        DB::statement('ALTER TABLE recipes ADD CONSTRAINT ck_recipes_qty CHECK (qty_base >= 1)');
    }

    public function down(): void
    {
        Schema::dropIfExists('recipes');
    }
};
