<?php

declare(strict_types=1);

use App\Domain\Inventory\Actions\CloseStockTake;
use App\Domain\Inventory\Actions\CreatePurchase;
use App\Domain\Inventory\Actions\OpenStockTake;
use App\Domain\Inventory\Actions\ReceivePurchase;
use App\Domain\Inventory\Actions\ReconcileStockLedger;
use App\Domain\Inventory\Actions\RecordStockMovement;
use App\Domain\Inventory\Actions\RecordStockTakeCount;
use App\Domain\Inventory\DTO\CloseStockTakeData;
use App\Domain\Inventory\DTO\CreatePurchaseData;
use App\Domain\Inventory\DTO\OpenStockTakeData;
use App\Domain\Inventory\DTO\PurchaseLineData;
use App\Domain\Inventory\DTO\ReceivePurchaseData;
use App\Domain\Inventory\DTO\RecordStockMovementData;
use App\Domain\Inventory\DTO\RecordStockTakeCountData;
use App\Domain\Inventory\Enums\PurchaseStatus;
use App\Domain\Inventory\Enums\StockMovementRefType;
use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Inventory\Enums\StockTakeStatus;
use App\Domain\Inventory\Models\Ingredient;
use App\Domain\Inventory\Models\IngredientUnit;
use App\Domain\Inventory\Models\Purchase;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Inventory\Models\StockTake;
use App\Domain\Inventory\Models\Supplier;
use App\Domain\Staffing\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Hai nhánh đối soát bổ sung — review Phase 3 mục 8.2-M.
 *
 * BUG CŨ: lệnh stock:doi-soat chỉ kiểm nhánh bán món (order_item ↔ sổ cái).
 * Hai nhánh còn lại của K11 — nhập hàng và kiểm kê — không ai kiểm, nên một
 * phiếu nhận hàng mà không sinh sổ cái (hoặc ngược lại) trôi qua im lặng.
 */
beforeEach(function () {
    $this->chuQuan = User::factory()->owner()->create();
    $this->action = new ReconcileStockLedger;
});

function taoPhieuNhapDaNhan(User $nguoi): Purchase
{
    $bia = Ingredient::factory()->create();
    IngredientUnit::factory()->for($bia, 'ingredient')->create(['unit_name' => 'Thùng', 'factor' => 24]);

    $phieu = app(CreatePurchase::class)->handle(new CreatePurchaseData(
        supplierId: Supplier::factory()->create()->id,
        note: null,
        invoiceNo: null,
        lines: [new PurchaseLineData(ingredientId: $bia->id, unitName: 'Thùng', qtyInput: 5, unitCost: 300_000)],
        createdByUserId: $nguoi->id,
    ));

    return app(ReceivePurchase::class)->handle(new ReceivePurchaseData(
        purchaseId: $phieu->id,
        receivedByUserId: $nguoi->id,
    ));
}

function taoPhieuKiemKeDaChot(User $nguoi, int $demDuoc = 95): StockTake
{
    $bia = Ingredient::factory()->create();

    app(RecordStockMovement::class)->handle(new RecordStockMovementData(
        uuid: (string) Str::uuid(),
        ingredientId: $bia->id,
        type: StockMovementType::Purchase,
        qtyDelta: 100,
        knownCost: 500_000,
        refType: StockMovementRefType::Manual,
        refId: null,
        reason: null,
        approvedByUserId: null,
        createdByUserId: $nguoi->id,
        shiftId: null,
    ));

    $phieu = app(OpenStockTake::class)->handle(new OpenStockTakeData(note: null, openedByUserId: $nguoi->id));
    $dong = $phieu->items()->where('ingredient_id', $bia->id)->sole();

    app(RecordStockTakeCount::class)->handle(new RecordStockTakeCountData($dong->id, $demDuoc));

    return app(CloseStockTake::class)->handle(new CloseStockTakeData($phieu->id, $nguoi->id));
}

/**
 * Dựng hiện trường "chứng từ có mà sổ cái không có" — thứ đối soát sinh ra để bắt.
 *
 * Trước 12/08 hai hàm dưới đây dựng cảnh bằng cách XOÁ dòng sổ cái bằng SQL thô.
 * Không dùng được nữa: sổ cái giờ đã khoá cấm xoá ở tầng database
 * (trg_stock_movements_no_delete), và đó đúng là điều mong muốn.
 *
 * Cách mới thật hơn cách cũ: KHÔNG BAO GIỜ tạo dòng sổ cái ngay từ đầu — đổi
 * trạng thái chứng từ bằng SQL thô mà không đi qua Action. Đây chính là hình
 * dạng của con bug ngoài đời (ai đó vá trạng thái bằng tay, hoặc một Action
 * tương lai quên gọi RecordStockMovement), chứ không phải cảnh "sổ cái tự bốc
 * hơi" mà giờ đã thành bất khả thi.
 */
function taoPhieuNhapDaNhanNhungThieuSoCai(User $nguoi): Purchase
{
    $bia = Ingredient::factory()->create();
    IngredientUnit::factory()->for($bia, 'ingredient')->create(['unit_name' => 'Thùng', 'factor' => 24]);

    $phieu = app(CreatePurchase::class)->handle(new CreatePurchaseData(
        supplierId: Supplier::factory()->create()->id,
        note: null,
        invoiceNo: null,
        lines: [new PurchaseLineData(ingredientId: $bia->id, unitName: 'Thùng', qtyInput: 5, unitCost: 300_000)],
        createdByUserId: $nguoi->id,
    ));

    // Đánh dấu đã nhận mà KHÔNG đi qua ReceivePurchase — không dòng sổ cái nào ra đời.
    DB::table('purchases')->where('id', $phieu->id)->update([
        'status' => PurchaseStatus::Received->value,
        'received_at' => now(),
        'received_by_user_id' => $nguoi->id,
    ]);

    return $phieu->refresh();
}

function taoPhieuKiemKeDaChotNhungThieuSoCai(User $nguoi, int $demDuoc = 95): StockTake
{
    $bia = Ingredient::factory()->create();

    app(RecordStockMovement::class)->handle(new RecordStockMovementData(
        uuid: (string) Str::uuid(),
        ingredientId: $bia->id,
        type: StockMovementType::Purchase,
        qtyDelta: 100,
        knownCost: 500_000,
        refType: StockMovementRefType::Manual,
        refId: null,
        reason: null,
        approvedByUserId: null,
        createdByUserId: $nguoi->id,
        shiftId: null,
    ));

    $phieu = app(OpenStockTake::class)->handle(new OpenStockTakeData(note: null, openedByUserId: $nguoi->id));
    $dong = $phieu->items()->where('ingredient_id', $bia->id)->sole();
    app(RecordStockTakeCount::class)->handle(new RecordStockTakeCountData($dong->id, $demDuoc));

    // Chốt phiếu mà KHÔNG đi qua CloseStockTake — không dòng sổ cái nào ra đời.
    DB::table('stock_takes')->where('id', $phieu->id)->update([
        'status' => StockTakeStatus::Closed->value,
        'closed_at' => now(),
        'closed_by_user_id' => $nguoi->id,
        'total_diff_cost' => 0,
    ]);

    return $phieu->refresh();
}

// ── NHÁNH NHẬP HÀNG (mục 6) ─────────────────────────────────────────────────

it('nhận hàng đúng luồng thì nhánh nhập hàng sạch', function () {
    taoPhieuNhapDaNhan($this->chuQuan);

    $ketQua = $this->action->handle();

    expect($ketQua->sach())->toBeTrue()
        ->and($ketQua->thieuSoCaiNhapHang)->toBe([])
        ->and($ketQua->moCoiNhapHang)->toBe([]);
});

it('BUG CŨ: phiếu đã nhận mà THIẾU dòng sổ cái không ai phát hiện — nay báo đỏ', function () {
    $phieu = taoPhieuNhapDaNhanNhungThieuSoCai($this->chuQuan);

    $dong = $phieu->items()->sole();

    $ketQua = $this->action->handle();

    expect($ketQua->sach())->toBeFalse()
        ->and($ketQua->thieuSoCaiNhapHang)->toHaveCount(1)
        ->and($ketQua->thieuSoCaiNhapHang[0]['purchase_item_id'])->toBe($dong->id)
        ->and($ketQua->thieuSoCaiNhapHang[0]['purchase_code'])->toBe($phieu->code);
});

it('dòng sổ cái nhập hàng trỏ về phiếu không còn ở trạng thái đã nhận là mồ côi', function () {
    $phieu = taoPhieuNhapDaNhan($this->chuQuan);

    // Giả lập ai đó đưa phiếu về nháp sau khi đã ghi sổ cái.
    DB::table('purchases')->where('id', $phieu->id)->update(['status' => PurchaseStatus::Draft->value]);

    $ketQua = $this->action->handle();

    expect($ketQua->sach())->toBeFalse()
        ->and($ketQua->moCoiNhapHang)->toHaveCount(1)
        ->and($ketQua->moCoiNhapHang[0]['nguon'])->toBe(StockMovementRefType::PurchaseItem->value);
});

it('dòng nhập hàng mồ côi đã được xác nhận thì thôi tính vào số lỗi', function () {
    $phieu = taoPhieuNhapDaNhan($this->chuQuan);
    DB::table('purchases')->where('id', $phieu->id)->update(['status' => PurchaseStatus::Draft->value]);

    $moCoi = StockMovement::query()->where('ref_type', StockMovementRefType::PurchaseItem)->sole();

    $this->artisan('stock:ghi-chu-mo-coi', [
        'movement' => $moCoi->id,
        '--ghi-chu' => 'Đã đối chiếu hoá đơn nhà cung cấp',
        '--ly-do' => 'Phiếu bị mở lại để sửa ghi chú, hàng đã nhận thật',
    ])->assertSuccessful();

    $ketQua = $this->action->handle();

    expect($ketQua->sach())->toBeTrue()
        ->and($ketQua->moCoiNhapHang)->toBe([])
        ->and($ketQua->moCoiDaGhiChu)->toHaveCount(1);
});

// ── NHÁNH KIỂM KÊ (mục 7) ───────────────────────────────────────────────────

it('kiểm kê chốt đúng luồng thì nhánh kiểm kê sạch', function () {
    taoPhieuKiemKeDaChot($this->chuQuan);

    $ketQua = $this->action->handle();

    expect($ketQua->sach())->toBeTrue()
        ->and($ketQua->thieuSoCaiKiemKe)->toBe([])
        ->and($ketQua->moCoiKiemKe)->toBe([]);
});

it('BUG CŨ: dòng kiểm kê lệch đã chốt mà THIẾU sổ cái không ai phát hiện — nay báo đỏ', function () {
    $phieu = taoPhieuKiemKeDaChotNhungThieuSoCai($this->chuQuan);
    $dong = $phieu->items()->where('diff_qty', '<>', 0)->sole();

    $ketQua = $this->action->handle();

    expect($ketQua->sach())->toBeFalse()
        ->and($ketQua->thieuSoCaiKiemKe)->toHaveCount(1)
        ->and($ketQua->thieuSoCaiKiemKe[0]['stock_take_item_id'])->toBe($dong->id)
        ->and($ketQua->thieuSoCaiKiemKe[0]['diff_qty'])->toBe(-5);
});

it('dòng sổ cái kiểm kê trỏ về phiếu chưa chốt là mồ côi', function () {
    $phieu = taoPhieuKiemKeDaChot($this->chuQuan);

    DB::table('stock_takes')->where('id', $phieu->id)->update(['status' => StockTakeStatus::Open->value]);

    $ketQua = $this->action->handle();

    expect($ketQua->sach())->toBeFalse()
        ->and($ketQua->moCoiKiemKe)->toHaveCount(1)
        ->and($ketQua->moCoiKiemKe[0]['nguon'])->toBe(StockMovementRefType::StockTakeItem->value);
});

it('dòng kiểm kê khớp và dòng chưa đếm KHÔNG bị báo thiếu sổ cái — chúng không sinh sổ cái', function () {
    // Đếm đúng bằng tồn: diff_qty = 0, CloseStockTake cố ý không sinh dòng
    // sổ cái nào. Nếu đối soát không loại trừ thì đây là báo lệch giả.
    taoPhieuKiemKeDaChot($this->chuQuan, demDuoc: 100);

    $ketQua = $this->action->handle();

    expect($ketQua->sach())->toBeTrue()
        ->and($ketQua->thieuSoCaiKiemKe)->toBe([]);
});

// ── LỆNH ────────────────────────────────────────────────────────────────────

it('lệnh in đủ mục 6 và mục 7, và trả về mã lỗi khi một trong hai nhánh lệch', function () {
    $phieu = taoPhieuNhapDaNhan($this->chuQuan);

    $this->artisan('stock:doi-soat')
        ->expectsOutputToContain('6. Dòng phiếu nhập đã nhận')
        ->expectsOutputToContain('7. Dòng kiểm kê lệch đã chốt')
        ->assertSuccessful();

    DB::table('purchases')->where('id', $phieu->id)->update(['status' => PurchaseStatus::Draft->value]);

    $this->artisan('stock:doi-soat')
        ->expectsOutputToContain('MỒ CÔI')
        ->assertFailed();
});
