<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bảng 7 trong docs/schema.md — NHÓM MÓN.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->string('name', 100)->comment('Bia, Mồi khô, Lẩu, Nước ngọt');
            $table->enum('station', ['kitchen', 'bar'])->default('kitchen')
                ->comment('Món thuộc nhóm này in tem ở đâu: kitchen=bếp, bar=quầy pha chế');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('name', 'uq_categories_name');
            $table->index(['is_active', 'sort_order'], 'idx_categories_menu');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
