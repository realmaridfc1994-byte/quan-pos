<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bảng 21 trong docs/schema.md PHẦN L — SỔ KHÁCH QUEN.
 *
 * Một quán, không phải SaaS nhiều chi nhánh — không có branch_id (xem
 * docs/viec-ton.md, quyết định 12/08). Không xoá cứng — nghỉ chơi thì tắt
 * is_active, giống suppliers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 20);
            $table->string('name', 150);
            $table->string('note', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('phone', 'uq_customers_phone');
            $table->index(['is_active', 'name'], 'idx_customers_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
