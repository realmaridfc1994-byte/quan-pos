<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Reporting\Models\DailySummary;
use App\Domain\Reporting\Models\ProductSaleDaily;
use App\Domain\Reporting\Queries\GetOwnerDashboard;
use Illuminate\Support\Carbon;

it('so sánh doanh thu hôm nay, hôm qua, cùng thứ tuần trước', function () {
    $homNay = Carbon::today();

    DailySummary::factory()->create(['date' => $homNay->toDateString(), 'revenue_amount' => 300_000, 'table_session_count' => 5]);
    DailySummary::factory()->create(['date' => $homNay->clone()->subDay()->toDateString(), 'revenue_amount' => 250_000, 'table_session_count' => 4]);
    DailySummary::factory()->create(['date' => $homNay->clone()->subWeek()->toDateString(), 'revenue_amount' => 200_000, 'table_session_count' => 3]);

    $ketQua = app(GetOwnerDashboard::class)->handle();

    expect($ketQua['hom_nay']['revenue_amount'])->toBe(300_000)
        ->and($ketQua['hom_qua']['revenue_amount'])->toBe(250_000)
        ->and($ketQua['cung_thu_tuan_truoc']['revenue_amount'])->toBe(200_000);
});

it('ngày chưa có dữ liệu tổng hợp thì hiện 0, không lỗi', function () {
    $ketQua = app(GetOwnerDashboard::class)->handle();

    expect($ketQua['hom_nay']['revenue_amount'])->toBe(0)
        ->and($ketQua['hom_nay']['table_session_count'])->toBe(0);
});

it('biểu đồ 7 ngày đủ đúng 7 điểm, đúng thứ tự từ cũ đến mới', function () {
    $homNay = Carbon::today();
    DailySummary::factory()->create(['date' => $homNay->toDateString(), 'revenue_amount' => 111_000]);
    DailySummary::factory()->create(['date' => $homNay->clone()->subDays(3)->toDateString(), 'revenue_amount' => 222_000]);

    $ketQua = app(GetOwnerDashboard::class)->handle();
    $bieuDo = $ketQua['bieu_do_7_ngay'];

    expect($bieuDo)->toHaveCount(7)
        ->and($bieuDo[6]['date'])->toBe($homNay->toDateString())
        ->and($bieuDo[6]['revenue_amount'])->toBe(111_000)
        ->and($bieuDo[3]['revenue_amount'])->toBe(222_000)
        ->and($bieuDo[0]['date'])->toBe($homNay->clone()->subDays(6)->toDateString());
});

it('top 10 món bán chạy tuần này, sắp theo số lượng giảm dần', function () {
    $homNay = Carbon::today();
    $category = Category::factory()->create();
    $monBanChay = Product::factory()->for($category)->create(['name' => 'Bia Tiger']);
    $monItKhach = Product::factory()->for($category)->create(['name' => 'Nước suối']);

    ProductSaleDaily::factory()->create(['date' => $homNay->toDateString(), 'product_id' => $monBanChay->id, 'quantity_sold' => 50, 'revenue_amount' => 1_250_000]);
    ProductSaleDaily::factory()->create(['date' => $homNay->clone()->subDays(2)->toDateString(), 'product_id' => $monBanChay->id, 'quantity_sold' => 30, 'revenue_amount' => 750_000]);
    ProductSaleDaily::factory()->create(['date' => $homNay->toDateString(), 'product_id' => $monItKhach->id, 'quantity_sold' => 2, 'revenue_amount' => 20_000]);
    // Ngoài tuần này — KHÔNG được tính.
    ProductSaleDaily::factory()->create(['date' => $homNay->clone()->subDays(10)->toDateString(), 'product_id' => $monBanChay->id, 'quantity_sold' => 999, 'revenue_amount' => 9_990_000]);

    $ketQua = app(GetOwnerDashboard::class)->handle();
    $top = $ketQua['top_10_mon_tuan_nay'];

    expect($top[0]['product_name'])->toBe('Bia Tiger')
        ->and($top[0]['quantity_sold'])->toBe(80) // 50 + 30, KHÔNG cộng 999 của 10 ngày trước
        ->and($top[0]['revenue_amount'])->toBe(2_000_000)
        ->and($top[1]['product_name'])->toBe('Nước suối');
});

it('cảnh báo chênh lệch két bất thường và tỉ lệ huỷ cao', function () {
    $homNay = Carbon::today();

    DailySummary::factory()->create([
        'date' => $homNay->toDateString(),
        'revenue_amount' => 1_000_000,
        'cancelled_item_amount' => 100_000, // 10% > ngưỡng 5%
        'cash_variance_amount' => -80_000, // vượt ngưỡng 50.000
    ]);
    DailySummary::factory()->create([
        'date' => $homNay->clone()->subDay()->toDateString(),
        'revenue_amount' => 1_000_000,
        'cancelled_item_amount' => 10_000, // 1%, dưới ngưỡng
        'cash_variance_amount' => 5_000, // dưới ngưỡng
    ]);

    $ketQua = app(GetOwnerDashboard::class)->handle();
    $canhBao = collect($ketQua['canh_bao']);

    expect($canhBao->where('date', $homNay->toDateString())->count())->toBe(2)
        ->and($canhBao->where('date', $homNay->clone()->subDay()->toDateString())->count())->toBe(0)
        ->and($canhBao->pluck('message')->implode(' | '))->toContain('thiếu')
        ->and($canhBao->pluck('message')->implode(' | '))->toContain('món huỷ chiếm');
});
