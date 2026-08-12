<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase 3 Bước 10 (review Opus) — khoá NỬA CÒN LẠI của bất biến K1.
 *
 * Migration 2026_08_12_000002 đã chặn SỬA sổ cái ở tầng database. Nhưng XOÁ thì
 * mới chỉ chặn ở tầng Model, và chính docblock của migration đó đã nói rõ lý do
 * vì sao tầng Model là chưa đủ: một câu SQL gõ tay trong phpMyAdmin hay một
 * script vá dữ liệu viết vội đều đi vòng qua nó.
 *
 * Lý lẽ cũ là "khoá ngoại trỏ vào sổ cái đã chặn phần lớn đường xoá rồi" —
 * PHẦN LỚN, không phải tất cả. Một dòng sổ cái không phải dòng cuối của nguyên
 * liệu nào (stock_balances.last_movement_id không trỏ tới) và chưa ai ghi chú
 * mồ côi cho nó thì không có khoá ngoại nào giữ, xoá được sạch sẽ. Xoá đúng một
 * dòng như vậy làm tổng sổ cái lệch khỏi bảng tồn vĩnh viễn — và đó chính là
 * thứ bất biến K2/K3 tồn tại để chặn.
 *
 * Sổ cái là chỗ duy nhất làm chứng được kho đã đi đâu về đâu. Ghi sai thì ghi
 * thêm một dòng bù trừ, không bao giờ xoá dòng cũ.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER trg_stock_movements_no_delete BEFORE DELETE ON stock_movements
            FOR EACH ROW
            BEGIN
                SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'stock_movements la so cai chi ghi them, khong duoc xoa. Ghi sai thi ghi them dong bu tru.';
            END
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_stock_movements_no_delete');
    }
};
