<?php

declare(strict_types=1);

/**
 * Phase 3 Bước 10 — `report:summarize` phải tổng hợp ĐỦ BA việc (báo cáo ngày,
 * lãi gộp theo món, hao hụt tháng) và chạy lại được cho một khoảng ngày cũ mà
 * không nhân đôi dữ liệu, không đổi doanh thu đã chốt.
 */

use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Inventory\Actions\RecordStockMovement;
use App\Domain\Inventory\DTO\RecordStockMovementData;
use App\Domain\Inventory\Enums\StockMovementRefType;
use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Inventory\Models\Ingredient;
use App\Domain\Ordering\Enums\OrderItemStatus;
use App\Domain\Ordering\Models\Order;
use App\Domain\Ordering\Models\OrderItem;
use App\Domain\Ordering\Models\TableSession;
use App\Domain\Reporting\Models\DailySummary;
use App\Domain\Reporting\Models\IngredientWasteMonthly;
use App\Domain\Reporting\Models\ProductProfitDaily;
use App\Domain\Staffing\Models\Shift;
use App\Domain\Staffing\Models\User;
use App\Support\CauHinhQuan;
use Illuminate\Support\Carbon;

/**
 * Dựng một ngày bán hàng cũ: tồn chỉ đủ 10 phần, bán 15 phần — 5 phần cuối
 * bán lúc kho đã về 0, tức KHÔNG xác định được giá vốn.
 */
function ngayCuBanLucTonAm(string $ngayChuoi): ProductVariant
{
    $ngay = Carbon::parse($ngayChuoi);
    $chuQuan = User::factory()->owner()->create();

    $ga = Ingredient::factory()->create();
    app(RecordStockMovement::class)->handle(new RecordStockMovementData(
        uuid: (string) Str::uuid(),
        ingredientId: $ga->id,
        type: StockMovementType::Purchase,
        qtyDelta: 10,
        knownCost: 100_000, // 10.000đ / đơn vị
        refType: StockMovementRefType::Manual,
        refId: null,
        reason: null,
        approvedByUserId: null,
        createdByUserId: $chuQuan->id,
        shiftId: null,
    ));

    $ca = Shift::factory()->closed()->create(['opened_at' => $ngay->clone()->setTime(18, 0)]);
    $session = TableSession::factory()->withTable()->create([
        'shift_id' => $ca->id,
        'opened_at' => $ngay->clone()->setTime(18, 10),
    ]);

    $mon = Product::factory()->for(Category::factory()->create())->create(['name' => 'Lẩu gà']);
    $bienThe = ProductVariant::factory()->for($mon)->create(['price' => 50_000]);

    $order = Order::factory()->for($session, 'tableSession')->create(['sent_at' => $ngay->clone()->setTime(19, 0)]);

    $dongDu = OrderItem::factory()->for($order, 'order')->create([
        'product_id' => $mon->id, 'product_variant_id' => $bienThe->id,
        'unit_price' => 50_000, 'options_amount' => 0, 'quantity' => 10,
        'status' => OrderItemStatus::Served, 'served_at' => $ngay->clone()->setTime(19, 10),
    ]);
    $dongAm = OrderItem::factory()->for($order, 'order')->create([
        'product_id' => $mon->id, 'product_variant_id' => $bienThe->id,
        'unit_price' => 50_000, 'options_amount' => 0, 'quantity' => 5,
        'status' => OrderItemStatus::Served, 'served_at' => $ngay->clone()->setTime(19, 20),
    ]);

    foreach ([[$dongDu, 10], [$dongAm, 5]] as [$dong, $soLuong]) {
        app(RecordStockMovement::class)->handle(new RecordStockMovementData(
            uuid: (string) Str::uuid(),
            ingredientId: $ga->id,
            type: StockMovementType::Sale,
            qtyDelta: -$soLuong,
            knownCost: null,
            refType: StockMovementRefType::OrderItem,
            refId: $dong->id,
            reason: null,
            approvedByUserId: null,
            createdByUserId: $chuQuan->id,
            shiftId: null,
        ));
    }

    return $bienThe;
}

it('chạy lại một ngày cũ có món bán lúc kho âm thì đếm đúng số phần không biết giá vốn', function () {
    $bienThe = ngayCuBanLucTonAm('2026-06-15');

    // Chưa chạy lần nào thì bảng lãi gộp trống trơn — đúng cái vấn đề đang chữa.
    expect(ProductProfitDaily::query()->count())->toBe(0);

    $this->artisan('report:summarize', ['--tu' => '2026-06-15', '--den' => '2026-06-15'])
        ->assertSuccessful();

    $dong = ProductProfitDaily::query()->where('product_variant_id', $bienThe->id)->sole();

    expect($dong->quantity_sold)->toBe(15)
        ->and($dong->qty_no_cost)->toBe(5)
        ->and($dong->cost_amount)->toBe(100_000);
});

it('chạy lại HAI LẦN cùng một ngày thì không nhân đôi, mọi con số không đổi', function () {
    ngayCuBanLucTonAm('2026-06-16');

    $doc = fn (): array => ProductProfitDaily::query()
        ->where('date', '2026-06-16')
        ->orderBy('product_variant_id')
        ->get(['product_variant_id', 'quantity_sold', 'revenue_amount', 'cost_amount', 'qty_no_cost', 'qty_not_served'])
        ->map(fn ($d): array => $d->only([
            'product_variant_id', 'quantity_sold', 'revenue_amount', 'cost_amount', 'qty_no_cost', 'qty_not_served',
        ]))
        ->all();

    $this->artisan('report:summarize', ['--tu' => '2026-06-16', '--den' => '2026-06-16'])->assertSuccessful();
    $lanMot = $doc();

    $this->artisan('report:summarize', ['--tu' => '2026-06-16', '--den' => '2026-06-16'])->assertSuccessful();
    $lanHai = $doc();

    expect($lanHai)->toBe($lanMot)
        ->and(ProductProfitDaily::query()->where('date', '2026-06-16')->count())->toBe(1)
        ->and(DailySummary::query()->where('date', '2026-06-16')->count())->toBe(1);
});

it('chạy cho một ngày cũng tổng hợp luôn lãi gộp và hao hụt tháng, không chỉ báo cáo ngày', function () {
    ngayCuBanLucTonAm('2026-06-17');

    $chuQuan = User::factory()->owner()->create();
    $nguyenLieu = Ingredient::factory()->create();
    app(RecordStockMovement::class)->handle(new RecordStockMovementData(
        uuid: (string) Str::uuid(),
        ingredientId: $nguyenLieu->id,
        type: StockMovementType::Purchase,
        qtyDelta: 20,
        knownCost: 200_000,
        refType: StockMovementRefType::Manual,
        refId: null,
        reason: null,
        approvedByUserId: null,
        createdByUserId: $chuQuan->id,
        shiftId: null,
    ));
    // Hao hụt phải rơi đúng vào tháng 6 (occurred_at do RecordStockMovement
    // lấy theo thời điểm ghi), nên chỉnh đồng hồ về đúng hôm đó.
    Carbon::setTestNow(Carbon::parse('2026-06-17 20:00:00'));
    app(RecordStockMovement::class)->handle(new RecordStockMovementData(
        uuid: (string) Str::uuid(),
        ingredientId: $nguyenLieu->id,
        type: StockMovementType::Waste,
        qtyDelta: -5,
        knownCost: null,
        refType: StockMovementRefType::Manual,
        refId: null,
        reason: '[Vỡ/hỏng] 5 lon bia vỡ',
        approvedByUserId: null,
        createdByUserId: $chuQuan->id,
        shiftId: null,
    ));
    Carbon::setTestNow();

    $this->artisan('report:summarize', ['date' => '2026-06-17'])->assertSuccessful();

    expect(DailySummary::query()->where('date', '2026-06-17')->exists())->toBeTrue()
        ->and(ProductProfitDaily::query()->where('date', '2026-06-17')->exists())->toBeTrue()
        ->and(IngredientWasteMonthly::query()->where('month', '2026-06-01')->where('ingredient_id', $nguyenLieu->id)->exists())->toBeTrue();
});

it('doanh thu đã chốt của một ngày cũ bị đổi thì lệnh DỪNG và không ghi đè', function () {
    ngayCuBanLucTonAm('2026-06-18');

    // Số tóm tắt cũ khác số tính lại (dựng tình huống "đã chốt rồi mới lệch").
    DailySummary::factory()->create(['date' => '2026-06-18', 'revenue_amount' => 999_000]);

    $this->artisan('report:summarize', ['--tu' => '2026-06-18', '--den' => '2026-06-18'])
        ->assertFailed();

    expect((int) DailySummary::query()->where('date', '2026-06-18')->value('revenue_amount'))->toBe(999_000)
        ->and(ProductProfitDaily::query()->where('date', '2026-06-18')->count())->toBe(0);

    // Đọc con số rồi đồng ý thì mới ghi đè.
    $this->artisan('report:summarize', ['--tu' => '2026-06-18', '--den' => '2026-06-18', '--dong-y-doanh-thu-doi' => true])
        ->assertSuccessful();

    expect((int) DailySummary::query()->where('date', '2026-06-18')->value('revenue_amount'))->not->toBe(999_000)
        ->and(ProductProfitDaily::query()->where('date', '2026-06-18')->count())->toBe(1);
});

it('chạy lại liền mạch tới sát mốc thì hạ mốc xuống, còn hở thì giữ nguyên mốc cũ', function () {
    config(['pos.moc_ngay_kiem_thieu_gia_von' => '2026-08-10']);
    $cauHinh = app(CauHinhQuan::class);

    // Còn hở (chạy tháng 6, mốc ở 10/08) → không được tuyên bố sạch cho quãng giữa.
    $this->artisan('report:summarize', ['--tu' => '2026-06-01', '--den' => '2026-06-03'])->assertSuccessful();
    expect($cauHinh->mocNgayKiemThieuGiaVon()->toDateString())->toBe('2026-08-10');

    // Dính liền vùng đã kiểm (chạy tới 09/08, sát mốc) → hạ mốc.
    $this->artisan('report:summarize', ['--tu' => '2026-08-01', '--den' => '2026-08-09'])->assertSuccessful();
    expect($cauHinh->mocNgayKiemThieuGiaVon()->toDateString())->toBe('2026-08-01');

    // Chạy lại một khoảng nằm trong vùng đã kiểm KHÔNG được nâng mốc lên.
    $this->artisan('report:summarize', ['--tu' => '2026-08-05', '--den' => '2026-08-06'])->assertSuccessful();
    expect($cauHinh->mocNgayKiemThieuGiaVon()->toDateString())->toBe('2026-08-01');
});

it('ghi cả một ngày và --tu/--den cùng lúc thì lệnh từ chối chạy', function () {
    $this->artisan('report:summarize', ['date' => '2026-06-01', '--den' => '2026-06-05'])
        ->assertFailed();
});
