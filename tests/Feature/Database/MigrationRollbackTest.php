<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

/** 15 bảng đúng thứ tự trong docs/schema.md */
function danhSachBang(): array
{
    return [
        'users',
        'shifts',
        'cash_movements',
        'dining_tables',
        'table_sessions',
        'table_session_tables',
        'categories',
        'products',
        'product_variants',
        'option_groups',
        'options',
        'orders',
        'order_items',
        'order_item_options',
        'payments',
    ];
}

it('migrate tạo đủ 15 bảng theo docs/schema.md', function () {
    foreach (danhSachBang() as $bang) {
        expect(Schema::hasTable($bang))->toBeTrue("Thiếu bảng {$bang} sau khi migrate.");
    }
});

it('rollback xoá sạch cả 15 bảng, không lỗi ràng buộc khoá ngoại', function () {
    // Không hardcode số bước: từ khi có thêm migration hạ tầng (Sanctum, Activitylog)
    // nằm cùng batch với 15 migration nghiệp vụ, rollback nguyên batch mới xoá sạch đúng cả 15 bảng.
    Artisan::call('migrate:rollback');

    foreach (danhSachBang() as $bang) {
        expect(Schema::hasTable($bang))->toBeFalse("Bảng {$bang} vẫn còn sau khi rollback.");
    }

    // Khôi phục lại schema để không ảnh hưởng các test khác chạy sau trong cùng tiến trình.
    Artisan::call('migrate');

    foreach (danhSachBang() as $bang) {
        expect(Schema::hasTable($bang))->toBeTrue("Bảng {$bang} không được tạo lại sau khi migrate lần hai.");
    }
});
