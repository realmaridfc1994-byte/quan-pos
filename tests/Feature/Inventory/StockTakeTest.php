<?php

declare(strict_types=1);

use App\Domain\Inventory\Actions\CloseStockTake;
use App\Domain\Inventory\Actions\OpenStockTake;
use App\Domain\Inventory\Actions\RecordStockMovement;
use App\Domain\Inventory\Actions\RecordStockTakeCount;
use App\Domain\Inventory\DTO\CloseStockTakeData;
use App\Domain\Inventory\DTO\OpenStockTakeData;
use App\Domain\Inventory\DTO\RecordStockMovementData;
use App\Domain\Inventory\DTO\RecordStockTakeCountData;
use App\Domain\Inventory\Enums\StockMovementRefType;
use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Inventory\Enums\StockTakeStatus;
use App\Domain\Inventory\Models\Ingredient;
use App\Domain\Inventory\Models\StockBalance;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Inventory\Models\StockTake;
use App\Domain\Ordering\Enums\TableSessionStatus;
use App\Domain\Ordering\Models\TableSession;
use App\Domain\Staffing\Models\Shift;
use App\Domain\Staffing\Models\User;
use App\Exceptions\DomainException;
use App\Filament\Resources\StockTakeResource\Pages\ManageStockTakes;
use App\Filament\Resources\StockTakeResource\Pages\ViewStockTake;
use Livewire\Livewire;

beforeEach(function () {
    $this->chuQuan = User::factory()->owner()->create();
    $this->openAction = new OpenStockTake;
    $this->countAction = new RecordStockTakeCount;
    $this->closeAction = new CloseStockTake(new RecordStockMovement);
});

function nhapTonDauStockTake(Ingredient $ingredient, int $qty, int $cost, User $user): void
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

it('kiểm kê thiếu 5 lon sinh đúng một dòng sổ cái điều chỉnh -5', function () {
    $bia = Ingredient::factory()->create();
    nhapTonDauStockTake($bia, 100, 2_000_000, $this->chuQuan);

    $phieu = $this->openAction->handle(new OpenStockTakeData(note: null, openedByUserId: $this->chuQuan->id));
    $dong = $phieu->items()->where('ingredient_id', $bia->id)->sole();
    expect($dong->system_qty)->toBe(100);

    $this->countAction->handle(new RecordStockTakeCountData($dong->id, 95));
    $phieuDaChot = $this->closeAction->handle(new CloseStockTakeData($phieu->id, $this->chuQuan->id));

    $dieuChinh = StockMovement::query()->where('ref_type', StockMovementRefType::StockTakeItem)->where('ref_id', $dong->id)->sole();
    expect($dieuChinh->type)->toBe(StockMovementType::Stocktake)
        ->and($dieuChinh->qty_delta)->toBe(-5);

    expect(StockBalance::query()->find($bia->id)->qty)->toBe(95);
    expect($phieuDaChot->status)->toBe(StockTakeStatus::Closed)
        ->and($phieuDaChot->total_diff_cost)->not->toBeNull();
});

it('kiểm kê thừa sinh dòng sổ cái điều chỉnh dương', function () {
    $bia = Ingredient::factory()->create();
    nhapTonDauStockTake($bia, 100, 2_000_000, $this->chuQuan);

    $phieu = $this->openAction->handle(new OpenStockTakeData(note: null, openedByUserId: $this->chuQuan->id));
    $dong = $phieu->items()->where('ingredient_id', $bia->id)->sole();

    $this->countAction->handle(new RecordStockTakeCountData($dong->id, 108));
    $this->closeAction->handle(new CloseStockTakeData($phieu->id, $this->chuQuan->id));

    $dieuChinh = StockMovement::query()->where('ref_id', $dong->id)->sole();
    expect($dieuChinh->qty_delta)->toBe(8);
    expect(StockBalance::query()->find($bia->id)->qty)->toBe(108);
});

it('kiểm kê khớp không sinh dòng sổ cái nào', function () {
    $bia = Ingredient::factory()->create();
    nhapTonDauStockTake($bia, 100, 2_000_000, $this->chuQuan);

    $phieu = $this->openAction->handle(new OpenStockTakeData(note: null, openedByUserId: $this->chuQuan->id));
    $dong = $phieu->items()->where('ingredient_id', $bia->id)->sole();

    $this->countAction->handle(new RecordStockTakeCountData($dong->id, 100));
    $this->closeAction->handle(new CloseStockTakeData($phieu->id, $this->chuQuan->id));

    expect(StockMovement::query()->where('ref_id', $dong->id)->count())->toBe(0);
    expect(StockBalance::query()->find($bia->id)->qty)->toBe(100);
});

it('dòng chưa đếm khi chốt phiếu thì không sinh dòng sổ cái nào', function () {
    $bia = Ingredient::factory()->create();
    nhapTonDauStockTake($bia, 100, 2_000_000, $this->chuQuan);

    $phieu = $this->openAction->handle(new OpenStockTakeData(note: null, openedByUserId: $this->chuQuan->id));
    $dong = $phieu->items()->where('ingredient_id', $bia->id)->sole();

    $this->closeAction->handle(new CloseStockTakeData($phieu->id, $this->chuQuan->id));

    expect(StockMovement::query()->where('ref_id', $dong->id)->count())->toBe(0);
    expect(StockBalance::query()->find($bia->id)->qty)->toBe(100);
});

it('chốt phiếu hai lần chỉ sinh điều chỉnh một lần', function () {
    $bia = Ingredient::factory()->create();
    nhapTonDauStockTake($bia, 100, 2_000_000, $this->chuQuan);

    $phieu = $this->openAction->handle(new OpenStockTakeData(note: null, openedByUserId: $this->chuQuan->id));
    $dong = $phieu->items()->where('ingredient_id', $bia->id)->sole();
    $this->countAction->handle(new RecordStockTakeCountData($dong->id, 95));

    $this->closeAction->handle(new CloseStockTakeData($phieu->id, $this->chuQuan->id));

    expect(fn () => $this->closeAction->handle(new CloseStockTakeData($phieu->id, $this->chuQuan->id)))
        ->toThrow(DomainException::class);

    expect(StockMovement::query()->where('ref_id', $dong->id)->count())->toBe(1);
    expect(StockBalance::query()->find($bia->id)->qty)->toBe(95);
});

it('sửa số đếm sau khi phiếu đã chốt thì bị chặn', function () {
    $bia = Ingredient::factory()->create();
    nhapTonDauStockTake($bia, 100, 2_000_000, $this->chuQuan);

    $phieu = $this->openAction->handle(new OpenStockTakeData(note: null, openedByUserId: $this->chuQuan->id));
    $dong = $phieu->items()->where('ingredient_id', $bia->id)->sole();
    $this->countAction->handle(new RecordStockTakeCountData($dong->id, 95));
    $this->closeAction->handle(new CloseStockTakeData($phieu->id, $this->chuQuan->id));

    expect(fn () => $this->countAction->handle(new RecordStockTakeCountData($dong->id, 50)))
        ->toThrow(DomainException::class, 'Phiếu kiểm kê này đã chốt hoặc đã huỷ, không sửa số đếm được nữa.');

    expect($dong->refresh()->counted_qty)->toBe(95);
});

it('còn bàn đang mở thì không mở được phiếu kiểm kê', function () {
    TableSession::factory()->create(['status' => TableSessionStatus::Open]);

    expect(fn () => $this->openAction->handle(new OpenStockTakeData(note: null, openedByUserId: $this->chuQuan->id)))
        ->toThrow(DomainException::class);

    expect(StockTake::query()->count())->toBe(0);
});

it('còn bàn đang billing (chưa thu xong tiền) cũng không mở được phiếu kiểm kê', function () {
    TableSession::factory()->create(['status' => TableSessionStatus::Billing]);

    expect(fn () => $this->openAction->handle(new OpenStockTakeData(note: null, openedByUserId: $this->chuQuan->id)))
        ->toThrow(DomainException::class);
});

it('bàn đã đóng hoặc đã huỷ thì không chặn mở phiếu kiểm kê', function () {
    $ca = Shift::factory()->open()->create();
    TableSession::factory()->for($ca, 'shift')->closed()->create();
    TableSession::factory()->for($ca, 'shift')->create([
        'status' => TableSessionStatus::Void,
        'voided_at' => now(),
        'void_reason' => 'Dọn dữ liệu diễn tập',
    ]);

    $phieu = $this->openAction->handle(new OpenStockTakeData(note: null, openedByUserId: $this->chuQuan->id));

    expect($phieu->status)->toBe(StockTakeStatus::Open);
});

it('đã có phiếu kiểm kê đang mở thì không mở thêm phiếu mới', function () {
    $this->openAction->handle(new OpenStockTakeData(note: null, openedByUserId: $this->chuQuan->id));

    expect(fn () => $this->openAction->handle(new OpenStockTakeData(note: null, openedByUserId: $this->chuQuan->id)))
        ->toThrow(DomainException::class, 'Đã có một phiếu kiểm kê đang mở — phải chốt phiếu đó trước khi mở phiếu mới.');

    expect(StockTake::query()->count())->toBe(1);
});

/**
 * K18 — KHO PHẢI ĐỨNG YÊN TRONG LÚC PHIẾU KIỂM KÊ ĐANG MỞ.
 *
 * Test này TRƯỚC ĐÂY khẳng định điều ngược lại: "kho biến động sau khi mở phiếu
 * cũng không làm đổi số chụp". Đúng về mặt cột dữ liệu, nhưng nó hợp thức hoá
 * đúng cái tình huống sinh ra lỗi cộng hai lần (xem review Phase 3 Bước 10):
 * tồn 100 → mở phiếu → nhập 20 (tồn 120) → nhân viên đếm tay thấy 120 → chốt
 * phiếu cộng thêm (120 − 100) = 20 nữa → hệ thống ghi 140.
 *
 * Giờ số chụp vẫn đóng băng, nhưng kho không còn nhúc nhích được nữa.
 */
it('phiếu kiểm kê đang mở thì không ai nhập hàng, ghi hao hụt hay điều chỉnh được', function () {
    $bia = Ingredient::factory()->create();
    nhapTonDauStockTake($bia, 100, 2_000_000, $this->chuQuan);

    $phieu = $this->openAction->handle(new OpenStockTakeData(note: null, openedByUserId: $this->chuQuan->id));
    $dong = $phieu->items()->where('ingredient_id', $bia->id)->sole();
    expect($dong->system_qty)->toBe(100);

    expect(fn () => nhapTonDauStockTake($bia, 20, 400_000, $this->chuQuan))
        ->toThrow(DomainException::class, 'Đang có phiếu kiểm kê mở');

    // Kho không đổi một chút nào, và số chụp vẫn là số chụp.
    expect(StockBalance::query()->find($bia->id)->qty)->toBe(100)
        ->and($dong->refresh()->system_qty)->toBe(100);
});

it('nhập hàng giữa lúc kiểm kê KHÔNG còn làm chốt phiếu cộng hai lần', function () {
    $bia = Ingredient::factory()->create();
    nhapTonDauStockTake($bia, 100, 2_000_000, $this->chuQuan);

    $phieu = $this->openAction->handle(new OpenStockTakeData(note: null, openedByUserId: $this->chuQuan->id));
    $dong = $phieu->items()->where('ingredient_id', $bia->id)->sole();

    // Xe hàng tới giữa lúc đang đếm: bị chặn, phải chờ chốt phiếu xong.
    expect(fn () => nhapTonDauStockTake($bia, 20, 400_000, $this->chuQuan))
        ->toThrow(DomainException::class);

    // Nhân viên đếm tay, thấy đúng 100 lon vì hàng chưa được nhập.
    $this->countAction->handle(new RecordStockTakeCountData($dong->id, 100));
    $this->closeAction->handle(new CloseStockTakeData(stockTakeId: $phieu->id, closedByUserId: $this->chuQuan->id));

    // Đếm bao nhiêu thì hệ thống ghi đúng bấy nhiêu — không cộng thêm lần nào.
    expect(StockBalance::query()->find($bia->id)->qty)->toBe(100);

    // Chốt phiếu xong, kho mở lại bình thường.
    nhapTonDauStockTake($bia, 20, 400_000, $this->chuQuan);
    expect(StockBalance::query()->find($bia->id)->qty)->toBe(120);
});

it('bán món KHÔNG bao giờ bị chặn dù phiếu kiểm kê đang mở — bếp không bị đứng', function () {
    $bia = Ingredient::factory()->create();
    nhapTonDauStockTake($bia, 100, 2_000_000, $this->chuQuan);

    $this->openAction->handle(new OpenStockTakeData(note: null, openedByUserId: $this->chuQuan->id));

    app(RecordStockMovement::class)->handle(new RecordStockMovementData(
        uuid: (string) Str::uuid(),
        ingredientId: $bia->id,
        type: StockMovementType::Sale,
        qtyDelta: -3,
        knownCost: null,
        refType: StockMovementRefType::Manual,
        refId: null,
        reason: null,
        approvedByUserId: null,
        createdByUserId: $this->chuQuan->id,
        shiftId: null,
    ));

    expect(StockBalance::query()->find($bia->id)->qty)->toBe(97);
});

it('màn hình danh sách kiểm kê mở được và mở phiếu qua nút, không lỗi', function () {
    $this->actingAs($this->chuQuan);

    Livewire::test(ManageStockTakes::class)
        ->assertSuccessful()
        ->callAction('mo-phieu', data: ['note' => 'Kiểm kê cuối tháng']);

    expect(StockTake::query()->sole()->note)->toBe('Kiểm kê cuối tháng');
});

it('màn hình đếm hiện đúng dòng nguyên liệu và chốt phiếu qua nút không lỗi', function () {
    $this->actingAs($this->chuQuan);
    $bia = Ingredient::factory()->create();
    nhapTonDauStockTake($bia, 100, 2_000_000, $this->chuQuan);

    $phieu = $this->openAction->handle(new OpenStockTakeData(note: null, openedByUserId: $this->chuQuan->id));

    Livewire::test(ViewStockTake::class, ['record' => $phieu->id])
        ->assertSuccessful()
        ->assertActionExists('chot-phieu')
        ->callAction('chot-phieu');

    expect($phieu->refresh()->status)->toBe(StockTakeStatus::Closed);
});

it('số đếm âm bị chặn', function () {
    $bia = Ingredient::factory()->create();
    nhapTonDauStockTake($bia, 100, 2_000_000, $this->chuQuan);

    $phieu = $this->openAction->handle(new OpenStockTakeData(note: null, openedByUserId: $this->chuQuan->id));
    $dong = $phieu->items()->where('ingredient_id', $bia->id)->sole();

    expect(fn () => $this->countAction->handle(new RecordStockTakeCountData($dong->id, -1)))
        ->toThrow(DomainException::class, 'Số đếm không được âm.');
});
