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
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Ordering\Actions\VoidTableSession;
use App\Domain\Ordering\DTO\VoidTableSessionData;
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
        uuid: (string) Str::uuid(),
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
        uuid: (string) Str::uuid(),
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

// ── HAI CON SỐ ĐỘ TIN CẬY (Bước 10) ──────────────────────────────────────
// cost_amount = 0 có thể là "món không tốn nguyên liệu", cũng có thể là
// "không ai biết nó tốn bao nhiêu". Hai cột dưới phân biệt hai chuyện đó.

it('bán 10 phần lúc tồn dương và 5 phần lúc tồn âm thì qty_no_cost đếm đúng 5', function () {
    $ngay = Carbon::parse('2026-08-14');
    $chuQuan = User::factory()->owner()->create();

    // Tồn đầu chỉ đủ cho 10 phần (mỗi phần ăn 1 đơn vị).
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
    $session = TableSession::factory()->withTable()->create(['shift_id' => $ca->id, 'opened_at' => $ngay->clone()->setTime(18, 10)]);

    $category = Category::factory()->create();
    $mon = Product::factory()->for($category)->create(['name' => 'Lẩu gà']);
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

    // 10 phần đầu trừ hết sạch tồn — vẫn biết giá vốn.
    ghiGiaVonChoDongMon($ga, $dongDu, 10, $chuQuan);
    // 5 phần sau bán lúc kho đã về 0 → has_cost = false, cost_delta = 0.
    ghiGiaVonChoDongMon($ga, $dongAm, 5, $chuQuan);

    app(SummarizeProductProfit::class)->handle($ngay->toDateString());

    $dong = ProductProfitDaily::query()->where('product_variant_id', $bienThe->id)->sole();

    expect($dong->quantity_sold)->toBe(15)
        ->and($dong->qty_no_cost)->toBe(5)
        ->and($dong->qty_not_served)->toBe(0)
        // Giá vốn chỉ ghi được phần biết thật, KHÔNG bịa cho 5 phần kia.
        ->and($dong->cost_amount)->toBe(100_000);
});

it('lượt khách đã đóng mà còn món chưa bấm xong thì qty_not_served đếm đúng', function () {
    $ngay = Carbon::parse('2026-08-15');
    $ca = Shift::factory()->closed()->create(['opened_at' => $ngay->clone()->setTime(18, 0)]);
    $session = TableSession::factory()->withTable()->closed()->create([
        'shift_id' => $ca->id,
        'opened_at' => $ngay->clone()->setTime(18, 10),
        'closed_at' => $ngay->clone()->setTime(21, 0),
    ]);

    $category = Category::factory()->create();
    $mon = Product::factory()->for($category)->create();
    $bienThe = ProductVariant::factory()->for($mon)->create(['price' => 20_000]);

    $order = Order::factory()->for($session, 'tableSession')->create(['sent_at' => $ngay->clone()->setTime(19, 0)]);

    OrderItem::factory()->for($order, 'order')->create([
        'product_id' => $mon->id, 'product_variant_id' => $bienThe->id,
        'unit_price' => 20_000, 'options_amount' => 0, 'quantity' => 4,
        'status' => OrderItemStatus::Served, 'served_at' => $ngay->clone()->setTime(19, 30),
    ]);
    OrderItem::factory()->for($order, 'order')->create([
        'product_id' => $mon->id, 'product_variant_id' => $bienThe->id,
        'unit_price' => 20_000, 'options_amount' => 0, 'quantity' => 3,
        'status' => OrderItemStatus::Ordered, 'served_at' => null,
    ]);

    app(SummarizeProductProfit::class)->handle($ngay->toDateString());

    $dong = ProductProfitDaily::query()->where('product_variant_id', $bienThe->id)->sole();

    expect($dong->quantity_sold)->toBe(7)
        ->and($dong->qty_not_served)->toBe(3)
        ->and($dong->qty_no_cost)->toBe(0);
});

it('bàn còn đang ăn dở, món chưa bưng ra thì KHÔNG tính là bếp quên bấm xong', function () {
    $ngay = Carbon::parse('2026-08-16');
    $ca = Shift::factory()->closed()->create(['opened_at' => $ngay->clone()->setTime(18, 0)]);
    $session = TableSession::factory()->withTable()->create(['shift_id' => $ca->id, 'opened_at' => $ngay->clone()->setTime(18, 10)]);

    $category = Category::factory()->create();
    $mon = Product::factory()->for($category)->create();
    $bienThe = ProductVariant::factory()->for($mon)->create(['price' => 20_000]);

    $order = Order::factory()->for($session, 'tableSession')->create(['sent_at' => $ngay->clone()->setTime(19, 0)]);
    OrderItem::factory()->for($order, 'order')->create([
        'product_id' => $mon->id, 'product_variant_id' => $bienThe->id,
        'unit_price' => 20_000, 'options_amount' => 0, 'quantity' => 3,
        'status' => OrderItemStatus::Ordered, 'served_at' => null,
    ]);

    app(SummarizeProductProfit::class)->handle($ngay->toDateString());

    expect(ProductProfitDaily::query()->where('product_variant_id', $bienThe->id)->sole()->qty_not_served)->toBe(0);
});

/**
 * Lượt khách bị huỷ CẢ LƯỢT — khách bỏ về không trả tiền (sửa 12/08, review
 * Phase 3 Bước 10).
 *
 * VoidTableSession cố ý KHÔNG đụng tới các dòng món bên trong (H1: huỷ từng
 * dòng phải ghi ai/lúc nào/vì sao). Trước đây báo cáo lãi gộp chỉ lọc theo
 * trạng thái dòng món và phiếu bếp, nên tiền của một bill chưa từng thu vẫn
 * được cộng vào doanh thu — trong khi báo cáo doanh thu ngày lấy từ phiếu thu
 * nên đếm 0 đồng. Hai màn hình cãi nhau.
 */
it('lượt khách bị huỷ cả lượt KHÔNG mang doanh thu, nhưng kho vẫn không hoàn lại', function () {
    $ngay = Carbon::parse('2026-08-10');
    $chuQuan = User::factory()->owner()->create();

    $ga = Ingredient::factory()->create();
    app(RecordStockMovement::class)->handle(new RecordStockMovementData(
        uuid: (string) Str::uuid(),
        ingredientId: $ga->id,
        type: StockMovementType::Purchase,
        qtyDelta: 1_000,
        knownCost: 1_000_000,
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
        'subtotal_amount' => 90_000,
        'discount_amount' => 0,
        'total_amount' => 90_000,
    ]);

    $category = Category::factory()->create();
    $mon = Product::factory()->for($category)->create();
    $bienThe = ProductVariant::factory()->for($mon)->create(['price' => 30_000]);

    $order = Order::factory()->for($session, 'tableSession')->create(['sent_at' => $ngay->clone()->setTime(18, 15)]);
    $item = OrderItem::factory()->for($order, 'order')->create([
        'product_id' => $mon->id, 'product_variant_id' => $bienThe->id,
        'unit_price' => 30_000, 'options_amount' => 0, 'quantity' => 3,
        'status' => OrderItemStatus::Served, 'served_at' => $ngay->clone()->setTime(18, 30),
    ]);

    // Món đã bưng ra, kho đã trừ thật.
    ghiGiaVonChoDongMon($ga, $item, 30, $chuQuan);

    // Khách bỏ về, chưa trả đồng nào → huỷ cả lượt.
    app(VoidTableSession::class)->handle(
        new VoidTableSessionData(
            tableSessionId: $session->id,
            reason: 'Khách bỏ về không trả tiền',
            voidedByUserId: $chuQuan->id,
        )
    );

    app(SummarizeProductProfit::class)->handle($ngay->toDateString());

    // Không dòng lãi gộp nào — doanh thu chưa từng có thì không được ghi.
    expect(ProductProfitDaily::query()->count())->toBe(0);

    // Nhưng gà thì đã nấu mất thật: sổ cái kho giữ nguyên, không hoàn lại (K6).
    expect(StockMovement::query()
        ->where('ref_type', StockMovementRefType::OrderItem)
        ->where('ref_id', $item->id)
        ->count())->toBe(1);
});
