<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Inventory\Actions\DeductStockForServedItem;
use App\Domain\Inventory\Actions\RecordStockMovement;
use App\Domain\Inventory\DTO\RecordStockMovementData;
use App\Domain\Inventory\Enums\StockMovementRefType;
use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Inventory\Models\Ingredient;
use App\Domain\Inventory\Models\Recipe;
use App\Domain\Inventory\Models\StockBalance;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Ordering\Actions\CancelOrderItem;
use App\Domain\Ordering\Actions\PlaceOrder;
use App\Domain\Ordering\Actions\UpdateOrderItemStatus;
use App\Domain\Ordering\DTO\CancelOrderItemData;
use App\Domain\Ordering\DTO\PlaceOrderData;
use App\Domain\Ordering\DTO\PlaceOrderItemData;
use App\Domain\Ordering\DTO\UpdateOrderItemStatusData;
use App\Domain\Ordering\Enums\OrderItemStatus;
use App\Domain\Ordering\Models\Order;
use App\Domain\Ordering\Models\OrderItem;
use App\Domain\Ordering\Models\TableSession;
use App\Domain\Staffing\Models\Shift;
use App\Domain\Staffing\Models\User;
use App\Domain\Sync\Actions\SyncBatch;
use App\Domain\Sync\DTO\SyncBatchData;
use App\Domain\Sync\DTO\SyncOperationData;
use App\Domain\Sync\Enums\OperationType;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/** Nhập tồn đầu cho một nguyên liệu qua đúng một cửa duy nhất — RecordStockMovement. */
function nhapTonDau(Ingredient $ingredient, int $qty, int $cost, User $user): void
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

/** Tạo một biến thể món có định lượng (recipes), gán deducts_stock = true. */
function taoBienTheCoDinhLuong(array $dinhLuong): ProductVariant
{
    $category = Category::factory()->create();
    $product = Product::factory()->for($category)->create();
    $variant = ProductVariant::factory()->for($product)->create(['deducts_stock' => true]);

    foreach ($dinhLuong as [$ingredient, $qtyBase]) {
        Recipe::factory()->for($variant, 'productVariant')->for($ingredient, 'ingredient')->create(['qty_base' => $qtyBase]);
    }

    return $variant;
}

/** Gọi món (PlaceOrder) cho một biến thể, trả về dòng order_item vừa tạo. */
function goiMonChoBienThe(TableSession $luot, ProductVariant $variant, int $quantity, User $user): OrderItem
{
    $order = app(PlaceOrder::class)->handle(new PlaceOrderData(
        uuid: (string) Str::uuid(),
        tableSessionId: $luot->id,
        items: [new PlaceOrderItemData((string) Str::uuid(), $variant->product_id, $variant->id, $quantity, null, [])],
        note: null,
        createdByUserId: $user->id,
    ));

    return $order->items()->sole();
}

beforeEach(function () {
    $this->ca = Shift::factory()->open()->create();
    $this->user = User::factory()->owner()->create();
    $this->luot = TableSession::factory()->withTable()->create(['shift_id' => $this->ca->id]);
});

it('phục vụ 1 lẩu gà trừ đúng từng nguyên liệu theo định lượng', function () {
    $ga = Ingredient::factory()->create(['code' => 'T-GA']);
    $sa = Ingredient::factory()->create(['code' => 'T-SA']);
    nhapTonDau($ga, 5_000, 500_000, $this->user);
    nhapTonDau($sa, 1_000, 30_000, $this->user);

    $bienThe = taoBienTheCoDinhLuong([[$ga, 300], [$sa, 30]]);
    $dongMon = goiMonChoBienThe($this->luot, $bienThe, 1, $this->user);

    app(UpdateOrderItemStatus::class)->handle(new UpdateOrderItemStatusData($dongMon->id, $this->user->id));

    expect(StockBalance::query()->find($ga->id)->qty)->toBe(5_000 - 300)
        ->and(StockBalance::query()->find($sa->id)->qty)->toBe(1_000 - 30);

    expect(StockMovement::query()->where('ref_type', StockMovementRefType::OrderItem)->where('ref_id', $dongMon->id)->count())->toBe(2);
});

it('phục vụ 3 lon Tiger trừ đúng 3 lon (định lượng 1:1 vẫn đi qua recipes)', function () {
    $bia = Ingredient::factory()->create(['code' => 'T-BIA']);
    nhapTonDau($bia, 100, 2_500_000, $this->user);

    $bienThe = taoBienTheCoDinhLuong([[$bia, 1]]);
    $dongMon = goiMonChoBienThe($this->luot, $bienThe, 3, $this->user);

    app(UpdateOrderItemStatus::class)->handle(new UpdateOrderItemStatusData($dongMon->id, $this->user->id));

    expect(StockBalance::query()->find($bia->id)->qty)->toBe(97);
    expect(StockMovement::query()->where('ref_id', $dongMon->id)->sole()->qty_delta)->toBe(-3);
});

it('phục vụ món không trừ kho thì không tạo dòng sổ cái nào', function () {
    $category = Category::factory()->create();
    $product = Product::factory()->for($category)->create();
    $bienThe = ProductVariant::factory()->for($product)->create(['deducts_stock' => false]);

    $dongMon = goiMonChoBienThe($this->luot, $bienThe, 2, $this->user);

    app(UpdateOrderItemStatus::class)->handle(new UpdateOrderItemStatusData($dongMon->id, $this->user->id));

    expect(StockMovement::query()->where('ref_id', $dongMon->id)->count())->toBe(0);
});

it('gọi trừ kho hai lần cho cùng dòng món chỉ trừ đúng một lần, không ném lỗi', function () {
    $ga = Ingredient::factory()->create();
    nhapTonDau($ga, 5_000, 500_000, $this->user);
    $bienThe = taoBienTheCoDinhLuong([[$ga, 300]]);
    $dongMon = goiMonChoBienThe($this->luot, $bienThe, 1, $this->user);
    $dongMon->update(['status' => OrderItemStatus::Served, 'served_at' => now()]);

    $action = app(DeductStockForServedItem::class);
    $action->handle($dongMon, $this->user->id, $this->ca->id);
    $action->handle($dongMon->refresh(), $this->user->id, $this->ca->id);

    expect(StockBalance::query()->find($ga->id)->qty)->toBe(5_000 - 300);
    expect(StockMovement::query()->where('ref_id', $dongMon->id)->count())->toBe(1);
});

it('huỷ món ĐÃ served thì không hoàn kho', function () {
    $ga = Ingredient::factory()->create();
    nhapTonDau($ga, 5_000, 500_000, $this->user);
    $bienThe = taoBienTheCoDinhLuong([[$ga, 300]]);
    $dongMon = goiMonChoBienThe($this->luot, $bienThe, 1, $this->user);

    app(UpdateOrderItemStatus::class)->handle(new UpdateOrderItemStatusData($dongMon->id, $this->user->id));
    $tonSauPhucVu = StockBalance::query()->find($ga->id)->qty;

    $thuNgan = User::factory()->cashier()->withPin('1234')->create();
    app(CancelOrderItem::class)->handle(new CancelOrderItemData(
        orderId: $dongMon->order_id,
        orderItemId: $dongMon->id,
        quantity: 1,
        reason: 'Khách trả món đã ăn dở',
        cancelledByUserId: $thuNgan->id,
        approverUserId: $thuNgan->id,
        approverPin: '1234',
        newItemUuid: null,
        optionUuids: [],
    ));

    expect(StockBalance::query()->find($ga->id)->qty)->toBe($tonSauPhucVu);
    expect(StockMovement::query()->where('ref_id', $dongMon->id)->count())->toBe(1);
});

it('huỷ MỘT PHẦN món đã served (tách dòng) không trừ thêm và không hoàn — dòng tách kế thừa served_at nhưng không tạo sổ cái', function () {
    $ga = Ingredient::factory()->create();
    nhapTonDau($ga, 5_000, 500_000, $this->user);
    $bienThe = taoBienTheCoDinhLuong([[$ga, 300]]);
    $dongMon = goiMonChoBienThe($this->luot, $bienThe, 5, $this->user);

    app(UpdateOrderItemStatus::class)->handle(new UpdateOrderItemStatusData($dongMon->id, $this->user->id));
    $tonSauPhucVu = StockBalance::query()->find($ga->id)->qty;
    expect($tonSauPhucVu)->toBe(5_000 - 300 * 5);

    $thuNgan = User::factory()->cashier()->withPin('1234')->create();
    $dongDaHuy = app(CancelOrderItem::class)->handle(new CancelOrderItemData(
        orderId: $dongMon->order_id,
        orderItemId: $dongMon->id,
        quantity: 1,
        reason: 'Khách trả bớt 1 phần đã ăn',
        cancelledByUserId: $thuNgan->id,
        approverUserId: $thuNgan->id,
        approverPin: '1234',
        newItemUuid: (string) Str::uuid(),
        optionUuids: [],
    ));

    expect($dongDaHuy->split_from_item_id)->toBe($dongMon->id)
        ->and($dongDaHuy->served_at)->not->toBeNull();

    // Gọi lại trừ kho cho dòng vừa tách — phải bị chặn bởi split_from_item_id, không trừ.
    app(DeductStockForServedItem::class)->handle($dongDaHuy, $thuNgan->id, $this->ca->id);

    expect(StockBalance::query()->find($ga->id)->qty)->toBe($tonSauPhucVu);
    expect(StockMovement::query()->where('ref_id', $dongDaHuy->id)->count())->toBe(0);
    expect(StockMovement::query()->where('ref_id', $dongMon->id)->count())->toBe(1);
});

it('huỷ món CHƯA served thì không có gì để hoàn, tồn không đổi', function () {
    $ga = Ingredient::factory()->create();
    nhapTonDau($ga, 5_000, 500_000, $this->user);
    $bienThe = taoBienTheCoDinhLuong([[$ga, 300]]);
    $dongMon = goiMonChoBienThe($this->luot, $bienThe, 2, $this->user);

    app(CancelOrderItem::class)->handle(new CancelOrderItemData(
        orderId: $dongMon->order_id,
        orderItemId: $dongMon->id,
        quantity: 2,
        reason: 'Khách đổi ý trước khi bếp làm',
        cancelledByUserId: $this->user->id,
        approverUserId: null,
        approverPin: null,
        newItemUuid: null,
        optionUuids: [],
    ));

    expect(StockBalance::query()->find($ga->id)->qty)->toBe(5_000);
    expect(StockMovement::query()->where('ref_id', $dongMon->id)->count())->toBe(0);
});

it('món đến muộn qua đồng bộ rồi được phục vụ thì trừ kho đúng một lần', function () {
    $ga = Ingredient::factory()->create();
    nhapTonDau($ga, 5_000, 500_000, $this->user);
    $bienThe = taoBienTheCoDinhLuong([[$ga, 300]]);

    $uuidMon = (string) Str::uuid();
    $goi = new SyncBatchData(
        batchUuid: (string) Str::uuid(),
        deviceId: 'pos-test',
        clientTime: CarbonImmutable::now(),
        operations: [
            new SyncOperationData(
                opUuid: (string) Str::uuid(),
                type: OperationType::PlaceOrder,
                occurredAt: CarbonImmutable::now()->subMinutes(15),
                dependsOn: [],
                payload: [
                    'uuid' => $uuidMon,
                    'table_session_uuid' => $this->luot->uuid,
                    'items' => [[
                        'uuid' => (string) Str::uuid(),
                        'product_id' => $bienThe->product_id,
                        'product_variant_id' => $bienThe->id,
                        'quantity' => 1,
                    ]],
                ],
                viTriGoc: 0,
            ),
        ],
        receivedByUserId: $this->user->id,
    );

    app(SyncBatch::class)->handle($goi);

    $order = Order::query()->where('uuid', $uuidMon)->sole();
    $dongMon = $order->items()->sole();

    expect($dongMon->status)->toBe(OrderItemStatus::Ordered);

    app(UpdateOrderItemStatus::class)->handle(new UpdateOrderItemStatusData($dongMon->id, $this->user->id));

    expect(StockBalance::query()->find($ga->id)->qty)->toBe(5_000 - 300);
    expect(StockMovement::query()->where('ref_id', $dongMon->id)->count())->toBe(1);
});

it('nguyên liệu không đủ tồn vẫn cho phục vụ, tồn cho phép về âm', function () {
    $ga = Ingredient::factory()->create();
    nhapTonDau($ga, 100, 10_000, $this->user);
    $bienThe = taoBienTheCoDinhLuong([[$ga, 300]]);
    $dongMon = goiMonChoBienThe($this->luot, $bienThe, 1, $this->user);

    app(UpdateOrderItemStatus::class)->handle(new UpdateOrderItemStatusData($dongMon->id, $this->user->id));

    expect($dongMon->refresh()->status)->toBe(OrderItemStatus::Served)
        ->and($dongMon->served_at)->not->toBeNull();
    expect(StockBalance::query()->find($ga->id)->qty)->toBe(100 - 300);
});

it('gọi lại trừ kho cho cùng dòng món vẫn chỉ ra một bộ dòng sổ cái, mã vân tay không đổi', function () {
    $ga = Ingredient::factory()->create(['code' => 'T-GA-LAP']);
    $sa = Ingredient::factory()->create(['code' => 'T-SA-LAP']);
    nhapTonDau($ga, 5_000, 500_000, $this->user);
    nhapTonDau($sa, 1_000, 30_000, $this->user);

    $bienThe = taoBienTheCoDinhLuong([[$ga, 300], [$sa, 30]]);
    $dongMon = goiMonChoBienThe($this->luot, $bienThe, 1, $this->user);

    $action = app(DeductStockForServedItem::class);
    $action->handle($dongMon, $this->user->id, $this->ca->id);

    $vanTayLanDau = StockMovement::query()
        ->where('ref_type', StockMovementRefType::OrderItem)
        ->where('ref_id', $dongMon->id)
        ->orderBy('ingredient_id')
        ->pluck('uuid')
        ->all();

    // Gọi lại lần hai — cùng dữ liệu gốc thì mã vân tay sinh ra y hệt, nên kể
    // cả khi lớp chặn theo ref_id có hỏng thì cũng không ghi thêm dòng nào.
    $action->handle($dongMon->refresh(), $this->user->id, $this->ca->id);

    $vanTayLanHai = StockMovement::query()
        ->where('ref_type', StockMovementRefType::OrderItem)
        ->where('ref_id', $dongMon->id)
        ->orderBy('ingredient_id')
        ->pluck('uuid')
        ->all();

    expect($vanTayLanHai)->toBe($vanTayLanDau)
        ->and($vanTayLanDau)->toHaveCount(2);

    expect(StockBalance::query()->find($ga->id)->qty)->toBe(5_000 - 300)
        ->and(StockBalance::query()->find($sa->id)->qty)->toBe(1_000 - 30);
});
