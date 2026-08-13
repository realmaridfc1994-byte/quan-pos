<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * docs/schema.md PHẦN M.3 — cho phép payments ghi tiền cọc đặt bàn.
 *
 * table_session_id đổi thành NULLABLE, thêm reservation_id NULLABLE. Ràng
 * buộc ck_payments_target (M5) đảm bảo đúng MỘT trong hai khác NULL — một
 * phiếu thu hoặc thuộc lượt khách (bán hàng), hoặc thuộc đặt bàn (cọc),
 * không bao giờ cả hai hay không cái nào.
 *
 * Dùng SQL thô cho cả bước đổi nullable lẫn CHECK — nhất quán với cách toàn
 * bộ ràng buộc CHECK khác trong dự án được viết (Blueprint chưa hỗ trợ
 * CHECK, và đổi cột có CHECK sẵn qua Doctrine DBAL từng có rủi ro xử lý sai).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE payments MODIFY COLUMN table_session_id BIGINT UNSIGNED NULL');

        DB::statement('ALTER TABLE payments ADD COLUMN reservation_id BIGINT UNSIGNED NULL AFTER table_session_id');

        DB::statement(<<<'SQL'
            ALTER TABLE payments ADD CONSTRAINT fk_payments_reservation
                FOREIGN KEY (reservation_id) REFERENCES reservations (id)
        SQL);

        DB::statement('ALTER TABLE payments ADD KEY idx_payments_reservation (reservation_id, status)');

        DB::statement(<<<'SQL'
            ALTER TABLE payments ADD CONSTRAINT ck_payments_target CHECK (
                (table_session_id IS NOT NULL AND reservation_id IS NULL)
             OR (table_session_id IS NULL     AND reservation_id IS NOT NULL)
            )
        SQL);
    }

    public function down(): void
    {
        // Thứ tự bắt buộc: CHECK trước (không phụ thuộc gì), rồi FK (MariaDB
        // từ chối xoá idx_payments_reservation trong khi FK còn dùng nó), rồi
        // mới tới KEY, cuối cùng là cột.
        DB::statement('ALTER TABLE payments DROP CONSTRAINT ck_payments_target');
        DB::statement('ALTER TABLE payments DROP CONSTRAINT fk_payments_reservation');
        DB::statement('ALTER TABLE payments DROP KEY idx_payments_reservation');
        DB::statement('ALTER TABLE payments DROP COLUMN reservation_id');
        DB::statement('ALTER TABLE payments MODIFY COLUMN table_session_id BIGINT UNSIGNED NOT NULL');
    }
};
