<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3 Bước 10 — hai cột "độ tin cậy" cho bảng lãi gộp theo món.
 *
 * Vì sao cần: `cost_amount` về 0 ở HAI trường hợp hoàn toàn khác nhau, mà
 * trước đây bảng này không phân biệt được, nên lãi gộp trông cao hơn thật mà
 * không ai biết:
 *
 *   1. Bán lúc kho đang âm → sổ cái có ghi dòng nhưng `has_cost = false`,
 *      `cost_delta = 0`. Giá vốn KHÔNG xác định được, không phải bằng 0.
 *   2. Bếp quên bấm "xong" → không có `served_at` → chưa trừ kho → không có
 *      dòng sổ cái nào, trong khi doanh thu vẫn tính đủ.
 *
 * Hai cột dưới KHÔNG sửa `cost_amount` và KHÔNG bịa giá vốn — chúng chỉ đếm
 * xem bao nhiêu phần trong con số lãi là không đáng tin, để màn hình chủ quán
 * treo được dấu cảnh báo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_profit_daily', function (Blueprint $table) {
            $table->unsignedInteger('qty_no_cost')->default(0)->after('cost_amount')
                ->comment('Số lượng bán mà không xác định được giá vốn (bán lúc tồn âm, has_cost=false)');
            $table->unsignedInteger('qty_not_served')->default(0)->after('qty_no_cost')
                ->comment('Số lượng đã tính tiền nhưng bếp chưa bấm xong (lượt khách đã đóng mà thiếu served_at)');
        });
    }

    public function down(): void
    {
        Schema::table('product_profit_daily', function (Blueprint $table) {
            $table->dropColumn(['qty_no_cost', 'qty_not_served']);
        });
    }
};
