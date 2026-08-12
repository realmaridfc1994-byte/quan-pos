<?php

declare(strict_types=1);

/**
 * Phase 3 Bước 10 — ngày CHƯA được tổng hợp lại phải NÓI RÕ là chưa kiểm,
 * không được hiện số sạch.
 *
 * Hai cột đếm phần thiếu giá vốn chỉ có số từ lần tổng hợp sau khi chúng ra
 * đời. Dòng chốt từ trước mang số 0 — nếu màn hình im lặng thì chủ quán nhìn
 * tháng cũ "sạch" rồi kết luận nhầm "tháng trước không có vấn đề gì".
 */

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Reporting\Models\ProductProfitDaily;
use App\Domain\Reporting\Queries\GetOwnerProfitDashboard;
use App\Domain\Staffing\Models\User;
use App\Filament\Widgets\LaiGopTheoMonWidget;
use App\Support\CauHinhQuan;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    // Chốt "hôm nay" là 11/08/2026 để đầu tháng đang xem (01/08) nằm trước mốc.
    Carbon::setTestNow(Carbon::parse('2026-08-11 10:00:00'));

    $this->actingAs(User::factory()->owner()->create());

    // Một món hoàn toàn SẠCH: không phần nào thiếu giá vốn, không phần nào bếp
    // quên bấm xong. Đúng cái vẻ ngoài mà dữ liệu cũ chưa kiểm cũng có.
    $mon = Product::factory()->create(['name' => 'Lẩu gà']);
    $bienThe = ProductVariant::factory()->for($mon)->create(['name' => 'Phần', 'price' => 200_000]);

    ProductProfitDaily::factory()->create([
        'date' => '2026-08-05',
        'product_id' => $mon->id,
        'product_variant_id' => $bienThe->id,
        'quantity_sold' => 120,
        'revenue_amount' => 24_000_000,
        'cost_amount' => 7_680_000,
        'qty_no_cost' => 0,
        'qty_not_served' => 0,
    ]);
});

afterEach(function () {
    Carbon::setTestNow();
});

it('xem dữ liệu trước mốc thì màn hình lãi gộp nói rõ là chưa kiểm phần thiếu giá vốn', function () {
    config(['pos.moc_ngay_kiem_thieu_gia_von' => '2026-08-10']);

    expect(app(GetOwnerProfitDashboard::class)->handle()['canh_bao_moc_ngay'])
        ->toBe('Số liệu trước ngày 10/08/2026 chưa được kiểm phần thiếu giá vốn — con số lãi có thể cao hơn thực tế.');

    Livewire::test(LaiGopTheoMonWidget::class)
        ->assertSuccessful()
        ->assertSee('chưa được kiểm phần thiếu giá vốn')
        ->assertSee('10/08/2026');
});

it('xem dữ liệu sau mốc mà không có món nào thiếu giá vốn thì KHÔNG hiện cảnh báo nào', function () {
    config(['pos.moc_ngay_kiem_thieu_gia_von' => '2026-06-01']);

    expect(app(GetOwnerProfitDashboard::class)->handle()['canh_bao_moc_ngay'])->toBeNull();

    Livewire::test(LaiGopTheoMonWidget::class)
        ->assertSuccessful()
        ->assertDontSee('chưa được kiểm phần thiếu giá vốn')
        ->assertDontSee('⚠️');
});

it('tổng hợp lại xong khoảng ngày cũ thì cảnh báo tự tắt', function () {
    config(['pos.moc_ngay_kiem_thieu_gia_von' => '2026-08-10']);

    expect(app(GetOwnerProfitDashboard::class)->handle()['canh_bao_moc_ngay'])->not->toBeNull();

    app(CauHinhQuan::class)->haMocNgayKiemThieuGiaVon(Carbon::parse('2026-08-01'));

    expect(app(GetOwnerProfitDashboard::class)->handle()['canh_bao_moc_ngay'])->toBeNull();

    Livewire::test(LaiGopTheoMonWidget::class)
        ->assertSuccessful()
        ->assertDontSee('chưa được kiểm phần thiếu giá vốn');
});

it('mốc chỉ hạ xuống, không bao giờ nâng lên', function () {
    config(['pos.moc_ngay_kiem_thieu_gia_von' => '2026-08-10']);
    $cauHinh = app(CauHinhQuan::class);

    expect($cauHinh->haMocNgayKiemThieuGiaVon(Carbon::parse('2026-06-01')))->toBeTrue()
        ->and($cauHinh->mocNgayKiemThieuGiaVon()->toDateString())->toBe('2026-06-01');

    expect($cauHinh->haMocNgayKiemThieuGiaVon(Carbon::parse('2026-07-01')))->toBeFalse()
        ->and($cauHinh->mocNgayKiemThieuGiaVon()->toDateString())->toBe('2026-06-01');
});
