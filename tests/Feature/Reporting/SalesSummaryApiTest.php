<?php

declare(strict_types=1);

/**
 * Phase 4 Bước 4B.1 — API báo cáo tổng hợp.
 *
 * Dùng lại bộ dữ liệu CỐ Ý DỰNG MÉO của Bước 4B.0: một buổi tối 580.000đ
 * doanh thu, chỉ 200.000đ biết giá vốn (tốn 100.000đ), 380.000đ không ai biết
 * tốn bao nhiêu → 65% doanh thu thiếu giá vốn, vượt xa ngưỡng 20%.
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
use App\Domain\Reporting\Actions\SummarizeDailyReport;
use App\Domain\Reporting\Actions\SummarizeProductProfit;
use App\Domain\Staffing\Models\Shift;
use App\Domain\Staffing\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;

require_once __DIR__.'/../../Fixtures/BuoiToiMeo.php';

function tongHopNgayMeo(): string
{
    $meo = dungBuoiToiMeo();
    $ngay = $meo['ngay']->toDateString();

    app(SummarizeDailyReport::class)->handle($ngay);
    app(SummarizeProductProfit::class)->handle($ngay);

    return $ngay;
}

/**
 * Một buổi tối SẠCH để đối chứng: 10 phần gà nướng 20.000đ, kho đủ hàng,
 * bếp bấm xong đầy đủ → 200.000đ doanh thu, 100.000đ giá vốn, không đồng nào
 * thiếu giá vốn.
 */
function duLieuSachMotNgay(): string
{
    $ngay = Carbon::parse('2026-09-05');
    $chuQuan = User::factory()->owner()->create();

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
    $session = TableSession::factory()->withTable()->closed()->create([
        'shift_id' => $ca->id,
        'opened_at' => $ngay->clone()->setTime(18, 0),
        'closed_at' => $ngay->clone()->setTime(21, 0),
        'subtotal_amount' => 200_000,
        'discount_amount' => 0,
        'total_amount' => 200_000,
        'paid_amount' => 200_000,
    ]);

    $mon = Product::factory()->for(Category::factory()->create())->create(['name' => 'Gà nướng']);
    $bienThe = ProductVariant::factory()->for($mon)->create(['price' => 20_000]);

    $order = Order::factory()->for($session, 'tableSession')->create(['sent_at' => $ngay->clone()->setTime(18, 30)]);
    $dong = OrderItem::factory()->for($order, 'order')->create([
        'product_id' => $mon->id, 'product_variant_id' => $bienThe->id,
        'unit_price' => 20_000, 'options_amount' => 0, 'quantity' => 10,
        'status' => OrderItemStatus::Served, 'served_at' => $ngay->clone()->setTime(19, 0),
    ]);

    ghiGiaVonChoDongMonMeo($ga, $dong, 10, $chuQuan);

    Payment::query()->create([
        'uuid' => (string) Str::uuid(),
        'table_session_id' => $session->id,
        'shift_id' => $ca->id,
        'method' => PaymentMethod::Cash,
        'amount' => 200_000,
        'tendered_amount' => 200_000,
        'change_amount' => 0,
        'status' => PaymentStatus::Completed,
        'received_by_user_id' => $chuQuan->id,
        'paid_at' => $ngay->clone()->setTime(21, 0),
    ]);

    app(SummarizeDailyReport::class)->handle($ngay->toDateString());
    app(SummarizeProductProfit::class)->handle($ngay->toDateString());

    return $ngay->toDateString();
}

function goiBaoCao(User $user, string $tu, string $den): TestResponse
{
    return test()->getJson("/api/v1/reports/summary?tu={$tu}&den={$den}", authHeaderFor($user));
}

// ── Doanh thu: ai đăng nhập cũng xem được ────────────────────────────────

it('chủ quán xem được doanh thu của kỳ, số khớp bảng tổng hợp', function () {
    $ngay = tongHopNgayMeo();

    goiBaoCao(User::factory()->owner()->create(), $ngay, $ngay)
        ->assertOk()
        ->assertJsonPath('data.doanh_thu.tong_amount', 580_000)
        ->assertJsonPath('data.doanh_thu.tien_mat_amount', 580_000)
        ->assertJsonPath('data.doanh_thu.chuyen_khoan_amount', 0)
        ->assertJsonPath('data.ky.so_ngay', 1);
});

it('trả về danh sách theo ngày và top món bán chạy', function () {
    $ngay = tongHopNgayMeo();

    $ketQua = goiBaoCao(User::factory()->owner()->create(), $ngay, $ngay)->assertOk();

    $ketQua->assertJsonPath('data.theo_ngay.0.ngay', $ngay)
        ->assertJsonPath('data.theo_ngay.0.doanh_thu_amount', 580_000);

    // Ba món trong fixture, món bán nhiều nhất (10 phần gà nướng) đứng đầu.
    expect($ketQua->json('data.top_mon_ban_chay'))->toHaveCount(3);
    $ketQua->assertJsonPath('data.top_mon_ban_chay.0.so_luong', 10)
        ->assertJsonPath('data.top_mon_ban_chay.0.doanh_thu_amount', 200_000);
});

it('chưa đăng nhập thì không gọi được', function () {
    $this->getJson('/api/v1/reports/summary')->assertUnauthorized();
});

// ── Quyền xem lãi gộp và giá vốn ─────────────────────────────────────────

it('thu ngân KHÔNG thấy trường lãi gộp và giá vốn — trường biến mất, không phải null', function () {
    $ngay = tongHopNgayMeo();

    $ketQua = goiBaoCao(User::factory()->cashier()->create(), $ngay, $ngay)->assertOk();

    // Doanh thu thì vẫn xem được — thu ngân cần đối soát két.
    $ketQua->assertJsonPath('data.doanh_thu.tong_amount', 580_000);

    // Còn giá vốn thì KHÔNG CÓ KHOÁ NÀO trong JSON. Trả null hay 0 là nói dối:
    // người đọc sẽ tưởng quán hoà vốn, sự thật là "anh không được xem".
    $ketQua->assertJsonMissingPath('data.lai_gop')
        ->assertJsonMissingPath('data.chat_luong_du_lieu');

    expect(array_keys($ketQua->json('data')))
        ->not->toContain('lai_gop')
        ->not->toContain('chat_luong_du_lieu');
});

it('phục vụ và bếp cũng không thấy trường lãi gộp', function (string $vaiTro) {
    $ngay = tongHopNgayMeo();

    goiBaoCao(User::factory()->{$vaiTro}()->create(), $ngay, $ngay)
        ->assertOk()
        ->assertJsonMissingPath('data.lai_gop')
        ->assertJsonMissingPath('data.chat_luong_du_lieu');
})->with(['staff', 'kitchen']);

it('chủ quán thấy đủ hai khối lãi gộp và chất lượng dữ liệu', function () {
    $ngay = tongHopNgayMeo();

    $data = goiBaoCao(User::factory()->owner()->create(), $ngay, $ngay)->assertOk()->json('data');

    expect($data)->toHaveKey('lai_gop')
        ->and($data)->toHaveKey('chat_luong_du_lieu');
});

// ── Cờ chất lượng dữ liệu ────────────────────────────────────────────────

it('cờ chất lượng dữ liệu tính đúng trên fixture có món thiếu giá vốn', function () {
    $ngay = tongHopNgayMeo();

    goiBaoCao(User::factory()->owner()->create(), $ngay, $ngay)
        ->assertOk()
        ->assertJsonPath('data.chat_luong_du_lieu.doanh_thu_theo_mon_amount', 580_000)
        ->assertJsonPath('data.chat_luong_du_lieu.doanh_thu_thieu_gia_von_amount', 380_000)
        // 380.000 trên 580.000 = 65%
        ->assertJsonPath('data.chat_luong_du_lieu.phan_tram_thieu_gia_von', 65)
        ->assertJsonPath('data.chat_luong_du_lieu.du_tin_cay', false)
        ->assertJsonPath('data.chat_luong_du_lieu.nguong_khong_du_tin_cay', 20);
});

it('vượt ngưỡng thì KHÔNG hiện số lãi — trả null kèm lý do, không trả số sai', function () {
    $ngay = tongHopNgayMeo();

    $ketQua = goiBaoCao(User::factory()->owner()->create(), $ngay, $ngay)->assertOk();

    $ketQua->assertJsonPath('data.lai_gop.du_lieu_du_de_tinh', false)
        ->assertJsonPath('data.lai_gop.lai_gop_amount', null)
        ->assertJsonPath('data.lai_gop.gia_von_amount', null);

    // Và phải nói rõ vì sao, không im lặng.
    expect($ketQua->json('data.lai_gop.ly_do'))->not->toBeNull()
        ->and($ketQua->json('data.chat_luong_du_lieu.canh_bao'))->toContain('65%');
});

it('dữ liệu sạch thì hiện số lãi thật kèm cờ báo không thiếu gì', function () {
    $ngay = duLieuSachMotNgay();

    goiBaoCao(User::factory()->owner()->create(), $ngay, $ngay)
        ->assertOk()
        ->assertJsonPath('data.chat_luong_du_lieu.phan_tram_thieu_gia_von', 0)
        ->assertJsonPath('data.chat_luong_du_lieu.du_tin_cay', true)
        ->assertJsonPath('data.chat_luong_du_lieu.canh_bao', null)
        ->assertJsonPath('data.lai_gop.du_lieu_du_de_tinh', true)
        // 200.000 doanh thu, giá vốn 100.000 → lãi gộp 100.000
        ->assertJsonPath('data.lai_gop.lai_gop_amount', 100_000)
        ->assertJsonPath('data.lai_gop.gia_von_amount', 100_000);
});

it('kỳ chưa có dữ liệu nào thì nói thẳng là không có gì để tính, không trả lãi 0đ', function () {
    goiBaoCao(User::factory()->owner()->create(), '2026-01-01', '2026-01-31')
        ->assertOk()
        ->assertJsonPath('data.doanh_thu.tong_amount', 0)
        ->assertJsonPath('data.lai_gop.du_lieu_du_de_tinh', false)
        ->assertJsonPath('data.lai_gop.lai_gop_amount', null)
        ->assertJsonPath('data.chat_luong_du_lieu.canh_bao', 'Kỳ này chưa có dòng món nào được tổng hợp — không có gì để tính lãi.');
});

// ── Khoảng ngày ──────────────────────────────────────────────────────────

it('ngày cuối trước ngày đầu thì bị chặn', function () {
    goiBaoCao(User::factory()->owner()->create(), '2026-08-20', '2026-08-10')
        ->assertUnprocessable();
});

it('xin khoảng dài hơn một năm thì bị chặn, không kéo cả kho lên', function () {
    goiBaoCao(User::factory()->owner()->create(), '2025-01-01', '2026-08-13')
        ->assertUnprocessable();
});

it('không ghi khoảng ngày thì mặc định từ đầu tháng này tới hôm nay', function () {
    $this->travelTo(Carbon::parse('2026-08-25 10:00:00'));

    $this->getJson('/api/v1/reports/summary', authHeaderFor(User::factory()->owner()->create()))
        ->assertOk()
        ->assertJsonPath('data.ky.tu', '2026-08-01')
        ->assertJsonPath('data.ky.den', '2026-08-25');
});
