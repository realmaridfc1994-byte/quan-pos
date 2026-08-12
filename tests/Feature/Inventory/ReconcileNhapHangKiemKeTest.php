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
 * Giả lập "sổ cái mất một dòng" — thứ đối soát sinh ra để bắt.
 *
 * Phải đi thẳng bằng SQL vì tầng Model đã khoá cứng không cho xoá sổ cái, và
 * phải gỡ tham chiếu stock_balances.last_movement_id trước, không thì khoá
 * ngoại chặn. Đây là dựng hiện trường trong test, KHÔNG phải cách làm được
 * phép ở tầng sản xuất.
 */
function xoaDongSoCaiBangSql(StockMovementRefType $refType, int $refId): void
{
    $ids = DB::table('stock_movements')
        ->where('ref_type', $refType->value)
        ->where('ref_id', $refId)
        ->pluck('id');

    DB::table('stock_balances')->whereIn('last_movement_id', $ids)->update(['last_movement_id' => null]);
    DB::table('stock_movements')->whereIn('id', $ids)->delete();
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
    $phieu = taoPhieuNhapDaNhan($this->chuQuan);

    $dong = $phieu->items()->sole();
    xoaDongSoCaiBangSql(StockMovementRefType::PurchaseItem, $dong->id);

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
    $phieu = taoPhieuKiemKeDaChot($this->chuQuan);
    $dong = $phieu->items()->where('diff_qty', '<>', 0)->sole();

    xoaDongSoCaiBangSql(StockMovementRefType::StockTakeItem, $dong->id);

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
