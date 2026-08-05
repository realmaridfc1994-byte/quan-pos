<?php

declare(strict_types=1);

/**
 * Phase 2 Bước 5, mục 3 của yêu cầu: chặn đóng ca khi còn xung đột đồng bộ
 * chưa xử lý — vì xung đột chưa xử lý nghĩa là số tiền chưa chắc đúng
 * (docs/thiet-ke-dong-bo.md mục 6.2).
 */

use App\Domain\Billing\Actions\RecordPayment;
use App\Domain\Billing\DTO\RecordPaymentData;
use App\Domain\Billing\Enums\PaymentMethod;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Ordering\Models\DiningTable;
use App\Domain\Ordering\Models\Order;
use App\Domain\Ordering\Models\OrderItem;
use App\Domain\Ordering\Models\TableSession;
use App\Domain\Ordering\Models\TableSessionTable;
use App\Domain\Staffing\Actions\CloseShift;
use App\Domain\Staffing\DTO\CloseShiftData;
use App\Domain\Staffing\Models\Shift;
use App\Domain\Staffing\Models\User;
use App\Domain\Sync\Actions\ResolveSyncConflict;
use App\Domain\Sync\DTO\ResolveSyncConflictData;
use App\Domain\Sync\Enums\ConflictKind;
use App\Domain\Sync\Enums\ConflictStatus;
use App\Domain\Sync\Models\SyncConflict;
use App\Exceptions\ApprovalPinRequiredException;
use App\Exceptions\DomainException;
use App\Support\Money;
use Illuminate\Support\Str;

function dongCaTrucTiep(Shift $ca, User $thuNgan): Shift
{
    return app(CloseShift::class)->handle(new CloseShiftData(
        shiftId: $ca->id,
        countedCash: Money::fromInt($ca->opening_cash),
        note: null,
        closedByUserId: $thuNgan->id,
    ));
}

beforeEach(function () {
    $this->thuNgan = User::factory()->cashier()->withPin('1234')->create();
    $this->ca = Shift::factory()->open()->create(['opened_by_user_id' => $this->thuNgan->id]);
});

it('chặn đóng ca khi còn xung đột chưa xử lý gắn với lượt khách thuộc ca này', function () {
    $ban = DiningTable::factory()->create();
    $session = TableSession::factory()->closed()->create(['shift_id' => $this->ca->id]);
    TableSessionTable::factory()->for($session)->create(['dining_table_id' => $ban->id, 'detached_at' => now()]);

    SyncConflict::factory()->create([
        'conflict_kind' => ConflictKind::ThuTienTrung->value,
        'table_session_id' => $session->id,
        'status' => ConflictStatus::Pending,
    ]);

    expect(fn () => dongCaTrucTiep($this->ca, $this->thuNgan))
        ->toThrow(DomainException::class);
});

it('chặn đóng ca khi còn xung đột không xác định được lượt khách nào (dòng 10)', function () {
    SyncConflict::factory()->create([
        'conflict_kind' => ConflictKind::ThieuThaoTacGoc->value,
        'table_session_id' => null,
        'status' => ConflictStatus::Pending,
    ]);

    expect(fn () => dongCaTrucTiep($this->ca, $this->thuNgan))
        ->toThrow(DomainException::class);
});

it('không chặn đóng ca vì xung đột thuộc MỘT ca khác', function () {
    $caKhac = Shift::factory()->closed()->create();
    $session = TableSession::factory()->closed()->create(['shift_id' => $caKhac->id]);

    SyncConflict::factory()->create([
        'conflict_kind' => ConflictKind::ThuTienTrung->value,
        'table_session_id' => $session->id,
        'status' => ConflictStatus::Pending,
    ]);

    $ket = dongCaTrucTiep($this->ca, $this->thuNgan);
    expect($ket->status->value)->toBe('closed');
});

it('xử lý xong xung đột rồi thì đóng ca lại được', function () {
    $ban = DiningTable::factory()->create();
    $session = TableSession::factory()->closed()->create(['shift_id' => $this->ca->id]);
    TableSessionTable::factory()->for($session)->create(['dining_table_id' => $ban->id, 'detached_at' => now()]);

    $xungDot = SyncConflict::factory()->create([
        'conflict_kind' => ConflictKind::ThuTienTrung->value,
        'table_session_id' => $session->id,
        'status' => ConflictStatus::Pending,
        'options' => [['key' => 'ket_khong_thua', 'label' => 'Két không thừa']],
        'payload' => ['goc' => ['type' => 'record_payment', 'payload' => ['uuid' => (string) Str::uuid(), 'amount' => 10_000]], 'cum' => []],
    ]);

    app(ResolveSyncConflict::class)->handle(new ResolveSyncConflictData(
        conflictId: $xungDot->id,
        dismiss: false,
        resolution: 'ket_khong_thua',
        note: 'Két không thừa, bỏ phiếu này.',
        resolvedByUserId: $this->thuNgan->id,
        approverUserId: $this->thuNgan->id,
        approverPin: '1234',
    ));

    $ket = dongCaTrucTiep($this->ca, $this->thuNgan);
    expect($ket->status->value)->toBe('closed');
});

/**
 * Tình huống 🔴 đã sửa 05/08: bàn một món, bill 100k, giá đổi lên 130k —
 * khoản bù 30k trên 130k ≈ 23%, vượt ngưỡng 20% của thu ngân. Trước khi sửa,
 * ResolveSyncConflict mở sẵn transaction rồi mới phát hiện thiếu PIN, xung
 * đột kẹt "pending" mãi và CloseShift không bao giờ hết bị chặn nếu chủ quán
 * không có mặt lúc đóng ca. Test này xác nhận: có PIN đúng thì xử lý xong
 * ngay tại một lần gọi (không mở giao dịch treo), và CloseShift hết bị chặn.
 */
it('xử lý xong xung đột giá lệch vượt ngưỡng PIN rồi thì đóng ca lại được, không cần chủ quán có mặt lúc đóng ca', function () {
    $chuQuan = User::factory()->owner()->withPin('9999')->create();

    $session = TableSession::factory()->withTable()->create(['shift_id' => $this->ca->id]);

    $category = Category::factory()->create();
    $mon = Product::factory()->for($category)->create();
    $bienThe = ProductVariant::factory()->for($mon)->create(['price' => 130_000]);
    $don = Order::factory()->for($session, 'tableSession')->create();
    // Server ghi 130.000 (giá thật), khách chỉ thấy 100.000 lúc gọi món offline
    // — chênh 30.000 trên tạm tính 130.000 ≈ 23%, vượt ngưỡng 20% của thu ngân.
    $dongMon = OrderItem::factory()->for($don, 'order')->create(['unit_price' => 130_000, 'quantity' => 1]);

    $xungDot = SyncConflict::factory()->create([
        'conflict_kind' => ConflictKind::GiaLech->value,
        'table_session_id' => $session->id,
        'status' => ConflictStatus::Pending,
        'options' => [
            ['key' => 'giu_gia_moi', 'label' => 'Giữ giá mới'],
            ['key' => 'giam_gia_bu', 'label' => 'Giảm giá bù'],
        ],
        'payload' => ['goc' => ['type' => 'place_order', 'payload' => [
            'uuid' => $don->uuid,
            'items' => [['uuid' => $dongMon->uuid, 'client_unit_price' => 100_000]],
        ]], 'cum' => []],
    ]);

    // Thu ngân thử xử lý mà không có PIN — bị chặn ngay, KHÔNG mở giao dịch nào.
    expect(fn () => app(ResolveSyncConflict::class)->handle(new ResolveSyncConflictData(
        conflictId: $xungDot->id,
        dismiss: false,
        resolution: 'giam_gia_bu',
        note: 'Chênh lệch lớn, cần chủ quán duyệt.',
        resolvedByUserId: $this->thuNgan->id,
    )))->toThrow(ApprovalPinRequiredException::class);

    expect($xungDot->refresh()->status)->toBe(ConflictStatus::Pending);
    expect(fn () => dongCaTrucTiep($this->ca, $this->thuNgan))->toThrow(DomainException::class);

    // Chủ quán gửi PIN qua điện thoại — thu ngân nhập, xử lý xong ngay lần gọi này.
    app(ResolveSyncConflict::class)->handle(new ResolveSyncConflictData(
        conflictId: $xungDot->id,
        dismiss: false,
        resolution: 'giam_gia_bu',
        note: 'Chủ quán duyệt PIN qua điện thoại.',
        resolvedByUserId: $this->thuNgan->id,
        approverUserId: $chuQuan->id,
        approverPin: '9999',
    ));

    expect($xungDot->refresh()->status)->toBe(ConflictStatus::Resolved);

    // Xung đột tiền chỉ chỉnh lại discount/total_amount — bàn vẫn đang mở, phải
    // thu tiền và đóng lượt khách như bình thường trước khi đóng ca được.
    $session->refresh();
    app(RecordPayment::class)->handle(new RecordPaymentData(
        uuid: (string) Str::uuid(),
        tableSessionId: $session->id,
        method: PaymentMethod::Cash,
        amount: Money::fromInt($session->total_amount),
        tenderedAmount: Money::fromInt($session->total_amount),
        reference: null,
        receivedByUserId: $this->thuNgan->id,
    ));

    $ket = dongCaTrucTiep($this->ca, $this->thuNgan);
    expect($ket->status->value)->toBe('closed');
});
