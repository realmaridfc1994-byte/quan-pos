<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Inventory\Actions\ReconcileStockLedger;
use App\Domain\Inventory\Actions\RecordStockMovement;
use App\Domain\Inventory\DTO\RecordStockMovementData;
use App\Domain\Inventory\Enums\StockMovementRefType;
use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Inventory\Models\Ingredient;
use App\Domain\Inventory\Models\Recipe;
use App\Domain\Ordering\Enums\OrderItemStatus;
use App\Domain\Ordering\Models\Order;
use App\Domain\Ordering\Models\OrderItem;
use App\Domain\Ordering\Models\TableSession;
use App\Domain\Staffing\Actions\CloseShift;
use App\Domain\Staffing\DTO\CloseShiftData;
use App\Domain\Staffing\Enums\ShiftStatus;
use App\Domain\Staffing\Models\Shift;
use App\Domain\Staffing\Models\User;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->chuQuan = User::factory()->owner()->create();
    $this->action = new ReconcileStockLedger;
});

function nhapTonDauDoiSoat(Ingredient $ingredient, int $qty, int $cost, User $user): void
{
    app(RecordStockMovement::class)->handle(new RecordStockMovementData(
        uuid: (string) Str::uuid(),
        ingredientId: $ingredient->id,
        type: StockMovementType::Purchase,
        qtyDelta: $qty,
        knownCost: $cost,
        refType: StockMovementRefType::Manual,
        refId: null,
        reason: null,
        approvedByUserId: null,
        createdByUserId: $user->id,
        shiftId: null,
    ));
}

it('cố tình sửa tay bảng tồn cho lệch số lượng → phát hiện đúng nguyên liệu và đúng số lệch', function () {
    $ga = Ingredient::factory()->create();
    nhapTonDauDoiSoat($ga, 100, 500_000, $this->chuQuan);

    // Cố tình sửa tay trực tiếp bằng SQL — giả lập ai đó chỉnh sai ngoài luồng.
    DB::table('stock_balances')->where('ingredient_id', $ga->id)->update(['qty' => 93]);

    $ketQua = $this->action->handle();

    expect($ketQua->sach())->toBeFalse();
    $dong = collect($ketQua->lechQty)->firstWhere('ingredient_id', $ga->id);
    expect($dong['so_cai'])->toBe(100)
        ->and($dong['ton_kho'])->toBe(93)
        ->and($dong['lech'])->toBe(-7)
        ->and($dong['ingredient_name'])->toBe($ga->name);
});

it('không lệch gì thì báo sạch', function () {
    $ga = Ingredient::factory()->create();
    nhapTonDauDoiSoat($ga, 100, 500_000, $this->chuQuan);

    $ketQua = $this->action->handle();

    expect($ketQua->sach())->toBeTrue()
        ->and($ketQua->lechQty)->toBe([])
        ->and($ketQua->lechCost)->toBe([])
        ->and($ketQua->thieuSoCai)->toBe([])
        ->and($ketQua->soCaiMoCoi)->toBe([]);
});

it('cố tình sửa tay giá trị tồn cho lệch → phát hiện đúng nguyên liệu và đúng số tiền lệch', function () {
    $ga = Ingredient::factory()->create();
    nhapTonDauDoiSoat($ga, 100, 500_000, $this->chuQuan);

    DB::table('stock_balances')->where('ingredient_id', $ga->id)->update(['total_cost' => 480_000]);

    $ketQua = $this->action->handle();

    $dong = collect($ketQua->lechCost)->firstWhere('ingredient_id', $ga->id);
    expect($dong['so_cai'])->toBe(500_000)
        ->and($dong['ton_kho'])->toBe(480_000)
        ->and($dong['lech'])->toBe(-20_000);
});

it('dòng món đã phục vụ nhưng thiếu sổ cái thì bị phát hiện', function () {
    $ga = Ingredient::factory()->create();
    nhapTonDauDoiSoat($ga, 100, 500_000, $this->chuQuan);

    $category = Category::factory()->create();
    $product = Product::factory()->for($category)->create();
    $variant = ProductVariant::factory()->for($product)->create(['deducts_stock' => true]);
    Recipe::factory()->for($variant, 'productVariant')->for($ga, 'ingredient')->create(['qty_base' => 10]);

    $order = Order::factory()->create();
    $item = OrderItem::factory()->for($order, 'order')->create([
        'product_id' => $product->id,
        'product_variant_id' => $variant->id,
        'status' => OrderItemStatus::Served,
        'served_at' => now(),
        'split_from_item_id' => null,
    ]);
    // KHÔNG gọi DeductStockForServedItem — giả lập lỗi thật (deduct thất bại
    // âm thầm hoặc bị bỏ sót).

    $ketQua = $this->action->handle();

    $dong = collect($ketQua->thieuSoCai)->firstWhere('order_item_id', $item->id);
    expect($dong)->not->toBeNull();
});

it('dòng tách ra khi huỷ một phần (split_from_item_id) không bị báo lệch giả dù không có sổ cái riêng', function () {
    $ga = Ingredient::factory()->create();
    nhapTonDauDoiSoat($ga, 100, 500_000, $this->chuQuan);

    $category = Category::factory()->create();
    $product = Product::factory()->for($category)->create();
    $variant = ProductVariant::factory()->for($product)->create(['deducts_stock' => true]);
    Recipe::factory()->for($variant, 'productVariant')->for($ga, 'ingredient')->create(['qty_base' => 10]);

    $order = Order::factory()->create();
    $dongGoc = OrderItem::factory()->for($order, 'order')->create([
        'product_id' => $product->id, 'product_variant_id' => $variant->id,
        'status' => OrderItemStatus::Served, 'served_at' => now(),
    ]);
    $dongTach = OrderItem::factory()->for($order, 'order')->create([
        'product_id' => $product->id, 'product_variant_id' => $variant->id,
        'status' => OrderItemStatus::Cancelled, 'served_at' => $dongGoc->served_at,
        'split_from_item_id' => $dongGoc->id,
        'cancelled_by_user_id' => $this->chuQuan->id, 'cancelled_at' => now(), 'cancel_reason' => 'Test',
    ]);

    $ketQua = $this->action->handle();

    // Dòng gốc CÓ thiếu sổ cái thật (không giả lập deduct ở đây) — nhưng dòng
    // tách thì KHÔNG được liệt kê riêng, vì nó không bao giờ có sổ cái của
    // chính nó (K7) — đây là điều Bước 9 phải loại trừ đúng.
    expect(collect($ketQua->thieuSoCai)->pluck('order_item_id'))->not->toContain($dongTach->id);
});

it('dòng sổ cái mồ côi (trỏ về order_item chưa served) bị phát hiện', function () {
    $ga = Ingredient::factory()->create();
    nhapTonDauDoiSoat($ga, 100, 500_000, $this->chuQuan);

    $order = Order::factory()->create();
    $itemChuaServed = OrderItem::factory()->for($order, 'order')->create([
        'status' => OrderItemStatus::Ordered,
        'served_at' => null,
    ]);

    $movement = app(RecordStockMovement::class)->handle(new RecordStockMovementData(
        uuid: (string) Str::uuid(),
        ingredientId: $ga->id,
        type: StockMovementType::Sale,
        qtyDelta: -5,
        knownCost: null,
        refType: StockMovementRefType::OrderItem,
        refId: $itemChuaServed->id,
        reason: null,
        approvedByUserId: null,
        createdByUserId: $this->chuQuan->id,
        shiftId: null,
    ));

    $ketQua = $this->action->handle();

    $dong = collect($ketQua->soCaiMoCoi)->firstWhere('stock_movement_id', $movement->id);
    expect($dong)->not->toBeNull()
        ->and($dong['ref_id'])->toBe($itemChuaServed->id);
});

it('ghi kết quả vào activity_log, log_name doi-soat-kho', function () {
    $ga = Ingredient::factory()->create();
    nhapTonDauDoiSoat($ga, 100, 500_000, $this->chuQuan);

    $this->action->handle();

    $banGhi = Activity::query()->where('log_name', 'doi-soat-kho')->latest('id')->first();
    expect($banGhi)->not->toBeNull()
        ->and($banGhi->properties->get('sach'))->toBeTrue();
});

it('ca vắt qua nửa đêm: món bưng ra sau 0 giờ vẫn được đối soát lúc đóng ca', function () {
    // Quán mở 20 giờ tối, đóng ca 2 giờ sáng hôm sau — món bưng ra lúc 1 giờ
    // sáng thuộc NGÀY KHÁC với ngày mở ca, vẫn phải nằm trong tầm đối soát.
    Carbon::setTestNow(Carbon::parse('2026-08-10 20:00:00'));

    $thuNgan = User::factory()->cashier()->create();
    $ca = Shift::factory()->open()->create([
        'opened_by_user_id' => $thuNgan->id,
        'opened_at' => now(),
        'opening_cash' => 0,
    ]);

    $ga = Ingredient::factory()->create();
    nhapTonDauDoiSoat($ga, 100, 500_000, $thuNgan);

    $category = Category::factory()->create();
    $product = Product::factory()->for($category)->create();
    $variant = ProductVariant::factory()->for($product)->create(['deducts_stock' => true]);
    Recipe::factory()->for($variant, 'productVariant')->for($ga, 'ingredient')->create(['qty_base' => 10]);

    Carbon::setTestNow(Carbon::parse('2026-08-11 01:00:00'));

    $luot = TableSession::factory()->closed()->create([
        'shift_id' => $ca->id,
        'closed_by_user_id' => $thuNgan->id,
    ]);
    $order = Order::factory()->for($luot, 'tableSession')->create();
    // Bưng ra sau nửa đêm nhưng KHÔNG có sổ cái — lỗi thật cần bắt được.
    $item = OrderItem::factory()->for($order, 'order')->create([
        'product_id' => $product->id,
        'product_variant_id' => $variant->id,
        'status' => OrderItemStatus::Served,
        'served_at' => now(),
        'split_from_item_id' => null,
    ]);

    Carbon::setTestNow(Carbon::parse('2026-08-11 02:00:00'));

    app(CloseShift::class)->handle(new CloseShiftData(
        shiftId: $ca->id,
        countedCash: Money::zero(),
        note: null,
        closedByUserId: $thuNgan->id,
    ));

    $banGhi = Activity::query()->where('log_name', 'doi-soat-kho')->latest('id')->first();
    expect($banGhi->properties->get('sach'))->toBeFalse()
        ->and(collect($banGhi->properties->get('thieu_so_cai'))->pluck('order_item_id'))
        ->toContain($item->id);

    Carbon::setTestNow();
});

it('lệnh chạy tay stock:doi-soat báo sạch khi không lệch, báo đúng nguyên liệu khi lệch', function () {
    $ga = Ingredient::factory()->create(['name' => 'Gà ta']);
    nhapTonDauDoiSoat($ga, 100, 500_000, $this->chuQuan);

    $this->artisan('stock:doi-soat', ['--tu' => '2026-08-01', '--den' => '2026-08-31'])
        ->expectsOutputToContain('SỔ CÁI KHO SẠCH')
        ->assertExitCode(0);

    DB::table('stock_balances')->where('ingredient_id', $ga->id)->update(['qty' => 93]);

    $this->artisan('stock:doi-soat', ['--tu' => '2026-08-01', '--den' => '2026-08-31'])
        ->expectsOutputToContain('Gà ta')
        ->expectsOutputToContain('PHÁT HIỆN LỆCH')
        ->assertExitCode(1);
});

it('job đối soát lỗi thì đóng ca vẫn thành công', function () {
    $thuNgan = User::factory()->cashier()->create();
    $ca = Shift::factory()->open()->create(['opened_by_user_id' => $thuNgan->id, 'opening_cash' => 500_000]);

    // ReconcileStockLedger là final class, Mockery không mock được — thay vào
    // đó tráo thẳng container bằng một đối tượng giả có cùng phương thức
    // handle(), CloseShift chỉ gọi app(ReconcileStockLedger::class)->handle()
    // nên không quan tâm nó có kế thừa lớp thật hay không.
    app()->bind(ReconcileStockLedger::class, fn () => new class
    {
        public function handle(mixed ...$args): never
        {
            throw new RuntimeException('Lỗi giả lập để kiểm tra đóng ca không bị chặn.');
        }
    });

    $caDaDong = app(CloseShift::class)->handle(new CloseShiftData(
        shiftId: $ca->id,
        countedCash: Money::fromInt(500_000),
        note: null,
        closedByUserId: $thuNgan->id,
    ));

    expect($caDaDong->status)->toBe(ShiftStatus::Closed);
});

// ── HAI MỤC CẢNH BÁO (Bước 10) ───────────────────────────────────────────
// Không phải lỗi sổ sách — là việc cần dọn. Chúng KHÔNG được làm sach() sai,
// vì lệnh đối soát đỏ mỗi đêm là lệnh không ai còn đọc.

/** Bán quá tồn để sổ cái ghi ra dòng has_cost = false (tồn về âm). */
function banQuaTon(Ingredient $ingredient, int $soLuong, OrderItem $item, User $user): void
{
    app(RecordStockMovement::class)->handle(new RecordStockMovementData(
        uuid: (string) Str::uuid(),
        ingredientId: $ingredient->id,
        type: StockMovementType::Sale,
        qtyDelta: -$soLuong,
        knownCost: null,
        refType: StockMovementRefType::OrderItem,
        refId: $item->id,
        reason: null,
        approvedByUserId: null,
        createdByUserId: $user->id,
        shiftId: null,
    ));
}

it('mục 4 đếm đúng số dòng sổ cái chưa xác định được giá vốn, theo từng nguyên liệu', function () {
    $ga = Ingredient::factory()->create(['name' => 'Gà ta']);
    $sa = Ingredient::factory()->create(['name' => 'Sả']);
    nhapTonDauDoiSoat($ga, 10, 100_000, $this->chuQuan);
    nhapTonDauDoiSoat($sa, 10, 50_000, $this->chuQuan);

    $ca = Shift::factory()->closed()->create();
    $luot = TableSession::factory()->withTable()->create(['shift_id' => $ca->id]);
    $order = Order::factory()->for($luot, 'tableSession')->create(['sent_at' => now()]);

    $bienThe = ProductVariant::factory()->for(Product::factory()->for(Category::factory())->create())->create(['deducts_stock' => true]);
    Recipe::factory()->for($bienThe, 'productVariant')->for($ga, 'ingredient')->create(['qty_base' => 1]);

    $taoDongMon = fn (): OrderItem => OrderItem::factory()->for($order, 'order')->create([
        'product_variant_id' => $bienThe->id,
        'status' => OrderItemStatus::Served,
        'served_at' => now(),
    ]);

    // Dòng đầu vét sạch tồn gà (vẫn biết giá vốn), hai dòng sau bán lúc kho âm.
    banQuaTon($ga, 10, $taoDongMon(), $this->chuQuan);
    banQuaTon($ga, 3, $taoDongMon(), $this->chuQuan);
    banQuaTon($ga, 2, $taoDongMon(), $this->chuQuan);
    // Sả bán trong tồn dương — không bao giờ vào mục này.
    banQuaTon($sa, 5, $taoDongMon(), $this->chuQuan);

    $ketQua = $this->action->handle(Carbon::today(), Carbon::today());

    expect($ketQua->thieuGiaVon)->toHaveCount(1)
        ->and($ketQua->thieuGiaVon[0]['ingredient_name'])->toBe('Gà ta')
        ->and($ketQua->thieuGiaVon[0]['so_dong'])->toBe(2);

    // CẢNH BÁO, KHÔNG PHẢI LỖI: sổ cái vẫn khớp bảng tồn.
    expect($ketQua->sach())->toBeTrue()
        ->and($ketQua->coCanhBao())->toBeTrue();
});

it('mục 5 liệt kê đúng dòng món của lượt khách đã đóng mà bếp quên bấm xong', function () {
    $ca = Shift::factory()->closed()->create();
    $luotDaDong = TableSession::factory()->withTable()->closed()->create([
        'shift_id' => $ca->id,
        'closed_at' => now(),
    ]);
    $luotConMo = TableSession::factory()->withTable()->create(['shift_id' => $ca->id]);

    $bienThe = ProductVariant::factory()->for(Product::factory()->for(Category::factory())->create())->create();

    $orderDaDong = Order::factory()->for($luotDaDong, 'tableSession')->create(['sent_at' => now()]);
    OrderItem::factory()->for($orderDaDong, 'order')->create([
        'product_variant_id' => $bienThe->id, 'product_name' => 'Lẩu gà',
        'status' => OrderItemStatus::Ordered, 'served_at' => null,
    ]);
    OrderItem::factory()->for($orderDaDong, 'order')->create([
        'product_variant_id' => $bienThe->id,
        'status' => OrderItemStatus::Served, 'served_at' => now(),
    ]);

    // Bàn còn đang ăn dở — món chưa bưng ra là chuyện bình thường, không đếm.
    $orderConMo = Order::factory()->for($luotConMo, 'tableSession')->create(['sent_at' => now()]);
    OrderItem::factory()->for($orderConMo, 'order')->create([
        'product_variant_id' => $bienThe->id,
        'status' => OrderItemStatus::Ordered, 'served_at' => null,
    ]);

    $ketQua = $this->action->handle(Carbon::today(), Carbon::today());

    expect($ketQua->quenBamXong)->toHaveCount(1)
        ->and($ketQua->quenBamXong[0]['product_name'])->toBe('Lẩu gà')
        ->and($ketQua->quenBamXong[0]['table_session_code'])->toBe($luotDaDong->code);

    expect($ketQua->sach())->toBeTrue()
        ->and($ketQua->coCanhBao())->toBeTrue();
});

it('kho sạch sẽ thì hai mục cảnh báo đều rỗng và lệnh stock:doi-soat vẫn báo thành công', function () {
    $ga = Ingredient::factory()->create();
    nhapTonDauDoiSoat($ga, 100, 500_000, $this->chuQuan);

    $ketQua = $this->action->handle(Carbon::today(), Carbon::today());

    expect($ketQua->thieuGiaVon)->toBe([])
        ->and($ketQua->quenBamXong)->toBe([])
        ->and($ketQua->coCanhBao())->toBeFalse();

    $this->artisan('stock:doi-soat')
        ->expectsOutputToContain('4. Dòng sổ cái chưa xác định được giá vốn')
        ->expectsOutputToContain('mọi lần xuất kho trong kỳ đều biết giá vốn')
        ->expectsOutputToContain('5. Món đã tính tiền mà bếp chưa bấm xong')
        ->assertSuccessful();
});

it('lệnh stock:doi-soat in đúng số dòng thiếu giá vốn và VẪN trả về thành công — cảnh báo không phải lỗi', function () {
    $ga = Ingredient::factory()->create(['name' => 'Gà ta']);
    nhapTonDauDoiSoat($ga, 5, 50_000, $this->chuQuan);

    $ca = Shift::factory()->closed()->create();
    $luot = TableSession::factory()->withTable()->create(['shift_id' => $ca->id]);
    $order = Order::factory()->for($luot, 'tableSession')->create(['sent_at' => now()]);
    $bienThe = ProductVariant::factory()->for(Product::factory()->for(Category::factory())->create())->create();

    $taoDongMon = fn (): OrderItem => OrderItem::factory()->for($order, 'order')->create([
        'product_variant_id' => $bienThe->id,
        'status' => OrderItemStatus::Served,
        'served_at' => now(),
    ]);

    banQuaTon($ga, 5, $taoDongMon(), $this->chuQuan);
    banQuaTon($ga, 4, $taoDongMon(), $this->chuQuan);

    $this->artisan('stock:doi-soat')
        ->expectsOutputToContain('1 dòng ở 1 nguyên liệu')
        ->expectsOutputToContain('Gà ta: 1 dòng')
        ->expectsOutputToContain('Nhưng có việc cần dọn')
        ->assertSuccessful();
});
