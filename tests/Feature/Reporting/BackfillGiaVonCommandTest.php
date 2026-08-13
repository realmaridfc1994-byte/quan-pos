<?php

declare(strict_types=1);

/**
 * Phase 4 Bước 4B.0 — lệnh `report:backfill-gia-von`.
 *
 * Điều quan trọng nhất phải chứng minh: cờ `--thu` (chạy thử) KHÔNG GHI MỘT
 * CHỮ NÀO. Lệnh này đổi con số lãi gộp của những ngày đã qua, nên chủ quán
 * phải xem được nó đổi bao nhiêu TRƯỚC khi nó đổi. Một chế độ "chạy thử" mà
 * lỡ ghi thì tệ hơn không có chế độ nào.
 */

use App\Domain\Reporting\Actions\SummarizeDailyReport;
use App\Domain\Reporting\Actions\SummarizeProductProfit;
use App\Domain\Reporting\Models\DailySummary;
use App\Domain\Reporting\Models\ProductProfitDaily;
use Illuminate\Support\Facades\Artisan;

require_once __DIR__.'/../../Fixtures/BuoiToiMeo.php';

/**
 * Dựng lại ĐÚNG tình trạng của dữ liệu lịch sử ngay sau khi chạy migration:
 * ngày đó đã được tổng hợp bằng code CŨ, nên số liệu có đủ, chỉ ba cột mới là
 * bằng 0 — và vì vậy lãi gộp của ngày đó vẫn đang nói dối (coi phần không biết
 * giá vốn là lãi 100%).
 *
 * Đưa ba cột về 0 chứ không tự bịa số: `profit_amount` là cột máy tự tính, hạ
 * `revenue_uncosted_amount` xuống 0 là nó quay lại đúng công thức cũ.
 */
function chepSoLieuKieuCu(string $ngay): void
{
    app(SummarizeDailyReport::class)->handle($ngay);
    app(SummarizeProductProfit::class)->handle($ngay);

    DailySummary::query()->where('date', $ngay)->update([
        'item_revenue_amount' => 0,
        'item_revenue_uncosted_amount' => 0,
    ]);
    ProductProfitDaily::query()->where('date', $ngay)->update(['revenue_uncosted_amount' => 0]);
}

it('chạy thử không ghi một chữ nào xuống database', function () {
    $meo = dungBuoiToiMeo();
    $ngay = $meo['ngay']->toDateString();

    chepSoLieuKieuCu($ngay);
    $truoc = DailySummary::query()->where('date', $ngay)->sole()->only([
        'revenue_amount', 'item_revenue_amount', 'item_revenue_uncosted_amount',
    ]);

    Artisan::call("report:backfill-gia-von --tu={$ngay} --den={$ngay} --thu");

    $sau = DailySummary::query()->where('date', $ngay)->sole()->only([
        'revenue_amount', 'item_revenue_amount', 'item_revenue_uncosted_amount',
    ]);

    expect($sau)->toBe($truoc)
        ->and($sau['item_revenue_uncosted_amount'])->toBe(0)
        // Bảng lãi gộp theo món cũng phải y nguyên số cũ, chưa được sửa chữ nào.
        ->and((int) ProductProfitDaily::query()->where('date', $ngay)->sum('revenue_uncosted_amount'))->toBe(0)
        ->and((int) ProductProfitDaily::query()->where('date', $ngay)->sum('profit_amount'))->toBe(480_000);
});

it('chạy thử vẫn in ra được con số MỚI để chủ quán đọc trước khi quyết', function () {
    $meo = dungBuoiToiMeo();
    $ngay = $meo['ngay']->toDateString();

    chepSoLieuKieuCu($ngay);

    Artisan::call("report:backfill-gia-von --tu={$ngay} --den={$ngay} --thu");
    $ketQua = Artisan::output();

    expect($ketQua)->toContain('CHẠY THỬ')
        ->toContain('Đã quay lui sạch')
        // Lãi gộp cũ 480.000đ tụt xuống 100.000đ, phần chưa biết 380.000đ.
        ->toContain('480.000 đ')
        ->toContain('100.000 đ')
        ->toContain('380.000 đ');
});

it('chạy thật thì ghi đủ hai cột mới cho ngày cũ', function () {
    $meo = dungBuoiToiMeo();
    $ngay = $meo['ngay']->toDateString();

    chepSoLieuKieuCu($ngay);

    $ma = Artisan::call("report:backfill-gia-von --tu={$ngay} --den={$ngay}");

    expect($ma)->toBe(0);

    $tomTat = DailySummary::query()->where('date', $ngay)->sole();
    expect($tomTat->item_revenue_amount)->toBe(580_000)
        ->and($tomTat->item_revenue_uncosted_amount)->toBe(380_000)
        // Doanh thu đã thu vào két KHÔNG đổi — chỉ cách báo cáo đổi.
        ->and($tomTat->revenue_amount)->toBe(580_000);
});

it('doanh thu ngày cũ bị đổi thì DỪNG và quay lui, không ghi đè lặng lẽ', function () {
    $meo = dungBuoiToiMeo();
    $ngay = $meo['ngay']->toDateString();

    // Số cũ ghi nhầm 900.000đ — tính lại sẽ ra 580.000đ.
    DailySummary::factory()->create([
        'date' => $ngay,
        'revenue_amount' => 900_000,
        'cash_amount' => 900_000,
    ]);

    $ma = Artisan::call("report:backfill-gia-von --tu={$ngay} --den={$ngay}");

    expect($ma)->toBe(1)
        ->and(Artisan::output())->toContain('DỪNG LẠI');

    // Không ghi đè gì cả — số cũ còn nguyên để đối chiếu.
    $tomTat = DailySummary::query()->where('date', $ngay)->sole();
    expect($tomTat->revenue_amount)->toBe(900_000)
        ->and($tomTat->item_revenue_uncosted_amount)->toBe(0);
});

it('người chạy đọc số rồi đồng ý thì mới cho ghi đè doanh thu đổi', function () {
    $meo = dungBuoiToiMeo();
    $ngay = $meo['ngay']->toDateString();

    DailySummary::factory()->create([
        'date' => $ngay,
        'revenue_amount' => 900_000,
        'cash_amount' => 900_000,
    ]);

    $ma = Artisan::call("report:backfill-gia-von --tu={$ngay} --den={$ngay} --dong-y-doanh-thu-doi");

    expect($ma)->toBe(0);

    $tomTat = DailySummary::query()->where('date', $ngay)->sole();
    expect($tomTat->revenue_amount)->toBe(580_000)
        ->and($tomTat->item_revenue_uncosted_amount)->toBe(380_000);
});

it('không ghi khoảng ngày thì lệnh từ chối chạy, không tự tính cả lịch sử', function () {
    $ma = Artisan::call('report:backfill-gia-von');

    expect($ma)->toBe(1)
        ->and(Artisan::output())->toContain('Phải ghi rõ khoảng ngày');
});

it('ngày đầu sau ngày cuối thì từ chối chạy', function () {
    $ma = Artisan::call('report:backfill-gia-von --tu=2026-08-20 --den=2026-08-10');

    expect($ma)->toBe(1)
        ->and(Artisan::output())->toContain('phải trước hoặc bằng');
});
