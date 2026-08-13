<?php

declare(strict_types=1);

/**
 * Phase 4 Bước 4B.1 — LÁ CHẮN: báo cáo KHÔNG BAO GIỜ đọc bảng giao dịch.
 *
 * Vì sao gắt tới mức có một file test riêng:
 *
 *  1. Bảng giao dịch thay đổi từng phút trong lúc bán. Báo cáo đọc thẳng
 *     `orders` thì mở hai lần cách nhau 30 giây ra hai con số khác nhau, và
 *     không ai giải thích được cho chủ quán vì sao.
 *  2. Một câu truy vấn nặng trên `order_items` lúc 8 giờ tối làm chậm chính
 *     cái máy đang thu tiền của khách.
 *
 * Cách gác: nghe TOÀN BỘ câu SQL sinh ra trong một lần gọi endpoint, rồi
 * khẳng định không câu nào chạm bảng giao dịch. Không dựa vào việc đọc code —
 * người sau thêm một `whereHas('order')` là test này đỏ ngay.
 */

use App\Domain\Ordering\Models\Order;
use App\Domain\Reporting\Actions\SummarizeDailyReport;
use App\Domain\Reporting\Actions\SummarizeProductProfit;
use App\Domain\Staffing\Models\User;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/../../Fixtures/BuoiToiMeo.php';

/** Bảng giao dịch — báo cáo không được đụng cái nào. */
const BANG_GIAO_DICH = [
    'orders',
    'order_items',
    'order_item_options',
    'table_sessions',
    'table_session_tables',
    'payments',
    'stock_movements',
    'cash_movements',
];

/** @return list<string> các câu SQL đã chạm bảng giao dịch */
function cauSqlChamBangGiaoDich(callable $viec): array
{
    $viPham = [];

    DB::listen(function ($truyVan) use (&$viPham): void {
        foreach (BANG_GIAO_DICH as $bang) {
            // Tên bảng trong SQL của Laravel luôn nằm trong dấu backtick.
            if (str_contains($truyVan->sql, "`{$bang}`")) {
                $viPham[] = "[{$bang}] {$truyVan->sql}";

                return;
            }
        }
    });

    $viec();

    return $viPham;
}

it('endpoint báo cáo không sinh câu truy vấn nào chạm bảng giao dịch', function () {
    $meo = dungBuoiToiMeo();
    $ngay = $meo['ngay']->toDateString();

    app(SummarizeDailyReport::class)->handle($ngay);
    app(SummarizeProductProfit::class)->handle($ngay);

    $chuQuan = User::factory()->owner()->create();
    $header = authHeaderFor($chuQuan);

    // Chỉ bắt đầu nghe SAU KHI đã dựng xong dữ liệu và đã tạo token — phần
    // dựng dữ liệu tất nhiên phải ghi vào bảng giao dịch, đó là chuyện khác.
    $viPham = cauSqlChamBangGiaoDich(function () use ($ngay, $header): void {
        test()->getJson("/api/v1/reports/summary?tu={$ngay}&den={$ngay}", $header)->assertOk();
    });

    expect($viPham)->toBe([], "Báo cáo đã đọc bảng giao dịch:\n".implode("\n", $viPham));
});

it('bộ gác này bắt được đúng kiểu vi phạm', function () {
    // Chứng minh test trên không phải cái lưới thủng: cố tình đọc một bảng
    // giao dịch và kiểm xem bộ gác có kêu không.
    $viPham = cauSqlChamBangGiaoDich(function (): void {
        Order::query()->count();
    });

    expect($viPham)->not->toBe([]);
    expect($viPham[0])->toContain('[orders]');
});
