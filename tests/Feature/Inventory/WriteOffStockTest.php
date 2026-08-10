<?php

declare(strict_types=1);

use App\Domain\Inventory\Actions\RecordStockMovement;
use App\Domain\Inventory\Actions\WriteOffStock;
use App\Domain\Inventory\DTO\RecordStockMovementData;
use App\Domain\Inventory\DTO\WriteOffStockData;
use App\Domain\Inventory\Enums\StockMovementRefType;
use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Inventory\Enums\WasteReasonCategory;
use App\Domain\Inventory\Models\Ingredient;
use App\Domain\Inventory\Models\StockBalance;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Staffing\Models\User;
use App\Exceptions\DomainException;

beforeEach(function () {
    $this->action = new WriteOffStock(new RecordStockMovement);
    $this->ingredient = Ingredient::factory()->create();

    app(RecordStockMovement::class)->handle(new RecordStockMovementData(
        ingredientId: $this->ingredient->id,
        type: StockMovementType::Purchase,
        qtyDelta: 1_000,
        knownCost: 1_000_000,
        refType: StockMovementRefType::Manual,
        refId: null,
        reason: null,
        approvedByUserId: null,
        createdByUserId: User::factory()->owner()->create()->id,
        shiftId: null,
    ));
});

function ghiHaoHut(WriteOffStock $action, Ingredient $ingredient, User $user, WasteReasonCategory $category, string $detail, int $qty = 5): StockMovement
{
    return $action->handle(new WriteOffStockData(
        ingredientId: $ingredient->id,
        category: $category,
        detail: $detail,
        qty: $qty,
        createdByUserId: $user->id,
        shiftId: null,
    ));
}

it('ghi hao hụt loại VỠ/HỎNG trừ đúng số lượng, ghép loại vào lý do', function () {
    $thuNgan = User::factory()->cashier()->create();

    $movement = ghiHaoHut($this->action, $this->ingredient, $thuNgan, WasteReasonCategory::Broken, 'Rơi vỡ lúc bưng bê', 5);

    expect($movement->type)->toBe(StockMovementType::Waste)
        ->and($movement->qty_delta)->toBe(-5)
        ->and($movement->reason)->toBe('[Vỡ/hỏng] Rơi vỡ lúc bưng bê')
        ->and($movement->created_by_user_id)->toBe($thuNgan->id);

    expect(StockBalance::query()->find($this->ingredient->id)->qty)->toBe(995);
});

it('ghi hao hụt loại HẾT HẠN trừ đúng số lượng', function () {
    $chuQuan = User::factory()->owner()->create();

    $movement = ghiHaoHut($this->action, $this->ingredient, $chuQuan, WasteReasonCategory::Expired, 'Hết hạn sử dụng theo hoá đơn nhập', 10);

    expect($movement->reason)->toBe('[Hết hạn] Hết hạn sử dụng theo hoá đơn nhập');
    expect(StockBalance::query()->find($this->ingredient->id)->qty)->toBe(990);
});

it('ghi hao hụt loại HAO HỤT TỰ NHIÊN trừ đúng số lượng', function () {
    $chuQuan = User::factory()->owner()->create();

    $movement = ghiHaoHut($this->action, $this->ingredient, $chuQuan, WasteReasonCategory::NaturalLoss, 'Đá bào bay hơi trong ca', 3);

    expect($movement->reason)->toBe('[Hao hụt tự nhiên] Đá bào bay hơi trong ca');
    expect(StockBalance::query()->find($this->ingredient->id)->qty)->toBe(997);
});

it('ghi hao hụt loại DÙNG NỘI BỘ trừ đúng số lượng', function () {
    $chuQuan = User::factory()->owner()->create();

    $movement = ghiHaoHut($this->action, $this->ingredient, $chuQuan, WasteReasonCategory::InternalUse, 'Nhân viên dùng để pha thử món mới', 2);

    expect($movement->reason)->toBe('[Dùng nội bộ] Nhân viên dùng để pha thử món mới');
    expect(StockBalance::query()->find($this->ingredient->id)->qty)->toBe(998);
});

it('bắt buộc ghi rõ lý do, không cho ghi qua loa (chuỗi rỗng)', function () {
    $chuQuan = User::factory()->owner()->create();

    expect(fn () => ghiHaoHut($this->action, $this->ingredient, $chuQuan, WasteReasonCategory::Broken, '   '))
        ->toThrow(DomainException::class, 'Phải ghi rõ lý do hao hụt.');

    expect(StockMovement::query()->count())->toBe(1); // chỉ dòng nhập tồn đầu ở beforeEach
});

it('số lượng hao hụt phải lớn hơn 0', function () {
    $chuQuan = User::factory()->owner()->create();

    expect(fn () => ghiHaoHut($this->action, $this->ingredient, $chuQuan, WasteReasonCategory::Broken, 'Vỡ 1 ít', 0))
        ->toThrow(DomainException::class, 'Số lượng hao hụt phải lớn hơn 0.');
});

it('nhân viên phục vụ không có quyền ghi hao hụt', function () {
    $staff = User::factory()->staff()->create();

    expect(fn () => ghiHaoHut($this->action, $this->ingredient, $staff, WasteReasonCategory::Broken, 'Vỡ 1 ít'))
        ->toThrow(DomainException::class, 'Chỉ chủ quán hoặc thu ngân được ghi hao hụt.');
});

it('bếp không có quyền ghi hao hụt', function () {
    $bep = User::factory()->kitchen()->create();

    expect(fn () => ghiHaoHut($this->action, $this->ingredient, $bep, WasteReasonCategory::Broken, 'Vỡ 1 ít'))
        ->toThrow(DomainException::class, 'Chỉ chủ quán hoặc thu ngân được ghi hao hụt.');
});
