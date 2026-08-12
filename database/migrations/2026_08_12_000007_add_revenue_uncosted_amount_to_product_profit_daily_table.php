<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * docs/schema.md K.5/K.7 (K19) — Phase 4 Bước 4B.0.
 *
 * profit_amount trước đây LUÔN = revenue_amount - cost_amount, kể cả khi
 * cost_amount = 0 vì "không xác định được" (has_cost=false hoặc chưa phục
 * vụ) chứ không phải "món không tốn gì" — lãi gộp trông thành 100% doanh
 * thu. Từ đây: NULL khi TOÀN BỘ doanh thu ngày/món chưa có giá vốn; còn lại
 * chỉ tính trên phần ĐÃ biết giá vốn, phần chưa biết tách riêng ở
 * revenue_uncosted_amount, không trộn vào profit_amount.
 *
 * KHÔNG backfill tự động ở đây — dữ liệu lịch sử giữ nguyên (revenue_uncosted_amount
 * mặc định 0) cho tới khi ai đó chạy lại `php artisan report:summarize
 * --tu=... --den=...` (lệnh đã có, luôn tính lại từ nguồn gốc) cho khoảng
 * ngày muốn sửa. Xem docs/viec-ton.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE product_profit_daily
                ADD COLUMN revenue_uncosted_amount BIGINT UNSIGNED NOT NULL DEFAULT 0
                    COMMENT 'Doanh thu ứng với phần chưa xác định được giá vốn (K19)'
                    AFTER qty_not_served
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE product_profit_daily
                ADD CONSTRAINT ck_product_profit_daily_uncosted
                    CHECK (revenue_uncosted_amount <= revenue_amount)
        SQL);

        // MODIFY COLUMN đổi được biểu thức của generated column tại chỗ.
        DB::statement(<<<'SQL'
            ALTER TABLE product_profit_daily
                MODIFY COLUMN profit_amount BIGINT
                    GENERATED ALWAYS AS (
                        CASE
                            WHEN (CAST(revenue_amount AS SIGNED) - CAST(revenue_uncosted_amount AS SIGNED)) = 0 THEN NULL
                            ELSE (CAST(revenue_amount AS SIGNED) - CAST(revenue_uncosted_amount AS SIGNED)) - CAST(cost_amount AS SIGNED)
                        END
                    ) STORED
                    COMMENT 'NULL neu toan bo doanh thu chua co gia von (K19). Con lai = doanh thu da biet gia von tru gia von.'
        SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE product_profit_daily
                MODIFY COLUMN profit_amount BIGINT
                    GENERATED ALWAYS AS (CAST(revenue_amount AS SIGNED) - CAST(cost_amount AS SIGNED)) STORED
                    COMMENT 'May tu tinh = doanh thu - gia von. CO Y co dau vi ban lo van ghi duoc'
        SQL);

        DB::statement('ALTER TABLE product_profit_daily DROP CONSTRAINT ck_product_profit_daily_uncosted');
        DB::statement('ALTER TABLE product_profit_daily DROP COLUMN revenue_uncosted_amount');
    }
};
