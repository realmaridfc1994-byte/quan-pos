<?php

declare(strict_types=1);

use App\Domain\Ordering\Models\OrderItem;
use App\Domain\Ordering\Models\OrderItemOption;
use App\Domain\Ordering\Models\TableSession;
use App\Domain\Staffing\Models\Shift;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

it('gán uuid cho dữ liệu cũ chưa có ở cả ba bảng, không đụng dòng đã có uuid', function () {
    // TableSessionFactory tự tạo Shift riêng (mặc định "open") — hai lượt khách
    // độc lập trong cùng test phải dùng chung một ca, nếu không đụng ràng buộc
    // "chỉ một ca mở cùng lúc" (uq_shifts_only_one_open).
    $ca = Shift::factory()->closed()->create();

    $luotCu = TableSession::factory()->create(['shift_id' => $ca->id]);
    $dongMonCu = OrderItem::factory()->create();
    $tuyChonCu = OrderItemOption::factory()->for($dongMonCu, 'orderItem')->create();

    // Factory đã mặc định sinh uuid — giả lập dữ liệu THẬT SỰ cũ bằng cách xoá uuid đi.
    DB::table('table_sessions')->where('id', $luotCu->id)->update(['uuid' => null]);
    DB::table('order_items')->where('id', $dongMonCu->id)->update(['uuid' => null]);
    DB::table('order_item_options')->where('id', $tuyChonCu->id)->update(['uuid' => null]);

    $luotDaCoUuid = TableSession::factory()->create(['shift_id' => $ca->id]);
    $uuidDaCo = $luotDaCoUuid->uuid;

    Artisan::call('pos:backfill-uuid');

    expect($luotCu->refresh()->uuid)->not->toBeNull()
        ->and($dongMonCu->refresh()->uuid)->not->toBeNull()
        ->and($tuyChonCu->refresh()->uuid)->not->toBeNull();

    // Dòng đã có uuid từ trước không bị đổi.
    expect($luotDaCoUuid->refresh()->uuid)->toBe($uuidDaCo);
});

it('chạy lại lần hai không lỗi, không còn dòng nào thiếu uuid', function () {
    TableSession::factory()->create();
    DB::table('table_sessions')->update(['uuid' => null]);

    Artisan::call('pos:backfill-uuid');
    $maLan1 = Artisan::call('pos:backfill-uuid');

    expect($maLan1)->toBe(0)
        ->and(TableSession::query()->whereNull('uuid')->count())->toBe(0);
});
