<?php

declare(strict_types=1);

/**
 * BỘ DỮ LIỆU CỐ Ý DỰNG MÉO — Phase 4 Bước 4B.0.
 *
 * Vì sao không dùng `pos:demo`: dữ liệu demo sạch chỉ chứng minh code không
 * sập, không chứng minh nó sửa đúng cái méo. Một buổi tối ở đây có đủ ba loại
 * dòng món trộn lẫn, đúng như quán thật:
 *
 *   1. Bán bình thường, kho đủ   → biết giá vốn
 *   2. Bán lúc kho đang âm       → has_cost = false, KHÔNG ai biết giá vốn
 *   3. Bếp quên bấm "xong"       → chưa trừ kho, cũng không biết giá vốn
 *
 * Đặt ở `tests/Fixtures/` (ngoài hai bộ test mà phpunit.xml quét) và được
 * `require_once` từ các file test cần dùng, để chạy lẻ MỘT file test vẫn có đủ
 * hàm — không phụ thuộc việc file test khác tình cờ được nạp trước.
 */

use App\Domain\Billing\Enums\PaymentMethod;
use App\Domain\Billing\Enums\PaymentStatus;
use App\Domain\Billing\Models\Payment;
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
use App\Domain\Staffing\Models\Shift;
use App\Domain\Staffing\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/** Ghi dòng sổ cái kho đúng như DeductStockForServedItem ghi lúc phục vụ. */
function ghiGiaVonChoDongMonMeo(Ingredient $ga, OrderItem $item, int $soLuongTru, User $user): void
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

/**
 * Dựng một buổi tối méo: doanh thu 580.000đ, trong đó chỉ 200.000đ biết giá
 * vốn (tốn 100.000đ), còn 380.000đ không ai biết tốn bao nhiêu.
 *
 *   Gà nướng  10 phần x 20.000 = 200.000đ — kho đủ, giá vốn 100.000đ
 *   Lẩu gà     4 phần x 50.000 = 200.000đ — bán lúc kho âm
 *   Nem rán    6 phần x 30.000 = 180.000đ — bếp quên bấm xong
 *
 * @return array{ngay: Carbon, session: TableSession, bien_the_du: ProductVariant, bien_the_am: ProductVariant, bien_the_quen: ProductVariant}
 */
function dungBuoiToiMeo(): array
{
    $ngay = Carbon::parse('2026-08-20');
    $chuQuan = User::factory()->owner()->create();

    // Kho: mua 10 đơn vị giá 10.000đ/đơn vị. Chỉ đủ cho món "đủ kho".
    $ga = Ingredient::factory()->create();
    app(RecordStockMovement::class)->handle(new RecordStockMovementData(
        uuid: (string) Str::uuid(),
        ingredientId: $ga->id,
        type: StockMovementType::Purchase,
        qtyDelta: 10,
        knownCost: 100_000,
        refType: StockMovementRefType::Manual,
        refId: null,
        reason: null,
        approvedByUserId: null,
        createdByUserId: $chuQuan->id,
        shiftId: null,
    ));

    $ca = Shift::factory()->closed()->create(['opened_at' => $ngay->clone()->setTime(17, 0)]);

    // Lượt khách ĐÃ ĐÓNG — bắt buộc, vì "bếp quên bấm xong" chỉ tính khi bàn
    // đã đóng (bàn còn ăn dở thì món chưa bưng ra là chuyện bình thường).
    $session = TableSession::factory()->withTable()->closed()->create([
        'shift_id' => $ca->id,
        'opened_at' => $ngay->clone()->setTime(18, 0),
        'closed_at' => $ngay->clone()->setTime(22, 0),
        'subtotal_amount' => 580_000,
        'discount_amount' => 0,
        'total_amount' => 580_000,
        'paid_amount' => 580_000,
    ]);

    $category = Category::factory()->create();

    $monDu = Product::factory()->for($category)->create(['name' => 'Gà nướng (đủ kho)']);
    $bienTheDu = ProductVariant::factory()->for($monDu)->create(['price' => 20_000]);

    $monAm = Product::factory()->for($category)->create(['name' => 'Lẩu gà (kho âm)']);
    $bienTheAm = ProductVariant::factory()->for($monAm)->create(['price' => 50_000]);

    $monQuen = Product::factory()->for($category)->create(['name' => 'Nem rán (bếp quên bấm)']);
    $bienTheQuen = ProductVariant::factory()->for($monQuen)->create(['price' => 30_000]);

    $order = Order::factory()->for($session, 'tableSession')->create(['sent_at' => $ngay->clone()->setTime(18, 30)]);

    // (1) 10 phần, kho đủ → giá vốn 100.000đ, doanh thu 200.000đ
    $dongDu = OrderItem::factory()->for($order, 'order')->create([
        'product_id' => $monDu->id, 'product_variant_id' => $bienTheDu->id,
        'unit_price' => 20_000, 'options_amount' => 0, 'quantity' => 10,
        'status' => OrderItemStatus::Served, 'served_at' => $ngay->clone()->setTime(19, 0),
    ]);

    // (2) 4 phần bán lúc kho đã cạn → has_cost = false, doanh thu 200.000đ
    $dongAm = OrderItem::factory()->for($order, 'order')->create([
        'product_id' => $monAm->id, 'product_variant_id' => $bienTheAm->id,
        'unit_price' => 50_000, 'options_amount' => 0, 'quantity' => 4,
        'status' => OrderItemStatus::Served, 'served_at' => $ngay->clone()->setTime(20, 0),
    ]);

    // (3) 6 phần bếp quên bấm xong → không có dòng sổ cái nào, doanh thu 180.000đ
    OrderItem::factory()->for($order, 'order')->create([
        'product_id' => $monQuen->id, 'product_variant_id' => $bienTheQuen->id,
        'unit_price' => 30_000, 'options_amount' => 0, 'quantity' => 6,
        'status' => OrderItemStatus::Ordered, 'served_at' => null,
    ]);

    ghiGiaVonChoDongMonMeo($ga, $dongDu, 10, $chuQuan);  // trừ hết sạch 10 đơn vị
    ghiGiaVonChoDongMonMeo($ga, $dongAm, 4, $chuQuan);   // kho đã 0 → bán lúc âm

    // Khách trả đủ tiền — để bảng tóm tắt ngày có doanh thu két thật.
    Payment::query()->create([
        'uuid' => (string) Str::uuid(),
        'table_session_id' => $session->id,
        'shift_id' => $ca->id,
        'method' => PaymentMethod::Cash,
        'amount' => 580_000,
        'tendered_amount' => 580_000,
        'change_amount' => 0,
        'status' => PaymentStatus::Completed,
        'received_by_user_id' => $chuQuan->id,
        'paid_at' => $ngay->clone()->setTime(22, 0),
    ]);

    return [
        'ngay' => $ngay,
        'session' => $session,
        'bien_the_du' => $bienTheDu,
        'bien_the_am' => $bienTheAm,
        'bien_the_quen' => $bienTheQuen,
    ];
}
