<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bảng hạ tầng chuẩn của Laravel (không thuộc 15 bảng nghiệp vụ trong docs/schema.md),
 * dùng cho Cache::store('database') — hiện tại phục vụ middleware chống thu trùng
 * (Idempotency-Key). Cấu trúc giống hệt lệnh `php artisan cache:table` sinh ra.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cache', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->integer('expiration');
        });

        Schema::create('cache_locks', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('owner');
            $table->integer('expiration');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cache_locks');
        Schema::dropIfExists('cache');
    }
};
