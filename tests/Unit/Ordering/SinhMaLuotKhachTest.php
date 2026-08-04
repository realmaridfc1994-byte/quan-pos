<?php

declare(strict_types=1);

use App\Domain\Ordering\Actions\OpenTableSession;
use Carbon\Carbon;

/**
 * sinhMaLuotKhach() là hàm tính toán thuần (id + thời điểm mở → chuỗi mã),
 * không đụng database — gọi qua reflection vì là hàm private, không đổi
 * thành public chỉ để phục vụ test.
 */
function goiSinhMaLuotKhach(int $id, Carbon $openedAt): string
{
    $action = new OpenTableSession;
    $method = new ReflectionMethod($action, 'sinhMaLuotKhach');
    $method->setAccessible(true);

    return $method->invoke($action, $id, $openedAt);
}

it('lượt khách mở lúc 23:50 nhưng đồng bộ/sinh mã lúc 00:10 hôm sau vẫn mang mã ngày HÔM QUA', function () {
    $moLuc = Carbon::parse('2026-08-03 23:50:00');

    Carbon::setTestNow(Carbon::parse('2026-08-04 00:10:00'));
    $ma = goiSinhMaLuotKhach(7, $moLuc);
    Carbon::setTestNow();

    expect($ma)->toBe('PH-20260803-0007')
        ->and($ma)->not->toContain('20260804');
});

it('mở bàn bình thường, opened_at đúng bằng lúc sinh mã, thì mã không đổi so với hiện tại', function () {
    $luc = Carbon::parse('2026-08-04 19:00:00');

    Carbon::setTestNow($luc);
    $ma = goiSinhMaLuotKhach(3, $luc);
    Carbon::setTestNow();

    expect($ma)->toBe('PH-20260804-0003');
});
