<?php

declare(strict_types=1);

/**
 * Phase 3 Bước 10 — màn hình chủ quán phải NÓI RA khi con số lãi gộp đang cao
 * hơn thực tế, ngay cạnh con số đó, không phải ở cuối trang.
 */

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Reporting\Models\ProductProfitDaily;
use App\Domain\Reporting\Queries\GetOwnerProfitDashboard;
use App\Domain\Staffing\Models\User;
use App\Filament\Widgets\CanhBaoThieuGiaVonWidget;
use App\Filament\Widgets\LaiGopTheoMonWidget;
use Livewire\Livewire;

function ghiLaiGopNgay(string $ten, array $so): ProductVariant
{
    $mon = Product::factory()->create(['name' => $ten]);
    $bienThe = ProductVariant::factory()->for($mon)->create(['name' => 'Phần', 'price' => 200_000]);

    ProductProfitDaily::factory()->create([
        'date' => now()->startOfMonth()->toDateString(),
        'product_id' => $mon->id,
        'product_variant_id' => $bienThe->id,
        ...$so,
    ]);

    return $bienThe;
}

beforeEach(function () {
    $this->chuQuan = User::factory()->owner()->create();
    $this->actingAs($this->chuQuan);

    // File này chỉ nói về cảnh báo THEO TỪNG MÓN. Đặt mốc "đã kiểm thiếu giá
    // vốn" về đầu tháng đang xem để câu cảnh báo theo MỐC NGÀY (kiểm ở
    // MocNgayTongHopTest) không lẫn vào các khẳng định dưới đây.
    config(['pos.moc_ngay_kiem_thieu_gia_von' => now()->startOfMonth()->toDateString()]);
});

it('món bán lúc kho âm và món bếp quên bấm xong đều bị đánh dấu cảnh báo', function () {
    ghiLaiGopNgay('Lẩu gà', [
        'quantity_sold' => 120,
        'revenue_amount' => 24_000_000,
        'cost_amount' => 7_680_000,
        'qty_no_cost' => 15,
        'qty_not_served' => 7,
    ]);

    $duLieu = app(GetOwnerProfitDashboard::class)->handle();
    $dong = $duLieu['lai_gop_theo_tong'][0];

    expect($dong['thieu_gia_von'])->toBeTrue()
        ->and($dong['qty_no_cost'])->toBe(15)
        ->and($dong['qty_not_served'])->toBe(7)
        ->and($dong['canh_bao'])->toBe(
            'Trong 120 phần Lẩu gà tháng này, 15 phần bán lúc kho đang âm nên chưa tính được giá vốn, '.
            'và 7 phần bếp chưa bấm xong. Con số lãi 68.0% chỉ tính trên phần đã biết giá vốn, '.
            'lãi thật của cả kỳ THẤP HƠN.'
        );

    expect($duLieu['thieu_gia_von']['so_mon'])->toBe(1)
        ->and($duLieu['thieu_gia_von']['cau'])
        ->toBe('Tháng này có 1 món bị thiếu giá vốn — lãi gộp dưới đây cao hơn thực tế.');
});

it('câu cảnh báo chỉ nêu đúng vế có thật — chỉ thiếu giá vốn, không có món chưa bấm xong', function () {
    ghiLaiGopNgay('Gà nướng', [
        'quantity_sold' => 40,
        'revenue_amount' => 8_000_000,
        'cost_amount' => 4_000_000,
        'qty_no_cost' => 3,
        'qty_not_served' => 0,
    ]);

    $dong = app(GetOwnerProfitDashboard::class)->handle()['lai_gop_theo_tong'][0];

    expect($dong['canh_bao'])->toContain('3 phần bán lúc kho đang âm')
        ->and($dong['canh_bao'])->not->toContain('bếp chưa bấm xong');
});

it('không món nào thiếu giá vốn thì KHÔNG hiện cảnh báo nào, widget đầu trang tự ẩn', function () {
    ghiLaiGopNgay('Bia Tiger', [
        'quantity_sold' => 200,
        'revenue_amount' => 5_000_000,
        'cost_amount' => 3_000_000,
        'qty_no_cost' => 0,
        'qty_not_served' => 0,
    ]);

    $duLieu = app(GetOwnerProfitDashboard::class)->handle();

    expect($duLieu['lai_gop_theo_tong'][0]['thieu_gia_von'])->toBeFalse()
        ->and($duLieu['lai_gop_theo_tong'][0]['canh_bao'])->toBeNull()
        ->and($duLieu['thieu_gia_von']['so_mon'])->toBe(0)
        ->and($duLieu['thieu_gia_von']['cau'])->toBeNull();

    expect(CanhBaoThieuGiaVonWidget::canView())->toBeFalse();

    Livewire::test(LaiGopTheoMonWidget::class)
        ->assertSuccessful()
        ->assertDontSee('⚠️')
        ->assertDontSee('CAO HƠN thực tế');
});

it('bảng lãi gộp hiện dấu cảnh báo kèm câu giải thích cho đúng món bị thiếu', function () {
    ghiLaiGopNgay('Lẩu gà', [
        'quantity_sold' => 120, 'revenue_amount' => 24_000_000, 'cost_amount' => 7_680_000,
        'qty_no_cost' => 15, 'qty_not_served' => 7,
    ]);
    ghiLaiGopNgay('Bia Tiger', [
        'quantity_sold' => 200, 'revenue_amount' => 5_000_000, 'cost_amount' => 3_000_000,
        'qty_no_cost' => 0, 'qty_not_served' => 0,
    ]);

    Livewire::test(LaiGopTheoMonWidget::class)
        ->assertSuccessful()
        ->assertSee('⚠️')
        ->assertSee('15 phần bán lúc kho đang âm nên chưa tính được giá vốn')
        ->assertSee('7 phần bếp chưa bấm xong');

    expect(CanhBaoThieuGiaVonWidget::canView())->toBeTrue();

    Livewire::test(CanhBaoThieuGiaVonWidget::class)
        ->assertSuccessful()
        ->assertSee('Tháng này có 1 món bị thiếu giá vốn')
        ->assertSee('lãi gộp dưới đây cao hơn thực tế');
});

it('thu ngân không xem được widget cảnh báo — cùng quyền với bảng lãi gộp', function () {
    ghiLaiGopNgay('Lẩu gà', [
        'quantity_sold' => 10, 'revenue_amount' => 1_000_000, 'cost_amount' => 0,
        'qty_no_cost' => 10, 'qty_not_served' => 0,
    ]);

    $this->actingAs(User::factory()->cashier()->create());

    expect(CanhBaoThieuGiaVonWidget::canView())->toBeFalse();
});
