<?php

declare(strict_types=1);

use App\Domain\Staffing\Actions\OpenShift;
use Carbon\Carbon;

/**
 * sinhMaCa() là hàm tính toán thuần (id + thời điểm mở ca → chuỗi mã),
 * không đụng database — gọi qua reflection vì là hàm private, không đổi
 * thành public chỉ để phục vụ test.
 */
function goiSinhMaCa(int $id, Carbon $openedAt): string
{
    $action = new OpenShift;
    $method = new ReflectionMethod($action, 'sinhMaCa');
    $method->setAccessible(true);

    return $method->invoke($action, $id, $openedAt);
}

it('ca mở lúc 23:50 nhưng đồng bộ/sinh mã lúc 00:10 hôm sau vẫn mang mã ngày HÔM QUA', function () {
    $moLuc = Carbon::parse('2026-08-03 23:50:00');

    Carbon::setTestNow(Carbon::parse('2026-08-04 00:10:00'));
    $ma = goiSinhMaCa(9, $moLuc);
    Carbon::setTestNow();

    expect($ma)->toBe('CA-20260803-09')
        ->and($ma)->not->toContain('20260804');
});

it('mở ca bình thường, opened_at đúng bằng lúc sinh mã, thì mã không đổi so với hiện tại', function () {
    $luc = Carbon::parse('2026-08-04 08:00:00');

    Carbon::setTestNow($luc);
    $ma = goiSinhMaCa(2, $luc);
    Carbon::setTestNow();

    expect($ma)->toBe('CA-20260804-02');
});
