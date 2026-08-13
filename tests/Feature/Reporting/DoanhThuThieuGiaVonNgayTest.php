<?php

declare(strict_types=1);

/**
 * Phase 4 Bước 4B.0 — chạy trên bộ dữ liệu CỐ Ý DỰNG MÉO
 * (`tests/Fixtures/BuoiToiMeo.php`: 580.000đ doanh thu, chỉ 200.000đ biết giá
 * vốn, 380.000đ không ai biết tốn bao nhiêu).
 *
 * Ba điều phải đúng, và đây cũng là ba điều kiện đóng bước:
 *   - Lãi gộp phải GIẢM so với công thức cũ (không còn coi phần không biết
 *     giá vốn là lãi 100%)
 *   - Doanh thu phải ĐỨNG YÊN (sửa cách BÁO CÁO, không sửa doanh thu)
 *   - Hai cột mới phải KHỚP với dữ liệu thật trong fixture
 */

use App\Domain\Reporting\Actions\SummarizeDailyReport;
use App\Domain\Reporting\Actions\SummarizeProductProfit;
use App\Domain\Reporting\Models\DailySummary;
use App\Domain\Reporting\Models\ProductProfitDaily;

require_once __DIR__.'/../../Fixtures/BuoiToiMeo.php';

it('một buổi tối méo: lãi gộp GIẢM đúng bằng phần không biết giá vốn, không còn tính thành lãi 100%', function () {
    $meo = dungBuoiToiMeo();

    app(SummarizeProductProfit::class)->handle($meo['ngay']->toDateString());

    $dong = ProductProfitDaily::query()->where('date', $meo['ngay']->toDateString())->get();

    $doanhThu = (int) $dong->sum('revenue_amount');
    $giaVon = (int) $dong->sum('cost_amount');
    $chuaBiet = (int) $dong->sum('revenue_uncosted_amount');

    // Công thức CŨ (trước Bước 4B.0): coi mọi giá vốn không xác định là 0 đồng.
    $laiGopCu = $doanhThu - $giaVon;
    // Công thức MỚI: chỉ tính trên phần ĐÃ biết giá vốn.
    $laiGopMoi = $doanhThu - $chuaBiet - $giaVon;

    expect($doanhThu)->toBe(580_000)
        ->and($giaVon)->toBe(100_000)
        // 4 phần lẩu (200.000đ) + 6 phần nem bếp quên bấm (180.000đ)
        ->and($chuaBiet)->toBe(380_000)
        ->and($laiGopCu)->toBe(480_000)
        // ĐIỂM MẤU CHỐT: lãi gộp phải TỤT đúng 380.000đ, không nguỵ trang nữa.
        ->and($laiGopMoi)->toBe(100_000)
        ->and($laiGopCu - $laiGopMoi)->toBe($chuaBiet);
});

it('món bán lúc kho âm trả lãi gộp NULL, KHÔNG phải bằng doanh thu', function () {
    $meo = dungBuoiToiMeo();

    app(SummarizeProductProfit::class)->handle($meo['ngay']->toDateString());

    $lauGa = ProductProfitDaily::query()->where('product_variant_id', $meo['bien_the_am']->id)->sole();

    expect($lauGa->revenue_amount)->toBe(200_000)
        ->and($lauGa->cost_amount)->toBe(0)
        ->and($lauGa->qty_no_cost)->toBe(4)
        ->and($lauGa->revenue_uncosted_amount)->toBe(200_000)
        // Không được là 200.000 (bug cũ = doanh thu), không được là 0 (lẫn với
        // "hoà vốn thật") — phải là NULL: không ai biết.
        ->and($lauGa->profit_amount)->toBeNull();

    // Đối chiếu: món kho đủ vẫn ra lãi gộp bình thường, không bị vạ lây.
    $gaNuong = ProductProfitDaily::query()->where('product_variant_id', $meo['bien_the_du']->id)->sole();
    expect($gaNuong->revenue_uncosted_amount)->toBe(0)
        ->and($gaNuong->profit_amount)->toBe(100_000);
});

it('doanh thu chưa xác nhận phục vụ nằm ở nhóm tách riêng, không trộn vào lãi gộp', function () {
    $meo = dungBuoiToiMeo();

    app(SummarizeProductProfit::class)->handle($meo['ngay']->toDateString());

    $nemRan = ProductProfitDaily::query()->where('product_variant_id', $meo['bien_the_quen']->id)->sole();

    expect($nemRan->revenue_amount)->toBe(180_000)
        ->and($nemRan->qty_not_served)->toBe(6)
        ->and($nemRan->qty_no_cost)->toBe(0)
        // Toàn bộ doanh thu món này nằm ở nhóm tách riêng...
        ->and($nemRan->revenue_uncosted_amount)->toBe(180_000)
        // ...nên không một đồng nào của nó chảy vào lãi gộp.
        ->and($nemRan->profit_amount)->toBeNull();
});

it('doanh thu ĐỨNG YÊN sau khi sửa cách báo cáo — chỉ cách chia nhóm đổi', function () {
    $meo = dungBuoiToiMeo();

    app(SummarizeDailyReport::class)->handle($meo['ngay']->toDateString());
    app(SummarizeProductProfit::class)->handle($meo['ngay']->toDateString());

    $tomTat = DailySummary::query()->where('date', $meo['ngay']->toDateString())->sole();

    // Tiền đã thu vào két: đúng bằng số khách trả, không đổi một đồng.
    expect($tomTat->revenue_amount)->toBe(580_000)
        ->and($tomTat->cash_amount)->toBe(580_000)
        // Tổng doanh thu theo dòng món cũng đứng nguyên.
        ->and($tomTat->item_revenue_amount)->toBe(580_000);
});

it('hai cột mới ở bảng tóm tắt ngày khớp đúng dữ liệu trong fixture', function () {
    $meo = dungBuoiToiMeo();

    app(SummarizeDailyReport::class)->handle($meo['ngay']->toDateString());
    app(SummarizeProductProfit::class)->handle($meo['ngay']->toDateString());

    $tomTat = DailySummary::query()->where('date', $meo['ngay']->toDateString())->sole();
    $theoMon = ProductProfitDaily::query()->where('date', $meo['ngay']->toDateString())->get();

    expect($tomTat->item_revenue_amount)->toBe(580_000)
        ->and($tomTat->item_revenue_uncosted_amount)->toBe(380_000)
        // Hai bảng phải nói CÙNG một con số về cùng một buổi tối.
        ->and($tomTat->item_revenue_amount)->toBe((int) $theoMon->sum('revenue_amount'))
        ->and($tomTat->item_revenue_uncosted_amount)->toBe((int) $theoMon->sum('revenue_uncosted_amount'));

    // Con số màn hình chủ quán sẽ hiện ở bước sau: 380.000 trên 580.000 = 65%.
    expect(intdiv($tomTat->item_revenue_uncosted_amount * 100, $tomTat->item_revenue_amount))->toBe(65);
});

it('chạy lại nhiều lần cho cùng một ngày thì ghi đè, không cộng dồn', function () {
    $meo = dungBuoiToiMeo();

    app(SummarizeDailyReport::class)->handle($meo['ngay']->toDateString());
    app(SummarizeDailyReport::class)->handle($meo['ngay']->toDateString());

    $tomTat = DailySummary::query()->where('date', $meo['ngay']->toDateString())->sole();

    expect($tomTat->item_revenue_amount)->toBe(580_000)
        ->and($tomTat->item_revenue_uncosted_amount)->toBe(380_000);
});

it('ngày không bán gì thì hai cột mới bằng 0, không nổ lỗi chia cho 0', function () {
    app(SummarizeDailyReport::class)->handle('2026-08-19');

    $tomTat = DailySummary::query()->where('date', '2026-08-19')->sole();

    expect($tomTat->item_revenue_amount)->toBe(0)
        ->and($tomTat->item_revenue_uncosted_amount)->toBe(0);
});
