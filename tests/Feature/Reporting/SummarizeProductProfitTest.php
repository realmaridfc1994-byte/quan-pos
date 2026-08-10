<?php

declare(strict_types=1);

/**
 * Phase 3 Bước 8 — SummarizeProductProfit phải tổng hợp khớp ĐÚNG BẰNG tổng
 * tính trực tiếp, và đặc biệt: một lượt khách có giảm giá thì lãi gộp từng
 * dòng cộng lại phải bằng đúng doanh thu thật (đã trừ giảm giá) trừ tổng giá
 * vốn — không lệch một đồng nào vì làm tròn phân bổ giảm giá.
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
use App\Domain\Reporting\Actions\SummarizeProductProfit;
use App\Domain\Reporting\Models\ProductProfitDaily;
use App\Domain\Staffing\Models\Shift;
use App\Domain\Staffing\Models\User;
use Illuminate\Support\Carbon;

function ghiGiaVonChoDongMon(Ingredient $ga, OrderItem $item, int $soLuongTru, User $user): void
{
    app(RecordStockMovement::class)->handle(new RecordStockMovementData(
        ingredientId: $ga->id,
        type: StockMovementType::Sale,
        qtyDelta: -$soLuongTru,
        knownCost: null,
        refType: StockMovementRefType::OrderItem,
        refId: $item->id,
        reason: null,
        approvedByUserId: null,
        createdByUserId: $user->id,
        shiftId: null,
    ));
}

it('một lượt khách có giảm giá: lãi gộp từng dòng cộng lại bằng đúng doanh thu thật trừ tổng giá vốn', function () {
    $ngay = Carbon::parse('2026-08-10');
    $chuQuan = User::factory()->owner()->create();

    $ga = Ingredient::factory()->create();
    app(RecordStockMovement::class)->handle(new RecordStockMovementData(
        ingredientId: $ga->id,
        type: StockMovementType::Purchase,
        qtyDelta: 1_000,
        knownCost: 1_000_000, // 1.000 đ / đơn vị
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
        'subtotal_amount' => 100_000,
        'discount_amount' => 10_000,
        'discount_reason' => 'Khách quen',
        'total_amount' => 90_000,
    ]);

    $category = Category::factory()->create();
    $monA = Product::factory()->for($category)->create();
    $bienTheA = ProductVariant::factory()->for($monA)->create(['price' => 30_000]);
    $monB = Product::factory()->for($category)->create();
    $bienTheB = ProductVariant::factory()->for($monB)->create(['price' => 10_000]);

    $order = Order::factory()->for($session, 'tableSession')->create(['sent_at' => $ngay->clone()->setTime(18, 15)]);

    $itemA = OrderItem::factory()->for($order, 'order')->create([
        'product_id' => $monA->id, 'product_variant_id' => $bienTheA->id,
        'unit_price' => 30_000, 'options_amount' => 0, 'quantity' => 3, 'status' => OrderItemStatus::Served,
    ]); // line_amount = 90.000

    $itemB = OrderItem::factory()->for($order, 'order')->create([
        'product_id' => $monB->id, 'product_variant_id' => $bienTheB->id,
        'unit_price' => 10_000, 'options_amount' => 0, 'quantity' => 1, 'status' => OrderItemStatus::Served,
    ]); // line_amount = 10.000

    // Giá vốn thật tại thời điểm bán (giống DeductStockForServedItem đã ghi).
    ghiGiaVonChoDongMon($ga, $itemA, 30, $chuQuan); // 30 x 1.000đ = 30.000đ
    ghiGiaVonChoDongMon($ga, $itemB, 5, $chuQuan); //  5 x 1.000đ = 5.000đ

    app(SummarizeProductProfit::class)->handle($ngay->toDateString());

    $dongA = ProductProfitDaily::query()->where('product_variant_id', $bienTheA->id)->sole();
    $dongB = ProductProfitDaily::query()->where('product_variant_id', $bienTheB->id)->sole();

    // subtotal=100.000, discount=10.000 → dòng A (90.000/100.000) chịu 9.000,
    // dòng B (cuối cùng, theo thứ tự id) nhận đúng phần dư 1.000.
    expect($dongA->revenue_amount)->toBe(81_000)
        ->and($dongA->cost_amount)->toBe(30_000)
        ->and($dongA->profit_amount)->toBe(51_000);

    expect($dongB->revenue_amount)->toBe(9_000)
        ->and($dongB->cost_amount)->toBe(5_000)
        ->and($dongB->profit_amount)->toBe(4_000);

    // ĐẶC BIỆT: tổng lãi gộp phải bằng đúng doanh thu thật (= total_amount đã
    // thu, sau giảm giá) trừ tổng giá vốn — không lệch một đồng vì làm tròn.
    $tongDoanhThuDaGiam = $dongA->revenue_amount + $dongB->revenue_amount;
    $tongGiaVon = $dongA->cost_amount + $dongB->cost_amount;
    $tongLaiGop = $dongA->profit_amount + $dongB->profit_amount;

    expect($tongDoanhThuDaGiam)->toBe($session->refresh()->total_amount)
        ->and($tongLaiGop)->toBe($tongDoanhThuDaGiam - $tongGiaVon)
        ->and($tongLaiGop)->toBe(55_000);
});

it('món không trừ kho (không có recipe) thì giá vốn bằng 0, lãi gộp bằng doanh thu', function () {
    $ngay = Carbon::parse('2026-08-11');
    $ca = Shift::factory()->closed()->create(['opened_at' => $ngay->clone()->setTime(18, 0)]);
    $session = TableSession::factory()->withTable()->create([
        'shift_id' => $ca->id,
        'opened_at' => $ngay->clone()->setTime(18, 10),
    ]);

    $category = Category::factory()->create();
    $mon = Product::factory()->for($category)->create();
    $bienThe = ProductVariant::factory()->for($mon)->create(['price' => 20_000]);

    $order = Order::factory()->for($session, 'tableSession')->create(['sent_at' => $ngay->clone()->setTime(19, 0)]);
    OrderItem::factory()->for($order, 'order')->create([
        'product_id' => $mon->id, 'product_variant_id' => $bienThe->id,
        'unit_price' => 20_000, 'options_amount' => 0, 'quantity' => 2, 'status' => OrderItemStatus::Served,
    ]);

    app(SummarizeProductProfit::class)->handle($ngay->toDateString());

    $dong = ProductProfitDaily::query()->where('product_variant_id', $bienThe->id)->sole();
    expect($dong->revenue_amount)->toBe(40_000)
        ->and($dong->cost_amount)->toBe(0)
        ->and($dong->profit_amount)->toBe(40_000);
});

it('món đã huỷ không được tính vào lãi gộp theo ngày', function () {
    $ngay = Carbon::parse('2026-08-12');
    $ca = Shift::factory()->closed()->create(['opened_at' => $ngay->clone()->setTime(18, 0)]);
    $session = TableSession::factory()->withTable()->create(['shift_id' => $ca->id, 'opened_at' => $ngay->clone()->setTime(18, 10)]);

    $category = Category::factory()->create();
    $mon = Product::factory()->for($category)->create();
    $bienThe = ProductVariant::factory()->for($mon)->create(['price' => 20_000]);

    $order = Order::factory()->for($session, 'tableSession')->create(['sent_at' => $ngay->clone()->setTime(19, 0)]);
    OrderItem::factory()->for($order, 'order')->create([
        'product_id' => $mon->id, 'product_variant_id' => $bienThe->id,
        'unit_price' => 20_000, 'options_amount' => 0, 'quantity' => 2, 'status' => OrderItemStatus::Cancelled,
        'cancelled_at' => $ngay->clone()->setTime(19, 5), 'cancel_reason' => 'Khách đổi ý',
        'cancelled_by_user_id' => User::factory(),
    ]);

    app(SummarizeProductProfit::class)->handle($ngay->toDateString());

    expect(ProductProfitDaily::query()->where('date', $ngay->toDateString())->count())->toBe(0);
});

it('chạy lại cho cùng một ngày thì ghi đè, không cộng dồn', function () {
    $ngay = Carbon::parse('2026-08-13');
    $ca = Shift::factory()->closed()->create(['opened_at' => $ngay->clone()->setTime(18, 0)]);
    $session = TableSession::factory()->withTable()->create(['shift_id' => $ca->id, 'opened_at' => $ngay->clone()->setTime(18, 10)]);

    $category = Category::factory()->create();
    $mon = Product::factory()->for($category)->create();
    $bienThe = ProductVariant::factory()->for($mon)->create(['price' => 20_000]);

    $order = Order::factory()->for($session, 'tableSession')->create(['sent_at' => $ngay->clone()->setTime(19, 0)]);
    OrderItem::factory()->for($order, 'order')->create([
        'product_id' => $mon->id, 'product_variant_id' => $bienThe->id,
        'unit_price' => 20_000, 'options_amount' => 0, 'quantity' => 1, 'status' => OrderItemStatus::Served,
    ]);

    app(SummarizeProductProfit::class)->handle($ngay->toDateString());
    app(SummarizeProductProfit::class)->handle($ngay->toDateString());

    $dong = ProductProfitDaily::query()->where('date', $ngay->toDateString())->where('product_variant_id', $bienThe->id)->sole();
    expect($dong->revenue_amount)->toBe(20_000);
    expect(ProductProfitDaily::query()->where('date', $ngay->toDateString())->count())->toBe(1);
});
