<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sổ cái CHỐNG TRÙNG THEO op_uuid Ở TẦNG ĐỒNG BỘ — độc lập với uuid nghiệp
 * vụ trên từng bảng (table_sessions.uuid, orders.uuid...). Hai lớp chống
 * trùng khác nhau, cùng tồn tại — xem docs/thiet-ke-dong-bo.md mục 3.3.
 *
 * Chỉ ghi các thao tác Action đã CHẠY THÀNH CÔNG (status "applied"). Xung
 * đột dùng chính `uq_sync_conflicts_op` của bảng sync_conflicts để chống
 * tạo trùng bản ghi chờ; "rejected" không cần ghi vì chạy lại tự nhiên ra
 * đúng lỗi cũ (Action đã tự chặn, không có tác dụng phụ nào để trùng).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_applied_ops', function (Blueprint $table) {
            $table->char('op_uuid', 36)->charset('ascii')->collation('ascii_bin')->primary();
            $table->string('op_type', 40);
            $table->string('device_id', 50);
            $table->json('result_payload')->comment('Đủ để dựng lại response applied cũ khi gửi lại');
            $table->dateTime('applied_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_applied_ops');
    }
};
