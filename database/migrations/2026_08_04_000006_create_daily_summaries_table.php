<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 Bước 8 — tổng hợp MỘT NGÀY, đọc bởi màn hình chủ quán. Ghi bởi
 * `App\Domain\Reporting\Actions\SummarizeDailyReport`, chạy lúc đóng ca (đẩy
 * vào hàng đợi, không cùng transaction với CloseShift) hoặc chạy tay lại
 * bằng lệnh `report:summarize`.
 *
 * Chạy lại nhiều lần cho CÙNG MỘT NGÀY phải ra cùng một kết quả (ghi đè,
 * không cộng dồn) — đây là lý do `date` là UNIQUE và Action luôn tính lại
 * từ đầu rồi updateOrCreate, không tự cộng vào số cũ.
 *
 * `cash_variance_amount` là cột DUY NHẤT trong toàn dự án cố ý cho phép ÂM —
 * chênh lệch két có thể là "thiếu" (âm) hay "thừa" (dương), khác mọi cột tiền
 * khác trong hệ thống vốn luôn BIGINT UNSIGNED.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_summaries', function (Blueprint $table) {
            $table->id();

            $table->date('date')->comment('Ngày kinh doanh — theo opened_at của từng lượt khách/ca, không phải giờ chạy job');

            $table->unsignedBigInteger('revenue_amount')->default(0)->comment('Doanh thu = tiền mặt + chuyển khoản đã thu (đồng)');
            $table->unsignedBigInteger('cash_amount')->default(0)->comment('Tiền mặt đã thu (đồng)');
            $table->unsignedBigInteger('transfer_amount')->default(0)->comment('Chuyển khoản đã thu (đồng)');
            $table->unsignedBigInteger('discount_amount')->default(0)->comment('Tổng tiền giảm giá trong ngày (đồng)');

            $table->unsignedInteger('table_session_count')->default(0)->comment('Số lượt khách mở trong ngày');
            $table->unsignedInteger('guest_count')->default(0)->comment('Tổng số khách trong ngày');

            $table->unsignedInteger('cancelled_item_count')->default(0)->comment('Số dòng món đã huỷ trong ngày');
            $table->unsignedBigInteger('cancelled_item_amount')->default(0)->comment('Giá trị món đã huỷ trong ngày (đồng)');

            // CỐ Ý KHÔNG unsigned — chênh lệch két có thể âm (thiếu tiền).
            $table->bigInteger('cash_variance_amount')->default(0)->comment('Tổng chênh lệch két các ca đóng trong ngày — âm = thiếu, dương = thừa (đồng)');

            $table->timestamps();

            $table->unique('date', 'uq_daily_summaries_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_summaries');
    }
};
