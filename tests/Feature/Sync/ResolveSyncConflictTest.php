<?php

declare(strict_types=1);

/**
 * Phase 2 Bước 5 — Action ResolveSyncConflict, người quyết một xung đột đang
 * chờ ở màn hình Filament. Một test cho mỗi phương án khả dụng của 8/9 loại
 * xung đột thật sự sinh ra được (docs/thiet-ke-dong-bo.md mục 5) — dòng 1
 * (bếp báo hết món) HOÃN, chưa sinh ra được trong hệ thống hiện tại (xem
 * docs/viec-ton.md), nên không có test riêng, chỉ kiểm no-op an toàn.
 *
 * Mỗi bản ghi sync_conflicts dựng thẳng bằng factory với payload GIỐNG HỆT
 * cấu trúc thật mà SyncBatch (Bước 4) đã ghi (goc/cum) — không cần chạy lại
 * toàn bộ đường ống /sync/batch, vì cái đang kiểm ở đây là hành vi ÁP DỤNG
 * LẠI của ResolveSyncConflict, không phải hành vi PHÁT HIỆN của SyncBatch
 * (đã có tests/Feature/Sync/SyncBatchConflictMatrixTest.php lo phần đó).
 *
 * Khoá phương án dùng ĐÚNG các khoá thật mà SyncBatch::taoConflict() đã gán
 * ở từng dòng ma trận (gop/tach, ket_khong_thua/ket_co_thua,
 * thu_du_hoan_phan_thua/bo_giam_gia, huy_mon/mo_luot_moi,
 * cho_mo_ca_moi/gan_ca_dang_mo, giu_gia_moi/giam_gia_bu, bo_qua/tao_luot_moi)
 * — ResolveSyncConflict khớp trên đúng các chuỗi này, không phải nhãn chung
 * chung.
 */

use App\Domain\Billing\Models\Payment;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Ordering\Enums\TableSessionStatus;
use App\Domain\Ordering\Models\DiningTable;
use App\Domain\Ordering\Models\Order;
use App\Domain\Ordering\Models\OrderItem;
use App\Domain\Ordering\Models\TableSession;
use App\Domain\Ordering\Models\TableSessionTable;
use App\Domain\Staffing\Models\CashMovement;
use App\Domain\Staffing\Models\Shift;
use App\Domain\Staffing\Models\User;
use App\Domain\Sync\Actions\ResolveSyncConflict;
use App\Domain\Sync\DTO\ResolveSyncConflictData;
use App\Domain\Sync\Enums\ConflictKind;
use App\Domain\Sync\Enums\ConflictStatus;
use App\Domain\Sync\Models\SyncConflict;
use App\Exceptions\DomainException;
use Illuminate\Support\Str;

/** @param list<array<string, mixed>> $cum */
function taoXungDot(ConflictKind $kind, array $goc, array $cum = [], ?int $tableSessionId = null, bool $urgent = false): SyncConflict
{
    return SyncConflict::factory()->create([
        'conflict_kind' => $kind->value,
        'is_urgent' => $urgent,
        'payload' => ['goc' => $goc, 'cum' => $cum],
        'table_session_id' => $tableSessionId,
        'options' => [
            ['key' => 'x', 'label' => 'Phương án X'],
            ['key' => 'y', 'label' => 'Phương án Y'],
        ],
    ]);
}

beforeEach(function () {
    $this->nguoiQuyet = User::factory()->owner()->withPin('1234')->create();
    $this->ca = Shift::factory()->open()->create();

    $category = Category::factory()->create();
    $this->mon = Product::factory()->for($category)->create();
    $this->bienThe = ProductVariant::factory()->for($this->mon)->create(['price' => 25_000]);
});

// ── dòng 2: hai máy cùng mở bàn ───────────────────────────────────────────

it('hai máy cùng mở bàn — "Gộp" chạy lại cụm món vào lượt khách đang thắng', function () {
    $banTranhChap = DiningTable::factory()->create();
    $luotThang = TableSession::factory()->create(['shift_id' => $this->ca->id]);
    TableSessionTable::factory()->for($luotThang)->create(['dining_table_id' => $banTranhChap->id]);

    $luotThuaUuid = (string) Str::uuid();
    $donUuid = (string) Str::uuid();
    $dongMonUuid = (string) Str::uuid();

    $xungDot = taoXungDot(
        ConflictKind::HaiMayMoBan,
        goc: ['type' => 'open_session', 'payload' => [
            'uuid' => $luotThuaUuid,
            'dining_table_ids' => [$banTranhChap->id],
            'primary_dining_table_id' => $banTranhChap->id,
            'guest_count' => 2,
        ]],
        cum: [[
            'type' => 'place_order',
            'payload' => [
                'uuid' => $donUuid,
                'table_session_uuid' => $luotThuaUuid,
                'items' => [[
                    'uuid' => $dongMonUuid,
                    'product_id' => $this->mon->id,
                    'product_variant_id' => $this->bienThe->id,
                    'quantity' => 2,
                    'note' => null,
                    'options' => [],
                ]],
            ],
        ]],
        urgent: true,
    );

    app(ResolveSyncConflict::class)->handle(new ResolveSyncConflictData(
        conflictId: $xungDot->id,
        dismiss: false,
        resolution: 'gop',
        note: 'Máy 2 gọi món trước khi biết bàn đã bị máy 1 giữ — gộp vào lượt máy 1.',
        resolvedByUserId: $this->nguoiQuyet->id,
    ));

    $don = Order::query()->where('uuid', $donUuid)->sole();
    expect($don->table_session_id)->toBe($luotThang->id)
        ->and(TableSession::query()->where('uuid', $luotThuaUuid)->exists())->toBeFalse();

    $xungDot->refresh();
    expect($xungDot->status)->toBe(ConflictStatus::Resolved)
        ->and($xungDot->resolution)->toBe('gop')
        ->and($xungDot->resolved_by_user_id)->toBe($this->nguoiQuyet->id)
        ->and($xungDot->resolved_at)->not->toBeNull();
});

it('hai máy cùng mở bàn — "Tách" dựng lại đúng lượt khách thua ở bàn khác, giữ nguyên món đã gọi', function () {
    $banGoc = DiningTable::factory()->create();
    $banKhac = DiningTable::factory()->create();
    $luotDangGiuBanGoc = TableSession::factory()->create(['shift_id' => $this->ca->id]);
    TableSessionTable::factory()->for($luotDangGiuBanGoc)->create(['dining_table_id' => $banGoc->id]);

    $luotThuaUuid = (string) Str::uuid();
    $donUuid = (string) Str::uuid();

    $xungDot = taoXungDot(
        ConflictKind::HaiMayMoBan,
        goc: ['type' => 'open_session', 'payload' => [
            'uuid' => $luotThuaUuid,
            'dining_table_ids' => [$banGoc->id],
            'primary_dining_table_id' => $banGoc->id,
            'guest_count' => 3,
        ]],
        cum: [[
            'type' => 'place_order',
            'payload' => [
                'uuid' => $donUuid,
                'table_session_uuid' => $luotThuaUuid,
                'items' => [[
                    'uuid' => (string) Str::uuid(),
                    'product_id' => $this->mon->id,
                    'product_variant_id' => $this->bienThe->id,
                    'quantity' => 1,
                    'note' => null,
                    'options' => [],
                ]],
            ],
        ]],
    );

    app(ResolveSyncConflict::class)->handle(new ResolveSyncConflictData(
        conflictId: $xungDot->id,
        dismiss: false,
        resolution: 'tach',
        note: 'Khách bàn thua chuyển sang bàn trống khác.',
        resolvedByUserId: $this->nguoiQuyet->id,
        diningTableIds: [$banKhac->id],
    ));

    $luotMoi = TableSession::query()->where('uuid', $luotThuaUuid)->sole();
    expect($luotMoi->tables->pluck('dining_table_id')->all())->toBe([$banKhac->id])
        ->and(Order::query()->where('uuid', $donUuid)->where('table_session_id', $luotMoi->id)->exists())->toBeTrue();
});

// ── dòng 4: hai máy cùng thu tiền ──────────────────────────────────────────

it('hai máy cùng thu tiền — "Két không thừa" không tạo dữ liệu gì', function () {
    $session = TableSession::factory()->closed()->withTable()->create([
        'shift_id' => $this->ca->id,
        'subtotal_amount' => 380_000,
        'total_amount' => 380_000,
        'paid_amount' => 380_000,
    ]);

    $xungDot = taoXungDot(
        ConflictKind::ThuTienTrung,
        goc: ['type' => 'record_payment', 'payload' => [
            'uuid' => (string) Str::uuid(), 'table_session_uuid' => $session->uuid,
            'amount' => 380_000, 'tendered_amount' => 380_000,
        ]],
        tableSessionId: $session->id,
    );

    app(ResolveSyncConflict::class)->handle(new ResolveSyncConflictData(
        conflictId: $xungDot->id,
        dismiss: false,
        resolution: 'ket_khong_thua',
        note: 'Đếm két, không thừa tiền — thu trùng thật, bỏ phiếu này.',
        resolvedByUserId: $this->nguoiQuyet->id,
        approverUserId: $this->nguoiQuyet->id,
        approverPin: '1234',
    ));

    expect(Payment::query()->count())->toBe(0)
        ->and(CashMovement::query()->count())->toBe(0);
});

it('hai máy cùng thu tiền — "Két có thừa" ghi một khoản thu và một khoản hoàn, cùng số tiền, lý do tra được bàn nào và xung đột nào', function () {
    $ban = DiningTable::factory()->create();
    $session = TableSession::factory()->closed()->create([
        'shift_id' => $this->ca->id,
        'subtotal_amount' => 380_000,
        'total_amount' => 380_000,
        'paid_amount' => 380_000,
    ]);
    TableSessionTable::factory()->for($session)->create(['dining_table_id' => $ban->id, 'detached_at' => now()]);

    $xungDot = taoXungDot(
        ConflictKind::ThuTienTrung,
        goc: ['type' => 'record_payment', 'payload' => [
            'uuid' => (string) Str::uuid(), 'table_session_uuid' => $session->uuid,
            'amount' => 380_000, 'tendered_amount' => 380_000,
        ]],
        tableSessionId: $session->id,
    );

    app(ResolveSyncConflict::class)->handle(new ResolveSyncConflictData(
        conflictId: $xungDot->id,
        dismiss: false,
        resolution: 'ket_co_thua',
        note: 'Đếm két thấy thừa 380.000 — khách trả hai lần thật.',
        resolvedByUserId: $this->nguoiQuyet->id,
        approverUserId: $this->nguoiQuyet->id,
        approverPin: '1234',
    ));

    $khoanThu = CashMovement::query()->where('direction', 'in')->sole();
    $khoanChi = CashMovement::query()->where('direction', 'out')->sole();
    expect($khoanThu->amount)->toBe(380_000)
        ->and($khoanChi->amount)->toBe(380_000)
        ->and(Payment::query()->count())->toBe(0)
        ->and($khoanThu->reason)->toBe("Thu trùng bàn {$ban->code} - xung đột #{$xungDot->id} - khách trả hai lần thật")
        ->and($khoanChi->reason)->toBe("Hoàn lại bàn {$ban->code} - xung đột #{$xungDot->id}");
});

// ── dòng 5: thu offline, giảm giá online ───────────────────────────────────

it('thu offline vượt tổng sau giảm giá — "Thu đúng tổng mới, hoàn thừa" tạo đúng phiếu thu có tiền thối', function () {
    $session = TableSession::factory()->withTable()->create([
        'shift_id' => $this->ca->id,
        'status' => TableSessionStatus::Billing,
        'subtotal_amount' => 500_000,
        'discount_amount' => 100_000,
        'total_amount' => 400_000,
        'discount_reason' => 'Khách quen',
        'paid_amount' => 0,
    ]);
    $uuidPhieuThu = (string) Str::uuid();

    $xungDot = taoXungDot(
        ConflictKind::ThuVuotGiamGia,
        goc: ['type' => 'record_payment', 'payload' => [
            'uuid' => $uuidPhieuThu, 'table_session_uuid' => $session->uuid,
            'amount' => 500_000, 'tendered_amount' => 500_000,
        ]],
        tableSessionId: $session->id,
    );

    app(ResolveSyncConflict::class)->handle(new ResolveSyncConflictData(
        conflictId: $xungDot->id,
        dismiss: false,
        resolution: 'thu_du_hoan_phan_thua',
        note: 'Giữ giảm giá, thu đúng 400.000, hoàn 100.000.',
        resolvedByUserId: $this->nguoiQuyet->id,
        approverUserId: $this->nguoiQuyet->id,
        approverPin: '1234',
    ));

    $phieuThu = Payment::query()->where('uuid', $uuidPhieuThu)->sole();
    expect($phieuThu->amount)->toBe(400_000)
        ->and($phieuThu->change_amount)->toBe(100_000);
});

it('thu offline vượt tổng sau giảm giá — "Bỏ giảm giá, thu đủ" phục hồi tổng cũ rồi thu đủ', function () {
    $session = TableSession::factory()->withTable()->create([
        'shift_id' => $this->ca->id,
        'status' => TableSessionStatus::Billing,
        'subtotal_amount' => 500_000,
        'discount_amount' => 100_000,
        'total_amount' => 400_000,
        'discount_reason' => 'Khách quen',
        'paid_amount' => 0,
    ]);
    // "Bỏ giảm giá" gọi lại CalculateBill, tính lại tạm tính từ dòng món THẬT
    // (T2) — phải có món thật khớp 500.000 thì tạm tính mới không bị tính lại
    // về 0 rồi đè lên discount_amount cũ.
    $don = Order::factory()->for($session, 'tableSession')->create();
    OrderItem::factory()->for($don, 'order')->create(['unit_price' => 500_000, 'options_amount' => 0, 'quantity' => 1]);
    $uuidPhieuThu = (string) Str::uuid();

    $xungDot = taoXungDot(
        ConflictKind::ThuVuotGiamGia,
        goc: ['type' => 'record_payment', 'payload' => [
            'uuid' => $uuidPhieuThu, 'table_session_uuid' => $session->uuid,
            'amount' => 500_000, 'tendered_amount' => 500_000,
        ]],
        tableSessionId: $session->id,
    );

    app(ResolveSyncConflict::class)->handle(new ResolveSyncConflictData(
        conflictId: $xungDot->id,
        dismiss: false,
        resolution: 'bo_giam_gia',
        note: 'Bỏ giảm giá, thu đủ 500.000 như máy POS đã ghi.',
        resolvedByUserId: $this->nguoiQuyet->id,
        approverUserId: $this->nguoiQuyet->id,
        approverPin: '1234',
    ));

    $session->refresh();
    expect($session->discount_amount)->toBe(0)
        ->and($session->total_amount)->toBe(500_000);

    $phieuThu = Payment::query()->where('uuid', $uuidPhieuThu)->sole();
    expect($phieuThu->amount)->toBe(500_000)
        ->and($phieuThu->change_amount)->toBe(0);
});

// ── dòng 6/9: gọi món vào lượt đã đóng / đã huỷ ────────────────────────────

it('gọi món vào lượt đã đóng — "Huỷ món" không tạo phiếu gọi món nào', function () {
    $session = TableSession::factory()->closed()->withTable()->create(['shift_id' => $this->ca->id]);

    $xungDot = taoXungDot(
        ConflictKind::LuotDaDong,
        goc: ['type' => 'place_order', 'payload' => [
            'uuid' => (string) Str::uuid(), 'table_session_uuid' => $session->uuid,
            'items' => [['uuid' => (string) Str::uuid(), 'product_id' => $this->mon->id, 'product_variant_id' => $this->bienThe->id, 'quantity' => 1, 'note' => null]],
        ]],
        tableSessionId: $session->id,
    );

    app(ResolveSyncConflict::class)->handle(new ResolveSyncConflictData(
        conflictId: $xungDot->id,
        dismiss: false,
        resolution: 'huy_mon',
        note: 'Gọi nhầm, khách đã trả bàn xong.',
        resolvedByUserId: $this->nguoiQuyet->id,
    ));

    expect(Order::query()->count())->toBe(0);
});

it('gọi món vào lượt đã đóng — "Mở lượt khách mới" dựng lượt mới ở đúng bàn cũ và tạo lại phiếu gọi món', function () {
    $ban = DiningTable::factory()->create();
    $session = TableSession::factory()->closed()->create(['shift_id' => $this->ca->id]);
    TableSessionTable::factory()->for($session)->create(['dining_table_id' => $ban->id, 'detached_at' => now()]);

    $donUuid = (string) Str::uuid();
    $xungDot = taoXungDot(
        ConflictKind::LuotDaDong,
        goc: ['type' => 'place_order', 'payload' => [
            'uuid' => $donUuid, 'table_session_uuid' => $session->uuid,
            'items' => [['uuid' => (string) Str::uuid(), 'product_id' => $this->mon->id, 'product_variant_id' => $this->bienThe->id, 'quantity' => 2, 'note' => null]],
        ]],
        tableSessionId: $session->id,
    );

    app(ResolveSyncConflict::class)->handle(new ResolveSyncConflictData(
        conflictId: $xungDot->id,
        dismiss: false,
        resolution: 'mo_luot_moi',
        note: 'Khách mới ngồi vào đúng bàn đó.',
        resolvedByUserId: $this->nguoiQuyet->id,
    ));

    $don = Order::query()->where('uuid', $donUuid)->sole();
    $luotMoi = TableSession::query()->findOrFail($don->table_session_id);
    expect($luotMoi->id)->not->toBe($session->id)
        ->and($luotMoi->tables->pluck('dining_table_id')->all())->toBe([$ban->id]);
});

// ── dòng 7: phiếu thu thuộc ca đã đóng ──────────────────────────────────────

it('phiếu thu thuộc ca đã đóng — "Chờ mở ca mới" không tạo phiếu thu', function () {
    $caCu = Shift::factory()->closed()->create();
    $session = TableSession::factory()->withTable()->create([
        'shift_id' => $caCu->id,
        'status' => TableSessionStatus::Billing,
        'subtotal_amount' => 150_000,
        'total_amount' => 150_000,
        'paid_amount' => 0,
    ]);

    $xungDot = taoXungDot(
        ConflictKind::CaDaDong,
        goc: ['type' => 'record_payment', 'payload' => [
            'uuid' => (string) Str::uuid(), 'table_session_uuid' => $session->uuid,
            'amount' => 150_000, 'tendered_amount' => 150_000,
        ]],
        tableSessionId: $session->id,
    );

    app(ResolveSyncConflict::class)->handle(new ResolveSyncConflictData(
        conflictId: $xungDot->id,
        dismiss: false,
        resolution: 'cho_mo_ca_moi',
        note: 'Chưa có ca nào đang mở, chờ mở ca rồi xử lý lại.',
        resolvedByUserId: $this->nguoiQuyet->id,
    ));

    expect(Payment::query()->count())->toBe(0);
});

it('phiếu thu thuộc ca đã đóng — "Gán ca đang mở" chuyển lượt khách sang ca mới rồi ghi phiếu thu vào ca đó', function () {
    $caCu = Shift::factory()->closed()->create();
    $session = TableSession::factory()->withTable()->create([
        'shift_id' => $caCu->id,
        'status' => TableSessionStatus::Billing,
        'subtotal_amount' => 150_000,
        'total_amount' => 150_000,
        'paid_amount' => 0,
    ]);
    $uuidPhieuThu = (string) Str::uuid();

    $xungDot = taoXungDot(
        ConflictKind::CaDaDong,
        goc: ['type' => 'record_payment', 'payload' => [
            'uuid' => $uuidPhieuThu, 'table_session_uuid' => $session->uuid,
            'amount' => 150_000, 'tendered_amount' => 150_000,
        ]],
        tableSessionId: $session->id,
    );

    app(ResolveSyncConflict::class)->handle(new ResolveSyncConflictData(
        conflictId: $xungDot->id,
        dismiss: false,
        resolution: 'gan_ca_dang_mo',
        note: 'Tính tiền này vào ca đang mở hiện tại.',
        resolvedByUserId: $this->nguoiQuyet->id,
    ));

    $session->refresh();
    expect($session->shift_id)->toBe($this->ca->id);

    $phieuThu = Payment::query()->where('uuid', $uuidPhieuThu)->sole();
    expect($phieuThu->shift_id)->toBe($this->ca->id);
});

// ── dòng 8: giá món đổi ─────────────────────────────────────────────────────

it('giá món đổi — "Giữ giá mới" không đổi gì thêm', function () {
    $session = TableSession::factory()->withTable()->create(['shift_id' => $this->ca->id]);
    $don = Order::factory()->for($session, 'tableSession')->create();
    $dongMon = OrderItem::factory()->for($don, 'order')->create(['unit_price' => 27_000, 'quantity' => 3]);

    $xungDot = taoXungDot(
        ConflictKind::GiaLech,
        goc: ['type' => 'place_order', 'payload' => [
            'uuid' => $don->uuid,
            'items' => [['uuid' => $dongMon->uuid, 'client_unit_price' => 25_000]],
        ]],
        tableSessionId: $session->id,
    );

    app(ResolveSyncConflict::class)->handle(new ResolveSyncConflictData(
        conflictId: $xungDot->id,
        dismiss: false,
        resolution: 'giu_gia_moi',
        note: 'Giữ đúng bảng giá hiện tại.',
        resolvedByUserId: $this->nguoiQuyet->id,
    ));

    $session->refresh();
    expect($session->discount_amount)->toBe(0);
});

it('giá món đổi — "Giảm giá bù" giảm đúng phần server thu nhiều hơn giá khách đã thấy', function () {
    $session = TableSession::factory()->withTable()->create(['shift_id' => $this->ca->id]);
    $don = Order::factory()->for($session, 'tableSession')->create();
    $dongMon = OrderItem::factory()->for($don, 'order')->create(['unit_price' => 27_000, 'quantity' => 3]);

    $xungDot = taoXungDot(
        ConflictKind::GiaLech,
        goc: ['type' => 'place_order', 'payload' => [
            'uuid' => $don->uuid,
            'items' => [['uuid' => $dongMon->uuid, 'client_unit_price' => 25_000]],
        ]],
        tableSessionId: $session->id,
    );

    app(ResolveSyncConflict::class)->handle(new ResolveSyncConflictData(
        conflictId: $xungDot->id,
        dismiss: false,
        resolution: 'giam_gia_bu',
        note: 'Giữ đúng phiếu tạm tính đã đưa khách, giảm bù phần chênh.',
        resolvedByUserId: $this->nguoiQuyet->id,
    ));

    $session->refresh();
    // (27.000 - 25.000) * 3 = 6.000
    expect($session->discount_amount)->toBe(6_000);
});

// ── dòng 10: thiếu thao tác gốc ──────────────────────────────────────────────

it('thiếu thao tác gốc — "Bỏ qua" không tạo dữ liệu gì', function () {
    $xungDot = taoXungDot(
        ConflictKind::ThieuThaoTacGoc,
        goc: ['type' => 'place_order', 'payload' => [
            'uuid' => (string) Str::uuid(), 'table_session_uuid' => (string) Str::uuid(),
            'items' => [['uuid' => (string) Str::uuid(), 'product_id' => $this->mon->id, 'product_variant_id' => $this->bienThe->id, 'quantity' => 1, 'note' => null]],
        ]],
    );

    app(ResolveSyncConflict::class)->handle(new ResolveSyncConflictData(
        conflictId: $xungDot->id,
        dismiss: false,
        resolution: 'bo_qua',
        note: 'Bỏ qua, dữ liệu không còn ý nghĩa.',
        resolvedByUserId: $this->nguoiQuyet->id,
    ));

    expect(Order::query()->count())->toBe(0);
});

it('thiếu thao tác gốc — "Tạo lượt khách mới" dựng lượt khách ở bàn được chọn rồi ghi lại phiếu gọi món', function () {
    $ban = DiningTable::factory()->create();
    $donUuid = (string) Str::uuid();

    $xungDot = taoXungDot(
        ConflictKind::ThieuThaoTacGoc,
        goc: ['type' => 'place_order', 'payload' => [
            'uuid' => $donUuid, 'table_session_uuid' => (string) Str::uuid(),
            'items' => [['uuid' => (string) Str::uuid(), 'product_id' => $this->mon->id, 'product_variant_id' => $this->bienThe->id, 'quantity' => 1, 'note' => null]],
        ]],
    );

    app(ResolveSyncConflict::class)->handle(new ResolveSyncConflictData(
        conflictId: $xungDot->id,
        dismiss: false,
        resolution: 'tao_luot_moi',
        note: 'Tạo lượt khách mới ở bàn trống cho các món này.',
        resolvedByUserId: $this->nguoiQuyet->id,
        diningTableIds: [$ban->id],
    ));

    $don = Order::query()->where('uuid', $donUuid)->sole();
    expect(TableSession::query()->findOrFail($don->table_session_id)->tables->pluck('dining_table_id')->all())->toBe([$ban->id]);
});

it('thiếu thao tác gốc — "Tạo lượt khách mới" từ chối khi thao tác gốc không phải gọi món', function () {
    $xungDot = taoXungDot(
        ConflictKind::ThieuThaoTacGoc,
        goc: ['type' => 'send_to_kitchen', 'payload' => ['order_uuid' => (string) Str::uuid()]],
    );

    expect(fn () => app(ResolveSyncConflict::class)->handle(new ResolveSyncConflictData(
        conflictId: $xungDot->id,
        dismiss: false,
        resolution: 'tao_luot_moi',
        note: 'Thử tạo lượt mới.',
        resolvedByUserId: $this->nguoiQuyet->id,
        diningTableIds: [DiningTable::factory()->create()->id],
    )))->toThrow(DomainException::class);
});

// ── quy tắc chung ────────────────────────────────────────────────────────────

it('bắt buộc ghi lý do — thiếu lý do thì từ chối, không xử lý', function () {
    $xungDot = taoXungDot(ConflictKind::ThuTienTrung, goc: ['type' => 'record_payment', 'payload' => ['uuid' => (string) Str::uuid(), 'amount' => 100_000]]);

    expect(fn () => app(ResolveSyncConflict::class)->handle(new ResolveSyncConflictData(
        conflictId: $xungDot->id,
        dismiss: false,
        resolution: 'ket_khong_thua',
        note: '   ',
        resolvedByUserId: $this->nguoiQuyet->id,
    )))->toThrow(DomainException::class);

    expect($xungDot->refresh()->status)->toBe(ConflictStatus::Pending);
});

it('"Bỏ qua" ghi nhận đã xem nhưng chưa xử lý — không đổi dữ liệu, bắt buộc có lý do', function () {
    // Dùng loại không dính tiền (LuotDaDong) — PIN chỉ bắt buộc cho ThuTienTrung/
    // ThuVuotGiamGia (đã có test riêng cho việc đó ở trên), test này chỉ kiểm cơ
    // chế "bỏ qua" chung.
    $xungDot = taoXungDot(ConflictKind::LuotDaDong, goc: ['type' => 'place_order', 'payload' => ['uuid' => (string) Str::uuid(), 'table_session_uuid' => (string) Str::uuid(), 'items' => []]]);

    app(ResolveSyncConflict::class)->handle(new ResolveSyncConflictData(
        conflictId: $xungDot->id,
        dismiss: true,
        resolution: null,
        note: 'Cần hỏi lại khách trước khi quyết, để đó đã.',
        resolvedByUserId: $this->nguoiQuyet->id,
    ));

    $xungDot->refresh();
    expect($xungDot->status)->toBe(ConflictStatus::Dismissed)
        ->and($xungDot->resolution)->toBe('bo_qua')
        ->and(Payment::query()->count())->toBe(0);
});

it('không xử lý lại một xung đột đã xử lý xong', function () {
    $xungDot = taoXungDot(ConflictKind::LuotDaDong, goc: ['type' => 'place_order', 'payload' => ['uuid' => (string) Str::uuid(), 'table_session_uuid' => (string) Str::uuid(), 'items' => []]]);

    app(ResolveSyncConflict::class)->handle(new ResolveSyncConflictData(
        conflictId: $xungDot->id,
        dismiss: false,
        resolution: 'huy_mon',
        note: 'Xử lý lần đầu.',
        resolvedByUserId: $this->nguoiQuyet->id,
    ));

    expect(fn () => app(ResolveSyncConflict::class)->handle(new ResolveSyncConflictData(
        conflictId: $xungDot->id,
        dismiss: false,
        resolution: 'huy_mon',
        note: 'Thử xử lý lần hai.',
        resolvedByUserId: $this->nguoiQuyet->id,
    )))->toThrow(DomainException::class);
});
