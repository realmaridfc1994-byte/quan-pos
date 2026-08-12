<?php

declare(strict_types=1);

use App\Domain\Inventory\Actions\RecordStockMovement;
use App\Domain\Inventory\DTO\RecordStockMovementData;
use App\Domain\Inventory\Enums\StockMovementRefType;
use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Inventory\Enums\WasteReasonCategory;
use App\Domain\Inventory\Models\Ingredient;
use App\Domain\Inventory\Models\StockBalance;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Staffing\Models\User;
use App\Filament\Resources\WasteRecordResource\Pages\ManageWasteRecords;
use Illuminate\Support\Str;
use Livewire\Livewire;

/**
 * Chống bấm Lưu hai lần Ở ĐÚNG TẦNG MÀN HÌNH (Bước 10).
 *
 * Lớp mã vân tay ở tầng Action chỉ có tác dụng NẾU màn hình gửi CÙNG MỘT mã
 * cho cả hai lần bấm. Bộ test này chứng minh đúng điều đó, không suy đoán:
 *   1. Mã sinh MỘT LẦN lúc mở form, nằm trong trạng thái của form.
 *   2. Hai lần gửi từ cùng một lần mở form mang cùng mã → một dòng sổ cái.
 *   3. Hai lần MỞ FORM riêng biệt ra hai mã khác nhau → hai dòng, đúng như
 *      hai lô hàng vỡ thật.
 */
beforeEach(function () {
    $this->chuQuan = User::factory()->owner()->create();
    $this->ingredient = Ingredient::factory()->create();

    app(RecordStockMovement::class)->handle(new RecordStockMovementData(
        uuid: (string) Str::uuid(),
        ingredientId: $this->ingredient->id,
        type: StockMovementType::Purchase,
        qtyDelta: 1_000,
        knownCost: 1_000_000,
        refType: StockMovementRefType::Manual,
        refId: null,
        reason: null,
        approvedByUserId: null,
        createdByUserId: $this->chuQuan->id,
        shiftId: null,
    ));

    $this->actingAs($this->chuQuan);
});

/** @return array<string, mixed> */
function duLieuHaoHut(Ingredient $ingredient, array $them = []): array
{
    return array_merge([
        'ingredient_id' => $ingredient->id,
        'category' => WasteReasonCategory::Broken->value,
        'qty' => 5,
        'detail' => 'Rơi vỡ lúc bưng bê',
    ], $them);
}

it('mã vân tay được sinh ngay lúc MỞ form, không phải lúc bấm Lưu', function () {
    $manHinh = Livewire::test(ManageWasteRecords::class)->mountAction('create');

    $uuid = $manHinh->get('mountedActionsData')[0]['uuid'];

    expect($uuid)->toBeString()
        ->and($uuid)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/');

    // Điền dữ liệu KHÔNG được làm đổi mã — nếu mã sinh lại mỗi lần form cập
    // nhật thì hai lần bấm Lưu sẽ mang hai mã khác nhau và lớp chặn vô dụng.
    $manHinh->setActionData(duLieuHaoHut($this->ingredient));

    expect($manHinh->get('mountedActionsData')[0]['uuid'])->toBe($uuid);
});

it('gửi hai lần từ cùng một lần mở form chỉ ghi MỘT dòng, tồn chỉ giảm một lần', function () {
    $manHinh = Livewire::test(ManageWasteRecords::class)->mountAction('create');
    $uuid = $manHinh->get('mountedActionsData')[0]['uuid'];

    $manHinh->setActionData(duLieuHaoHut($this->ingredient))->callMountedAction();

    // Lần bấm thứ hai của cùng một lần mở form: trình duyệt gửi lại y nguyên
    // trạng thái form đang giữ, tức là CÙNG mã vân tay đó.
    Livewire::test(ManageWasteRecords::class)
        ->mountAction('create')
        ->setActionData(duLieuHaoHut($this->ingredient, ['uuid' => $uuid]))
        ->callMountedAction();

    expect(StockMovement::query()->where('type', StockMovementType::Waste)->count())->toBe(1);
    expect(StockBalance::query()->find($this->ingredient->id)->qty)->toBe(995);
});

it('mở form hai lần riêng biệt, ghi hai lô hao hụt thật thì ra HAI dòng', function () {
    Livewire::test(ManageWasteRecords::class)
        ->mountAction('create')
        ->setActionData(duLieuHaoHut($this->ingredient))
        ->callMountedAction();

    Livewire::test(ManageWasteRecords::class)
        ->mountAction('create')
        ->setActionData(duLieuHaoHut($this->ingredient))
        ->callMountedAction();

    expect(StockMovement::query()->where('type', StockMovementType::Waste)->count())->toBe(2);
    expect(StockBalance::query()->find($this->ingredient->id)->qty)->toBe(990);
});

it('mỗi lần mở form ra một mã vân tay khác nhau — lưu xong là có mã mới cho lô sau', function () {
    $lanMoDau = Livewire::test(ManageWasteRecords::class)->mountAction('create');
    $uuidDau = $lanMoDau->get('mountedActionsData')[0]['uuid'];

    $lanMoDau->setActionData(duLieuHaoHut($this->ingredient))->callMountedAction();

    $uuidSau = Livewire::test(ManageWasteRecords::class)
        ->mountAction('create')
        ->get('mountedActionsData')[0]['uuid'];

    expect($uuidSau)->not->toBe($uuidDau);
});

it('bấm Lưu lần nữa sau khi đã lưu xong không ghi thêm gì — Filament đã đóng form', function () {
    $manHinh = Livewire::test(ManageWasteRecords::class)
        ->mountAction('create')
        ->setActionData(duLieuHaoHut($this->ingredient));

    $manHinh->callMountedAction();

    expect($manHinh->get('mountedActionsData'))->toBe([]);

    $manHinh->callMountedAction();

    expect(StockMovement::query()->where('type', StockMovementType::Waste)->count())->toBe(1);
});
