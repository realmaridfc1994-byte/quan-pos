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
use App\Domain\Reporting\Actions\SummarizeIngredientWasteMonthly;
use App\Domain\Reporting\Models\IngredientWasteMonthly;
use App\Domain\Staffing\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->chuQuan = User::factory()->owner()->create();
});

function nhapTonDauHaoHut(Ingredient $ingredient, int $qty, int $cost, User $user): void
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

function ghiHaoHutVaoNgay(Ingredient $ingredient, int $qty, User $user, Carbon $ngay): void
{
    $movement = app(WriteOffStock::class)->handle(new WriteOffStockData(
        uuid: (string) Str::uuid(),
        ingredientId: $ingredient->id,
        category: WasteReasonCategory::Broken,
        detail: 'Vỡ khi bưng bê',
        qty: $qty,
        createdByUserId: $user->id,
        shiftId: null,
    ));

    // WriteOffStock luôn ghi occurred_at = now() — dựng lại đúng ngày cần test
    // bằng cách cập nhật trực tiếp cột occurred_at (không đi qua Action, vì
    // đây là bước dàn dựng dữ liệu quá khứ cho test, không phải nghiệp vụ).
    $movement->occurred_at = $ngay;
    $movement->saveQuietly();
}

it('tổng hợp đúng tổng số lượng và giá trị hao hụt trong tháng', function () {
    $ga = Ingredient::factory()->create();
    nhapTonDauHaoHut($ga, 1_000, 1_000_000, $this->chuQuan);

    $thang = Carbon::parse('2026-08-15');
    ghiHaoHutVaoNgay($ga, 5, $this->chuQuan, $thang->clone()->setDay(3));
    ghiHaoHutVaoNgay($ga, 3, $this->chuQuan, $thang->clone()->setDay(20));

    app(SummarizeIngredientWasteMonthly::class)->handle($thang->toDateString());

    $dong = IngredientWasteMonthly::query()->where('month', $thang->clone()->startOfMonth()->toDateString())->sole();
    expect($dong->ingredient_id)->toBe($ga->id)
        ->and($dong->waste_qty)->toBe(8)
        ->and($dong->waste_cost)->toBe(8_000);
});

it('hao hụt tháng khác không lẫn vào tháng đang tổng hợp', function () {
    $ga = Ingredient::factory()->create();
    nhapTonDauHaoHut($ga, 1_000, 1_000_000, $this->chuQuan);

    ghiHaoHutVaoNgay($ga, 5, $this->chuQuan, Carbon::parse('2026-07-28'));
    ghiHaoHutVaoNgay($ga, 2, $this->chuQuan, Carbon::parse('2026-08-05'));

    app(SummarizeIngredientWasteMonthly::class)->handle('2026-08-15');

    $dongThang8 = IngredientWasteMonthly::query()->where('month', '2026-08-01')->sole();
    expect($dongThang8->waste_qty)->toBe(2);

    expect(IngredientWasteMonthly::query()->where('month', '2026-07-01')->exists())->toBeFalse();
});

it('chạy lại cho cùng tháng thì ghi đè, không cộng dồn', function () {
    $ga = Ingredient::factory()->create();
    nhapTonDauHaoHut($ga, 1_000, 1_000_000, $this->chuQuan);
    ghiHaoHutVaoNgay($ga, 4, $this->chuQuan, Carbon::parse('2026-08-10'));

    app(SummarizeIngredientWasteMonthly::class)->handle('2026-08-01');
    app(SummarizeIngredientWasteMonthly::class)->handle('2026-08-20');

    expect(IngredientWasteMonthly::query()->where('month', '2026-08-01')->count())->toBe(1);
    expect(IngredientWasteMonthly::query()->where('month', '2026-08-01')->sole()->waste_qty)->toBe(4);
});

it('nguyên liệu không hao hụt gì trong tháng thì không xuất hiện trong bảng', function () {
    $ga = Ingredient::factory()->create();
    $sa = Ingredient::factory()->create();
    nhapTonDauHaoHut($ga, 1_000, 1_000_000, $this->chuQuan);
    nhapTonDauHaoHut($sa, 1_000, 100_000, $this->chuQuan);

    ghiHaoHutVaoNgay($ga, 1, $this->chuQuan, Carbon::parse('2026-08-10'));

    app(SummarizeIngredientWasteMonthly::class)->handle('2026-08-10');

    expect(IngredientWasteMonthly::query()->where('ingredient_id', $sa->id)->exists())->toBeFalse();
});
