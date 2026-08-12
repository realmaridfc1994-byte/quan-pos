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
use App\Domain\Staffing\Actions\VerifyApproverPin;
use App\Domain\Staffing\Models\User;
use App\Exceptions\ApprovalPinRequiredException;
use App\Exceptions\DomainException;
use App\Support\CauHinhQuan;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->action = new WriteOffStock(new RecordStockMovement, new VerifyApproverPin, new CauHinhQuan);
    $this->ingredient = Ingredient::factory()->create();
    $this->chuQuan = User::factory()->owner()->withPin('1234')->create();

    // Tồn đầu 1.000 đơn vị trị giá 1.000.000đ → giá vốn trung bình đúng
    // 1.000đ/đơn vị, nên "số lượng hao hụt" nhân 1.000 ra thẳng số tiền.
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

function ghiHaoHut(
    WriteOffStock $action,
    Ingredient $ingredient,
    User $user,
    WasteReasonCategory $category,
    string $detail,
    int $qty = 5,
    ?int $approverUserId = null,
    ?string $approverPin = null,
    ?string $uuid = null,
): StockMovement {
    return $action->handle(new WriteOffStockData(
        uuid: $uuid ?? (string) Str::uuid(),
        ingredientId: $ingredient->id,
        category: $category,
        detail: $detail,
        qty: $qty,
        createdByUserId: $user->id,
        shiftId: null,
        approverUserId: $approverUserId,
        approverPin: $approverPin,
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
    $movement = ghiHaoHut($this->action, $this->ingredient, $this->chuQuan, WasteReasonCategory::Expired, 'Hết hạn sử dụng theo hoá đơn nhập', 10);

    expect($movement->reason)->toBe('[Hết hạn] Hết hạn sử dụng theo hoá đơn nhập');
    expect(StockBalance::query()->find($this->ingredient->id)->qty)->toBe(990);
});

it('ghi hao hụt loại HAO HỤT TỰ NHIÊN trừ đúng số lượng', function () {
    $movement = ghiHaoHut($this->action, $this->ingredient, $this->chuQuan, WasteReasonCategory::NaturalLoss, 'Đá bào bay hơi trong ca', 3);

    expect($movement->reason)->toBe('[Hao hụt tự nhiên] Đá bào bay hơi trong ca');
    expect(StockBalance::query()->find($this->ingredient->id)->qty)->toBe(997);
});

it('ghi hao hụt loại DÙNG NỘI BỘ trừ đúng số lượng', function () {
    $movement = ghiHaoHut($this->action, $this->ingredient, $this->chuQuan, WasteReasonCategory::InternalUse, 'Nhân viên dùng để pha thử món mới', 2);

    expect($movement->reason)->toBe('[Dùng nội bộ] Nhân viên dùng để pha thử món mới');
    expect(StockBalance::query()->find($this->ingredient->id)->qty)->toBe(998);
});

it('bắt buộc ghi rõ lý do, không cho ghi qua loa (chuỗi rỗng)', function () {
    expect(fn () => ghiHaoHut($this->action, $this->ingredient, $this->chuQuan, WasteReasonCategory::Broken, '   '))
        ->toThrow(DomainException::class, 'Phải ghi rõ lý do hao hụt.');

    expect(StockMovement::query()->count())->toBe(1); // chỉ dòng nhập tồn đầu ở beforeEach
});

it('số lượng hao hụt phải lớn hơn 0', function () {
    expect(fn () => ghiHaoHut($this->action, $this->ingredient, $this->chuQuan, WasteReasonCategory::Broken, 'Vỡ 1 ít', 0))
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

// ── Ngưỡng PIN theo GIÁ TRỊ TIỀN của lô hao hụt (quyết định 10/08) ──────────

it('thu ngân ghi hao hụt 150.000đ (dưới ngưỡng 200.000đ) thì được, không cần PIN', function () {
    $thuNgan = User::factory()->cashier()->create();

    $movement = ghiHaoHut($this->action, $this->ingredient, $thuNgan, WasteReasonCategory::Broken, 'Vỡ một thùng lúc dọn kho', 150);

    expect($movement->cost_delta)->toBe(-150_000)
        ->and($movement->approved_by_user_id)->toBeNull()
        ->and($movement->created_by_user_id)->toBe($thuNgan->id);

    expect(StockBalance::query()->find($this->ingredient->id)->qty)->toBe(850);
});

it('thu ngân ghi hao hụt 500.000đ mà không có PIN thì bị chặn, KHÔNG mở transaction nào', function () {
    $thuNgan = User::factory()->cashier()->create();

    expect(fn () => ghiHaoHut($this->action, $this->ingredient, $thuNgan, WasteReasonCategory::Broken, 'Rơi cả khay xuống sàn', 500))
        ->toThrow(ApprovalPinRequiredException::class, 'phải có chủ quán duyệt bằng mã PIN');

    // Không dòng sổ cái nào được ghi, tồn không đổi — chứng minh lỗi nổ ra
    // TRƯỚC khi RecordStockMovement mở giao dịch.
    expect(StockMovement::query()->count())->toBe(1);
    expect(StockBalance::query()->find($this->ingredient->id)->qty)->toBe(1_000);
});

it('cùng lô 500.000đ nhưng có PIN chủ quán đúng thì thành công, sổ cái ghi lại người duyệt', function () {
    $thuNgan = User::factory()->cashier()->create();

    $movement = ghiHaoHut(
        $this->action, $this->ingredient, $thuNgan, WasteReasonCategory::Broken, 'Rơi cả khay xuống sàn', 500,
        approverUserId: $this->chuQuan->id,
        approverPin: '1234',
    );

    expect($movement->cost_delta)->toBe(-500_000)
        ->and($movement->created_by_user_id)->toBe($thuNgan->id)
        ->and($movement->approved_by_user_id)->toBe($this->chuQuan->id);

    expect(StockBalance::query()->find($this->ingredient->id)->qty)->toBe(500);
});

it('lô vượt ngưỡng mà PIN sai thì bị chặn, không ghi gì', function () {
    $thuNgan = User::factory()->cashier()->create();

    expect(fn () => ghiHaoHut(
        $this->action, $this->ingredient, $thuNgan, WasteReasonCategory::Broken, 'Rơi cả khay xuống sàn', 500,
        approverUserId: $this->chuQuan->id,
        approverPin: '9999',
    ))->toThrow(DomainException::class, 'Mã PIN không đúng.');

    expect(StockMovement::query()->count())->toBe(1);
});

it('thu ngân duyệt hộ thu ngân cũng bị chặn — chỉ chủ quán được duyệt hao hụt vượt ngưỡng', function () {
    $thuNgan = User::factory()->cashier()->create();
    $thuNganKhac = User::factory()->cashier()->withPin('5678')->create();

    expect(fn () => ghiHaoHut(
        $this->action, $this->ingredient, $thuNgan, WasteReasonCategory::Broken, 'Rơi cả khay xuống sàn', 500,
        approverUserId: $thuNganKhac->id,
        approverPin: '5678',
    ))->toThrow(ApprovalPinRequiredException::class, 'Chỉ chủ quán được duyệt hao hụt vượt ngưỡng.');

    expect(StockMovement::query()->count())->toBe(1);
});

it('chủ quán tự ghi hao hụt mức nào cũng được, không cần PIN', function () {
    $movement = ghiHaoHut($this->action, $this->ingredient, $this->chuQuan, WasteReasonCategory::Broken, 'Hỏng cả lô do mất điện tủ đông', 900);

    expect($movement->cost_delta)->toBe(-900_000)
        ->and($movement->approved_by_user_id)->toBeNull();

    expect(StockBalance::query()->find($this->ingredient->id)->qty)->toBe(100);
});

it('đúng bằng ngưỡng (200.000đ) đã phải có PIN — ngưỡng tính từ mức đó trở lên', function () {
    $thuNgan = User::factory()->cashier()->create();

    expect(fn () => ghiHaoHut($this->action, $this->ingredient, $thuNgan, WasteReasonCategory::Broken, 'Vỡ đúng hai thùng', 200))
        ->toThrow(ApprovalPinRequiredException::class);
});

it('chủ quán chỉnh ngưỡng lên 1.000.000đ thì lô 500.000đ không còn cần PIN nữa', function () {
    app(CauHinhQuan::class)->datNguongHaoHutCanPin(1_000_000);
    $thuNgan = User::factory()->cashier()->create();

    $movement = ghiHaoHut($this->action, $this->ingredient, $thuNgan, WasteReasonCategory::Broken, 'Rơi cả khay xuống sàn', 500);

    expect($movement->cost_delta)->toBe(-500_000)
        ->and($movement->approved_by_user_id)->toBeNull();
});

// ── LỚP CHẶN THỨ HAI: giá vốn nhích giữa lúc ước tính và lúc ghi sổ ────────
//
// Ước tính chạy NGOÀI giao dịch, đọc bảng tồn không khoá. Nếu có hàng nhập
// vào (hoặc món bán ra) xen vào giữa, giá vốn trung bình đổi và số tiền thật
// chốt trên sổ cái khác số dùng để quyết định đòi PIN. Ba test dưới dựng đúng
// tình huống đó bằng cách chen một phiếu nhập vào ĐÚNG lúc WriteOffStock đọc
// bảng tồn để ước tính.

/**
 * Chen đúng MỘT phiếu nhập vào lần đọc stock_balances tiếp theo: 1.000 đơn vị
 * giá 1.200.000đ → tồn thành 2.000 đơn vị / 2.200.000đ, giá vốn trung bình
 * nhích từ 1.000đ lên 1.100đ mỗi đơn vị.
 */
function nhichGiaVonDungLucUocTinh(Ingredient $ingredient, User $nguoiNhap): void
{
    $daChen = false;

    Event::listen('eloquent.retrieved: '.StockBalance::class, function () use (&$daChen, $ingredient, $nguoiNhap): void {
        if ($daChen) {
            return;
        }
        $daChen = true;

        app(RecordStockMovement::class)->handle(new RecordStockMovementData(
            uuid: (string) Str::uuid(),
            ingredientId: $ingredient->id,
            type: StockMovementType::Purchase,
            qtyDelta: 1_000,
            knownCost: 1_200_000,
            refType: StockMovementRefType::Manual,
            refId: null,
            reason: 'Nhập thêm đúng lúc thu ngân đang ghi hao hụt',
            approvedByUserId: null,
            createdByUserId: $nguoiNhap->id,
            shiftId: null,
        ));
    });
}

it('ước tính 190.000đ lọt ngưỡng nhưng giá trị thật 209.000đ thì bị chặn, không ghi gì', function () {
    $thuNgan = User::factory()->cashier()->create();
    nhichGiaVonDungLucUocTinh($this->ingredient, $this->chuQuan);

    expect(fn () => ghiHaoHut($this->action, $this->ingredient, $thuNgan, WasteReasonCategory::Broken, 'Vỡ lúc xếp lại kho', 190))
        ->toThrow(ApprovalPinRequiredException::class, 'Giá trị lô hàng vừa thay đổi, cần mã PIN chủ quán.');

    // Chỉ còn hai dòng nhập (tồn đầu + phiếu chen vào), KHÔNG có dòng hao hụt
    // nào — cả giao dịch đã quay lui sạch.
    expect(StockMovement::query()->where('type', StockMovementType::Waste)->count())->toBe(0);

    $balance = StockBalance::query()->find($this->ingredient->id);
    expect($balance->qty)->toBe(2_000)
        ->and($balance->total_cost)->toBe(2_200_000);
});

it('cùng lô đó nhưng CÓ PIN chủ quán đúng thì ghi được, dù ước tính vẫn dưới ngưỡng', function () {
    $thuNgan = User::factory()->cashier()->create();

    // Đây là lần thu ngân BẤM LẠI sau khi bị lớp hai từ chối: hàng đã nhập
    // xong từ trước, ước tính giờ ra 209.000đ hay 190.000đ đều không quan
    // trọng — có PIN thì phải xác thực và cho ghi.
    app(RecordStockMovement::class)->handle(new RecordStockMovementData(
        uuid: (string) Str::uuid(),
        ingredientId: $this->ingredient->id,
        type: StockMovementType::Purchase,
        qtyDelta: 1_000,
        knownCost: 1_200_000,
        refType: StockMovementRefType::Manual,
        refId: null,
        reason: 'Nhập thêm trước khi thu ngân bấm lại',
        approvedByUserId: null,
        createdByUserId: $this->chuQuan->id,
        shiftId: null,
    ));

    $movement = ghiHaoHut(
        $this->action, $this->ingredient, $thuNgan, WasteReasonCategory::Broken, 'Vỡ lúc xếp lại kho', 190,
        approverUserId: $this->chuQuan->id,
        approverPin: '1234',
    );

    expect($movement->cost_delta)->toBe(-209_000)
        ->and($movement->approved_by_user_id)->toBe($this->chuQuan->id);

    expect(StockBalance::query()->find($this->ingredient->id)->qty)->toBe(1_810);
});

it('ước tính và giá trị thật đều dưới ngưỡng thì vẫn ghi bình thường, không đòi PIN', function () {
    $thuNgan = User::factory()->cashier()->create();
    nhichGiaVonDungLucUocTinh($this->ingredient, $this->chuQuan);

    // Ước tính 150 × 1.000 = 150.000đ, thật 150 × 1.100 = 165.000đ — cả hai
    // vẫn dưới 200.000đ, lớp chặn thứ hai không được phép làm phiền ai.
    $movement = ghiHaoHut($this->action, $this->ingredient, $thuNgan, WasteReasonCategory::Broken, 'Vỡ vài chai lúc dọn', 150);

    expect($movement->cost_delta)->toBe(-165_000)
        ->and($movement->approved_by_user_id)->toBeNull();
});

// ── CHỐNG GHI TRÙNG THEO MÃ VÂN TAY (Bước 10) ────────────────────────────
// Khoá uq_stock_movements_ref không chặn được đường hao hụt, vì đường này luôn
// ghi ref_id rỗng và MariaDB không coi hai dòng cùng rỗng là trùng nhau.

it('ghi hao hụt hai lần cùng mã vân tay chỉ trừ kho một lần, lần hai trả về đúng dòng cũ', function () {
    $thuNgan = User::factory()->cashier()->create();
    $vanTay = (string) Str::uuid();

    $lanDau = ghiHaoHut($this->action, $this->ingredient, $thuNgan, WasteReasonCategory::Broken, 'Rơi vỡ lúc bưng bê', 5, uuid: $vanTay);
    $lanHai = ghiHaoHut($this->action, $this->ingredient, $thuNgan, WasteReasonCategory::Broken, 'Rơi vỡ lúc bưng bê', 5, uuid: $vanTay);

    expect($lanHai->id)->toBe($lanDau->id);

    expect(StockMovement::query()->where('type', StockMovementType::Waste)->count())->toBe(1);
    expect(StockBalance::query()->find($this->ingredient->id)->qty)->toBe(995);
});

it('hai mã vân tay khác nhau, cùng nguyên liệu cùng số lượng, ghi thành hai dòng — hai lần vỡ thật', function () {
    $thuNgan = User::factory()->cashier()->create();

    ghiHaoHut($this->action, $this->ingredient, $thuNgan, WasteReasonCategory::Broken, 'Rơi vỡ lúc bưng bê', 5);
    ghiHaoHut($this->action, $this->ingredient, $thuNgan, WasteReasonCategory::Broken, 'Rơi vỡ lúc bưng bê', 5);

    expect(StockMovement::query()->where('type', StockMovementType::Waste)->count())->toBe(2);
    expect(StockBalance::query()->find($this->ingredient->id)->qty)->toBe(990);
});

it('gửi lại lô hao hụt đã ghi thì không bị lớp chặn PIN thứ hai làm phiền', function () {
    $thuNgan = User::factory()->cashier()->create();
    $vanTay = (string) Str::uuid();

    // Lô 190 × 1.000 = 190.000đ, dưới ngưỡng 200.000đ nên ghi được không cần PIN.
    $lanDau = ghiHaoHut($this->action, $this->ingredient, $thuNgan, WasteReasonCategory::Broken, 'Vỡ lúc xếp kho', 190, uuid: $vanTay);

    // Nhập thêm một lô đắt hơn: giá vốn trung bình nhích lên ~1.110đ/đơn vị,
    // nên đúng lô 190 đó bây giờ ước tính ~210.900đ, VƯỢT ngưỡng 200.000đ.
    // Nó đã ghi xong từ lần đầu rồi — bấm lại phải trả về dòng cũ, không đòi PIN.
    app(RecordStockMovement::class)->handle(new RecordStockMovementData(
        uuid: (string) Str::uuid(),
        ingredientId: $this->ingredient->id,
        type: StockMovementType::Purchase,
        qtyDelta: 1_000,
        knownCost: 1_200_000,
        refType: StockMovementRefType::Manual,
        refId: null,
        reason: null,
        approvedByUserId: null,
        createdByUserId: $this->chuQuan->id,
        shiftId: null,
    ));

    $lanHai = ghiHaoHut($this->action, $this->ingredient, $thuNgan, WasteReasonCategory::Broken, 'Vỡ lúc xếp kho', 190, uuid: $vanTay);

    expect($lanHai->id)->toBe($lanDau->id);
    expect(StockMovement::query()->where('type', StockMovementType::Waste)->count())->toBe(1);
});
