<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase 3 Bước 10 — khoá bất biến K1 ("sổ cái không bao giờ sửa") ở TẦNG
 * DATABASE, không chỉ ở tầng ứng dụng.
 *
 * Vì sao cần thêm lớp này khi Model đã chặn rồi: chốt ở
 * App\Domain\Inventory\Models\StockMovement chỉ bắt được code đi qua Eloquent.
 * Một câu `DB::table('stock_movements')->update(...)`, một dòng SQL gõ tay
 * trong phpMyAdmin, hay một script vá dữ liệu viết vội đều đi vòng qua nó.
 * Sổ cái là chỗ duy nhất làm chứng được kho đã đi đâu về đâu — nó phải được
 * giữ bởi chính database, không bởi thiện chí của người viết code.
 *
 * Hai lớp KHÔNG thừa nhau, chúng bắt hai loại lỗi khác nhau:
 *   - Lớp Model: báo lỗi tiếng Việt sớm, ngay trong code, kèm ngữ cảnh.
 *   - Lớp trigger: chốt cuối cùng, không ai đi vòng được.
 *
 * Trigger chỉ chặn UPDATE. INSERT vẫn chạy bình thường (đó là việc của
 * RecordStockMovement) và DELETE cũng không bị đụng tới ở đây — xoá đã được
 * chặn ở tầng Model, và về mặt dữ liệu thì các khoá ngoại trỏ vào sổ cái
 * (stock_balances.last_movement_id, stock_reconciliation_notes) đã chặn phần
 * lớn đường xoá rồi.
 *
 * HỆ QUẢ ĐÃ CÂN NHẮC: sau migration này không còn đường nào vá tay một cột
 * trên sổ cái, kể cả lệnh `stock:backfill-uuid`. Chấp nhận có chủ đích —
 * migration 2026_08_11_000002 đã siết `uuid NOT NULL` nên không thể còn dòng
 * nào cần vá; và nếu tương lai thật sự cần sửa, việc đó phải là một quyết
 * định có ý thức (tạm gỡ trigger), không phải một câu UPDATE lỡ tay.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER trg_stock_movements_no_update BEFORE UPDATE ON stock_movements
            FOR EACH ROW
            BEGIN
                SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'stock_movements la so cai chi ghi them, khong duoc sua. Ghi sai thi ghi them dong bu tru.';
            END
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_stock_movements_no_update');
    }
};
