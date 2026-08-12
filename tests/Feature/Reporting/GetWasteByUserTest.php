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
use App\Domain\Reporting\Queries\GetWasteByUser;
use App\Domain\Staffing\Actions\VerifyApproverPin;
use App\Domain\Staffing\Models\User;
use App\Support\CauHinhQuan;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->action = new WriteOffStock(new RecordStockMovement, new VerifyApproverPin, new CauHinhQuan);
    $this->ingredient = Ingredient::factory()->create();
    $this->chuQuan = User::factory()->owner()->withPin('1234')->create();

    // Giá vốn trung bình đúng 1.000đ/đơn vị cho dễ đối chiếu bằng mắt.
    app(RecordStockMovement::class)->handle(new RecordStockMovementData(
        uuid: (string) Str::uuid(),
        ingredientId: $this->ingredient->id,
        type: StockMovementType::Purchase,
        qtyDelta: 10_000,
        knownCost: 10_000_000,
        refType: StockMovementRefType::Manual,
        refId: null,
        reason: null,
        approvedByUserId: null,
        createdByUserId: $this->chuQuan->id,
        shiftId: null,
    ));
});

function ghiHaoHutCho(WriteOffStock $action, Ingredient $ingredient, User $user, int $qty): void
{
    $action->handle(new WriteOffStockData(
        uuid: (string) Str::uuid(),
        ingredientId: $ingredient->id,
        category: WasteReasonCategory::Broken,
        detail: 'Vỡ lúc bưng bê',
        qty: $qty,
        createdByUserId: $user->id,
        shiftId: null,
    ));
}

it('gộp đúng số lần và tổng giá trị hao hụt của từng người trong tháng', function () {
    $thuNganA = User::factory()->cashier()->create(['name' => 'Thu ngân A']);
    $thuNganB = User::factory()->cashier()->create(['name' => 'Thu ngân B']);

    // A ghi ba lần nhỏ — kiểu không bao giờ chạm ngưỡng PIN.
    ghiHaoHutCho($this->action, $this->ingredient, $thuNganA, 50);
    ghiHaoHutCho($this->action, $this->ingredient, $thuNganA, 50);
    ghiHaoHutCho($this->action, $this->ingredient, $thuNganA, 50);

    // B ghi đúng một lần, nhỏ hơn tổng của A.
    ghiHaoHutCho($this->action, $this->ingredient, $thuNganB, 100);

    $ketQua = app(GetWasteByUser::class)->handle();

    expect($ketQua)->toHaveCount(2);

    // Sắp theo tổng giá trị giảm dần — A đứng trước dù mỗi lần đều nhỏ.
    expect($ketQua[0]['user_id'])->toBe($thuNganA->id)
        ->and($ketQua[0]['user_name'])->toBe('Thu ngân A')
        ->and($ketQua[0]['so_lan'])->toBe(3)
        ->and($ketQua[0]['tong_qty'])->toBe(150)
        ->and($ketQua[0]['tong_cost'])->toBe(150_000);

    expect($ketQua[1]['user_id'])->toBe($thuNganB->id)
        ->and($ketQua[1]['so_lan'])->toBe(1)
        ->and($ketQua[1]['tong_qty'])->toBe(100)
        ->and($ketQua[1]['tong_cost'])->toBe(100_000);
});

it('hao hụt tháng khác không lẫn vào tháng đang xem', function () {
    $thuNgan = User::factory()->cashier()->create();

    Carbon::setTestNow(Carbon::create(2026, 7, 15, 20));
    ghiHaoHutCho($this->action, $this->ingredient, $thuNgan, 150);

    Carbon::setTestNow(Carbon::create(2026, 8, 15, 20));
    ghiHaoHutCho($this->action, $this->ingredient, $thuNgan, 100);

    $thangTam = app(GetWasteByUser::class)->handle(Carbon::create(2026, 8, 1));
    expect($thangTam)->toHaveCount(1)
        ->and($thangTam[0]['so_lan'])->toBe(1)
        ->and($thangTam[0]['tong_cost'])->toBe(100_000);

    $thangBay = app(GetWasteByUser::class)->handle(Carbon::create(2026, 7, 1));
    expect($thangBay)->toHaveCount(1)
        ->and($thangBay[0]['so_lan'])->toBe(1)
        ->and($thangBay[0]['tong_cost'])->toBe(150_000);

    Carbon::setTestNow();
});

it('chỉ đếm dòng hao hụt, không đếm nhập hàng hay điều chỉnh', function () {
    $thuNgan = User::factory()->cashier()->create();
    ghiHaoHutCho($this->action, $this->ingredient, $thuNgan, 50);

    $ketQua = app(GetWasteByUser::class)->handle();

    // beforeEach đã ghi một dòng purchase do chủ quán tạo — không được lẫn vào.
    expect($ketQua)->toHaveCount(1)
        ->and($ketQua[0]['user_id'])->toBe($thuNgan->id)
        ->and($ketQua[0]['so_lan'])->toBe(1);
});

it('tháng chưa ai ghi hao hụt thì trả về danh sách rỗng, không lỗi', function () {
    expect(app(GetWasteByUser::class)->handle())->toBe([]);
});
