<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 Bước 6 — chương trình khuyến mãi chủ quán tự cấu hình: giảm %,
 * giảm tiền cố định, hoặc giờ vàng (giảm % chỉ trong khung giờ/ngày nhất định).
 *
 * `target_id` là cột KHÔNG có khoá ngoại — nó trỏ tới `categories.id` HOẶC
 * `products.id` tuỳ `applies_to`, một cột không thể có hai khoá ngoại cùng
 * lúc. Đổi lại, `App\Domain\Billing\Actions\ApplyPromotion` tự kiểm tra đúng
 * bảng tương ứng tồn tại trước khi dùng, và Filament chỉ cho chọn từ đúng
 * danh sách category/product khi tạo.
 *
 * Ràng buộc CHECK viết bằng SQL thô vì Laravel Blueprint chưa hỗ trợ CHECK,
 * giống mọi bảng giao dịch khác trong dự án (xem payments, order_items).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();

            $table->string('code', 30)->comment('Mã khuyến mãi, thu ngân gõ hoặc chọn: HAPPYHOUR20');
            $table->string('name', 100)->comment('Tên hiển thị: "Giờ vàng bia 17h-19h"');

            $table->enum('type', ['percent', 'amount', 'happy_hour'])
                ->comment('percent/happy_hour: value là %. amount: value là số tiền cố định (đồng)');
            $table->unsignedBigInteger('value')->comment('percent/happy_hour: 1-100. amount: số đồng');

            $table->unsignedBigInteger('min_order_amount')->nullable()->comment('Tạm tính tối thiểu của cả lượt khách, NULL = không giới hạn');
            $table->unsignedBigInteger('max_discount_amount')->nullable()->comment('Trần số tiền được giảm, NULL = không trần');

            $table->dateTime('starts_at')->nullable()->comment('NULL = không giới hạn ngày bắt đầu');
            $table->dateTime('ends_at')->nullable()->comment('NULL = không giới hạn ngày kết thúc');
            $table->json('time_rules')->nullable()->comment('{"days":[0..6],"from":"17:00","to":"19:00"} — NULL = mọi giờ mọi ngày. Bắt buộc có khi type=happy_hour');

            $table->enum('applies_to', ['all', 'category', 'product'])->default('all');
            $table->unsignedBigInteger('target_id')->nullable()->comment('id của categories/products khi applies_to khác all — không có FK, xem docblock đầu file');

            $table->unsignedInteger('max_usage')->nullable()->comment('Tổng số lần được dùng, NULL = không giới hạn');
            $table->unsignedInteger('used_count')->default(0);

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique('code', 'uq_promotions_code');
            $table->index(['is_active', 'starts_at', 'ends_at'], 'idx_promotions_active_dates');
        });

        DB::statement('ALTER TABLE promotions ADD CONSTRAINT ck_promotions_value CHECK (value > 0)');
        DB::statement("ALTER TABLE promotions ADD CONSTRAINT ck_promotions_percent CHECK (type NOT IN ('percent', 'happy_hour') OR value <= 100)");
        DB::statement("ALTER TABLE promotions ADD CONSTRAINT ck_promotions_target CHECK (applies_to = 'all' OR target_id IS NOT NULL)");
        DB::statement('ALTER TABLE promotions ADD CONSTRAINT ck_promotions_usage CHECK (max_usage IS NULL OR used_count <= max_usage)');
        DB::statement('ALTER TABLE promotions ADD CONSTRAINT ck_promotions_dates CHECK (starts_at IS NULL OR ends_at IS NULL OR starts_at < ends_at)');
    }

    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};
