<?php

declare(strict_types=1);

use App\Domain\Inventory\Actions\AdjustStock;
use App\Domain\Inventory\Actions\RecordStockMovement;
use App\Domain\Inventory\DTO\AdjustStockData;
use App\Domain\Inventory\DTO\RecordStockMovementData;
use App\Domain\Inventory\Enums\StockMovementRefType;
use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Inventory\Models\Ingredient;
use App\Domain\Inventory\Models\StockBalance;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Staffing\Actions\VerifyApproverPin;
use App\Domain\Staffing\Models\User;
use App\Exceptions\DomainException;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->action = new AdjustStock(new VerifyApproverPin, new RecordStockMovement);
    $this->ingredient = Ingredient::factory()->create();
    $this->chuQuan = User::factory()->owner()->withPin('1234')->create();

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
});

function dieuChinhTon(AdjustStock $action, Ingredient $ingredient, User $chuQuan, User $nguoiDuyet, string $pin, int $qtyDelta, string $reason = 'Đếm lại thấy lệch so với sổ sách, kiểm tra kỹ rồi mới sửa', ?string $uuid = null): StockMovement
{
    return $action->handle(new AdjustStockData(
        uuid: $uuid ?? (string) Str::uuid(),
        ingredientId: $ingredient->id,
        qtyDelta: $qtyDelta,
        reason: $reason,
        requestedByUserId: $chuQuan->id,
        approverUserId: $nguoiDuyet->id,
        approverPin: $pin,
        shiftId: null,
    ));
}

it('chủ quán điều chỉnh giảm tồn, đúng PIN, ghi sổ cái loại adjust riêng', function () {
    $movement = dieuChinhTon($this->action, $this->ingredient, $this->chuQuan, $this->chuQuan, '1234', -50);

    expect($movement->type)->toBe(StockMovementType::Adjust)
        ->and($movement->qty_delta)->toBe(-50)
        ->and($movement->approved_by_user_id)->toBe($this->chuQuan->id)
        ->and($movement->created_by_user_id)->toBe($this->chuQuan->id);

    expect(StockBalance::query()->find($this->ingredient->id)->qty)->toBe(950);
    expect(StockMovement::query()->where('type', StockMovementType::Adjust)->count())->toBe(1);
});

it('chủ quán điều chỉnh tăng tồn, đúng PIN', function () {
    $movement = dieuChinhTon($this->action, $this->ingredient, $this->chuQuan, $this->chuQuan, '1234', 30);

    expect($movement->qty_delta)->toBe(30);
    expect(StockBalance::query()->find($this->ingredient->id)->qty)->toBe(1_030);
});

it('lý do quá ngắn (ghi qua loa) bị chặn, không tạo dòng sổ cái nào', function () {
    expect(fn () => dieuChinhTon($this->action, $this->ingredient, $this->chuQuan, $this->chuQuan, '1234', -10, 'Sai số'))
        ->toThrow(DomainException::class);

    expect(StockMovement::query()->where('type', StockMovementType::Adjust)->count())->toBe(0);
});

it('PIN sai thì không điều chỉnh được', function () {
    expect(fn () => dieuChinhTon($this->action, $this->ingredient, $this->chuQuan, $this->chuQuan, '9999', -10))
        ->toThrow(DomainException::class, 'Mã PIN không đúng.');

    expect(StockBalance::query()->find($this->ingredient->id)->qty)->toBe(1_000);
});

it('thu ngân không được điều chỉnh tồn kho tay dù có PIN đúng', function () {
    $thuNgan = User::factory()->cashier()->withPin('5678')->create();

    expect(fn () => dieuChinhTon($this->action, $this->ingredient, $thuNgan, $thuNgan, '5678', -10))
        ->toThrow(DomainException::class, 'Chỉ chủ quán được điều chỉnh tồn kho tay.');
});

it('người duyệt PIN phải là chủ quán, thu ngân duyệt hộ cũng bị chặn', function () {
    $thuNgan = User::factory()->cashier()->withPin('5678')->create();

    expect(fn () => dieuChinhTon($this->action, $this->ingredient, $this->chuQuan, $thuNgan, '5678', -10))
        ->toThrow(DomainException::class, 'Chỉ chủ quán được duyệt điều chỉnh tồn kho tay.');
});

it('số lượng điều chỉnh bằng 0 bị chặn', function () {
    expect(fn () => dieuChinhTon($this->action, $this->ingredient, $this->chuQuan, $this->chuQuan, '1234', 0))
        ->toThrow(DomainException::class, 'Số lượng điều chỉnh không được bằng 0.');
});

// ── CHỐNG GHI TRÙNG THEO MÃ VÂN TAY (Bước 10) ────────────────────────────

it('điều chỉnh hai lần cùng mã vân tay chỉ ghi một dòng sổ cái, tồn chỉ đổi một lần', function () {
    $vanTay = (string) Str::uuid();

    $lanDau = dieuChinhTon($this->action, $this->ingredient, $this->chuQuan, $this->chuQuan, '1234', -50, uuid: $vanTay);
    $lanHai = dieuChinhTon($this->action, $this->ingredient, $this->chuQuan, $this->chuQuan, '1234', -50, uuid: $vanTay);

    expect($lanHai->id)->toBe($lanDau->id);

    expect(StockMovement::query()->where('type', StockMovementType::Adjust)->count())->toBe(1);
    expect(StockBalance::query()->find($this->ingredient->id)->qty)->toBe(950);
});

it('hai mã vân tay khác nhau thì ghi thành hai dòng điều chỉnh riêng', function () {
    dieuChinhTon($this->action, $this->ingredient, $this->chuQuan, $this->chuQuan, '1234', -50);
    dieuChinhTon($this->action, $this->ingredient, $this->chuQuan, $this->chuQuan, '1234', -50);

    expect(StockMovement::query()->where('type', StockMovementType::Adjust)->count())->toBe(2);
    expect(StockBalance::query()->find($this->ingredient->id)->qty)->toBe(900);
});

it('bấm lại lần hai không đẻ thêm dòng nhật ký thử PIN', function () {
    $vanTay = (string) Str::uuid();

    $lanDau = dieuChinhTon($this->action, $this->ingredient, $this->chuQuan, $this->chuQuan, '1234', -50, uuid: $vanTay);

    // Cố tình gửi lại kèm PIN SAI. Nếu Action không tra mã vân tay TRƯỚC bước
    // hỏi PIN, lần bấm lại này sẽ nổ lỗi "Mã PIN không đúng" và ghi một dòng
    // 'pin-verify' vào nhật ký — trong khi thực tế chẳng có gì được ghi thêm
    // vào kho cả.
    $lanHai = dieuChinhTon($this->action, $this->ingredient, $this->chuQuan, $this->chuQuan, '9999', -50, uuid: $vanTay);

    expect($lanHai->id)->toBe($lanDau->id);
    expect(StockMovement::query()->where('type', StockMovementType::Adjust)->count())->toBe(1);
    expect(Activity::query()->where('log_name', 'pin-verify')->count())->toBe(0);
});
