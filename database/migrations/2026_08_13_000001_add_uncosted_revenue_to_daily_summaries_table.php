<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * docs/schema.md bảng 19 + bất biến K19 — Phase 4 Bước 4B.0.
 *
 * Bước 4B.0 đã tách được phần doanh thu chưa biết giá vốn ở bảng lãi gộp THEO
 * MÓN. Nhưng màn hình chủ quán hỏi một câu ở cấp NGÀY: "tối qua bao nhiêu phần
 * trăm doanh thu không biết giá vốn?". Trả lời câu đó cần một tử số và một mẫu
 * số, và hai số đó phải đo bằng CÙNG MỘT THƯỚC.
 *
 * `revenue_amount` sẵn có KHÔNG dùng làm mẫu số được: nó là tiền ĐÃ THU VÀO KÉT
 * (từ payments), còn phần thiếu giá vốn đo theo DÒNG MÓN ĐÃ GỌI. Hai thước khác
 * nhau một cách bình thường — khách ăn tối nay trả tiền sau nửa đêm, bàn còn mở
 * chưa thu, bill huỷ cả lượt. Lấy tử số thước này chia mẫu số thước kia ra một
 * tỉ lệ SAI mà trông rất thật, đúng loại lỗi bước 4B.0 sinh ra để diệt.
 *
 * Nên thêm ĐỦ CẢ CẶP, cùng đo theo dòng món:
 *   item_revenue_amount           — mẫu số
 *   item_revenue_uncosted_amount  — tử số
 *
 * KHÔNG backfill tự động ở đây. Dữ liệu ngày cũ giữ nguyên giá trị 0 (nghĩa là
 * "chưa soát", không phải "đã soát và sạch") cho tới khi có người chạy
 * `php artisan report:backfill-gia-von --tu=... --den=...` cho khoảng ngày đó.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_summaries', function (Blueprint $table) {
            $table->unsignedBigInteger('item_revenue_amount')->default(0)->after('discount_amount')
                ->comment('Doanh thu theo dòng món đã gọi, đã phân bổ giảm giá (đồng) — MẪU SỐ của tỉ lệ thiếu giá vốn');
            $table->unsignedBigInteger('item_revenue_uncosted_amount')->default(0)->after('item_revenue_amount')
                ->comment('Phần trong đó chưa xác định được giá vốn (đồng) — TỬ SỐ, K19');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE daily_summaries ADD CONSTRAINT ck_daily_summaries_uncosted
                CHECK (item_revenue_uncosted_amount <= item_revenue_amount)
        SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE daily_summaries DROP CONSTRAINT ck_daily_summaries_uncosted');

        Schema::table('daily_summaries', function (Blueprint $table) {
            $table->dropColumn(['item_revenue_uncosted_amount', 'item_revenue_amount']);
        });
    }
};
