<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3 Bước 2 — bảng 16 trong docs/schema.md K.5: NHÀ CUNG CẤP.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('phone', 20)->nullable();
            $table->string('address', 255)->nullable();
            $table->string('note', 255)->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique('name', 'uq_suppliers_name');
            $table->index(['is_active', 'name'], 'idx_suppliers_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
