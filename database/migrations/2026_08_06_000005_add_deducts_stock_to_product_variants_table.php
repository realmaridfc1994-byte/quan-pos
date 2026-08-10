<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3 Bước 3 — cột đánh dấu biến thể nào trừ kho theo định lượng (`recipes`).
 *
 * Chỉ HAI trạng thái, đúng nguyên tắc "một đường duy nhất" ở docs/schema.md K.1:
 * deducts_stock = 1 (có định lượng, đọc từ `recipes`) hoặc = 0 (không trừ kho,
 * ví dụ phí phục vụ, khăn lạnh). Không có "trừ thẳng" riêng — bia lon cũng đi
 * qua `recipes` (một dòng, số lượng 1).
 *
 * Trigger chặn Ở TẦNG DỮ LIỆU: không thêm được dòng `recipes` cho một biến thể
 * đang đánh dấu deducts_stock = 0 — tránh vừa nói "không trừ kho" vừa có định
 * lượng treo ở đó không bao giờ được dùng.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->boolean('deducts_stock')->default(false)->after('stock_factor')
                ->comment('PHASE 3 Bước 3: biến thể này có trừ kho theo định lượng (recipes) không');
        });

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER trg_recipes_deducts_stock_insert BEFORE INSERT ON recipes
            FOR EACH ROW
            BEGIN
                DECLARE v_deducts TINYINT(1);
                SELECT deducts_stock INTO v_deducts FROM product_variants WHERE id = NEW.product_variant_id;
                IF v_deducts IS NULL OR v_deducts = 0 THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Biến thể này chưa đánh dấu trừ kho theo định lượng, không thêm được nguyên liệu định lượng.';
                END IF;
            END
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER trg_recipes_deducts_stock_update BEFORE UPDATE ON recipes
            FOR EACH ROW
            BEGIN
                DECLARE v_deducts TINYINT(1);
                SELECT deducts_stock INTO v_deducts FROM product_variants WHERE id = NEW.product_variant_id;
                IF v_deducts IS NULL OR v_deducts = 0 THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Biến thể này chưa đánh dấu trừ kho theo định lượng, không thêm được nguyên liệu định lượng.';
                END IF;
            END
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_recipes_deducts_stock_insert');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_recipes_deducts_stock_update');

        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn('deducts_stock');
        });
    }
};
