<?php

declare(strict_types=1);

/**
 * Một test cho MỖI dòng của ma trận xung đột — docs/thiet-ke-dong-bo.md mục 5.
 * Dòng 1 (bếp báo hết món) HOÃN — chưa có tính năng "bếp báo hết món" để tạo
 * tín hiệu so sánh (quyết định 04/08, xem docs/viec-ton.md). Dòng 3 không
 * phải xung đột (nhận cả hai, cộng dồn) nên không sinh sync_conflicts —
 * kiểm bằng cách khẳng định KHÔNG có bản ghi nào, không phải "một dòng".
 *
 * Mỗi test khẳng định cả hai điều thiết kế yêu cầu ở mục 9: server tự làm
 * đúng việc (auto_action / có tạo bản ghi hay không / có gọi Action hay
 * không), VÀ sync_conflicts sinh đúng loại kèm câu thông báo tiếng Việt.
 */

use App\Domain\Billing\Models\Payment;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Ordering\Enums\TableSessionStatus;
use App\Domain\Ordering\Models\DiningTable;
use App\Domain\Ordering\Models\Order;
use App\Domain\Ordering\Models\TableSession;
use App\Domain\Staffing\Models\Shift;
use App\Domain\Staffing\Models\User;
use App\Domain\Sync\Enums\ConflictKind;
use App\Domain\Sync\Models\SyncConflict;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/** @param array<int, array<string, mixed>> $operations */
function guiDongBo(User $user, array $operations, ?string $deviceId = null, ?string $batchUuid = null): TestResponse
{
    return test()->postJson('/api/v1/sync/batch', [
        'device_id' => $deviceId ?? 'pos-test-1',
        'batch_uuid' => $batchUuid ?? (string) Str::uuid(),
        'client_time' => now()->toIso8601String(),
        'operations' => $operations,
    ], authHeaderFor($user));
}

function opMoBan(string $opUuid, string $luotUuid, array $banIds, int $banChinhId, int $soKhach = 2, ?string $occurredAt = null): array
{
    return [
        'op_uuid' => $opUuid,
        'type' => 'open_session',
        'occurred_at' => $occurredAt ?? now()->toIso8601String(),
        'depends_on' => [],
        'payload' => [
            'uuid' => $luotUuid,
            'dining_table_ids' => $banIds,
            'primary_dining_table_id' => $banChinhId,
            'guest_count' => $soKhach,
        ],
    ];
}

function opGoiMon(string $opUuid, string $luotUuid, Product $mon, ProductVariant $bienThe, array $dependsOn = [], ?int $clientUnitPrice = null, ?string $occurredAt = null): array
{
    return [
        'op_uuid' => $opUuid,
        'type' => 'place_order',
        'occurred_at' => $occurredAt ?? now()->toIso8601String(),
        'depends_on' => $dependsOn,
        'payload' => [
            'uuid' => (string) Str::uuid(),
            'table_session_uuid' => $luotUuid,
            'items' => [[
                'uuid' => (string) Str::uuid(),
                'product_id' => $mon->id,
                'product_variant_id' => $bienThe->id,
                'quantity' => 1,
                'note' => null,
                'client_unit_price' => $clientUnitPrice ?? $bienThe->price,
                'options' => [],
            ]],
        ],
    ];
}

function opThuTienMat(string $opUuid, string $luotUuid, int $soTien, array $dependsOn = [], ?string $occurredAt = null): array
{
    return [
        'op_uuid' => $opUuid,
        'type' => 'record_payment',
        'occurred_at' => $occurredAt ?? now()->toIso8601String(),
        'depends_on' => $dependsOn,
        'payload' => [
            'uuid' => (string) Str::uuid(),
            'table_session_uuid' => $luotUuid,
            'amount' => $soTien,
            'tendered_amount' => $soTien,
        ],
    ];
}

beforeEach(function () {
    $this->thuNgan = User::factory()->cashier()->create();
    $this->ca = Shift::factory()->open()->create();

    $category = Category::factory()->create();
    $this->mon = Product::factory()->for($category)->create();
    $this->bienThe = ProductVariant::factory()->for($this->mon)->create(['price' => 25_000]);
});

it('dòng 2: hai máy cùng offline cùng mở bàn — máy sau gom cả cụm vào một xung đột GẤP', function () {
    $ban = DiningTable::factory()->create();

    $luotA = (string) Str::uuid();
    guiDongBo($this->thuNgan, [opMoBan((string) Str::uuid(), $luotA, [$ban->id], $ban->id)], deviceId: 'pos-01')
        ->assertOk();

    $opMoBanB = (string) Str::uuid();
    $luotB = (string) Str::uuid();
    $opGoiMonB = (string) Str::uuid();

    $response = guiDongBo($this->thuNgan, [
        opMoBan($opMoBanB, $luotB, [$ban->id], $ban->id),
        opGoiMon($opGoiMonB, $luotB, $this->mon, $this->bienThe, dependsOn: [$opMoBanB]),
    ], deviceId: 'pos-02')->assertOk();

    $ketQuaMo = collect($response->json('results'))->firstWhere('op_uuid', $opMoBanB);
    $ketQuaMon = collect($response->json('results'))->firstWhere('op_uuid', $opGoiMonB);

    expect($ketQuaMo['status'])->toBe('conflict')
        ->and($ketQuaMon['status'])->toBe('conflict')
        ->and($ketQuaMon['conflict_id'])->toBe($ketQuaMo['conflict_id']);

    $conflict = SyncConflict::query()->findOrFail($ketQuaMo['conflict_id']);
    expect($conflict->conflict_kind)->toBe(ConflictKind::HaiMayMoBan->value)
        ->and($conflict->is_urgent)->toBeTrue()
        ->and($conflict->auto_action)->toBe('khong_lam_gi')
        ->and($conflict->message_vi)->not->toBeEmpty()
        ->and($conflict->payload['cum'])->toHaveCount(1)
        ->and($conflict->payload['cum'][0]['op_uuid'])->toBe($opGoiMonB);

    expect(TableSession::query()->where('uuid', $luotB)->exists())->toBeFalse();
});

it('dòng 3: hai máy cùng gọi món vào cùng bàn — nhận cả hai, cộng dồn, không phải xung đột', function () {
    $ban = DiningTable::factory()->create();
    $luot = (string) Str::uuid();
    $opMo = (string) Str::uuid();

    guiDongBo($this->thuNgan, [opMoBan($opMo, $luot, [$ban->id], $ban->id)])->assertOk();

    $response = guiDongBo($this->thuNgan, [
        opGoiMon((string) Str::uuid(), $luot, $this->mon, $this->bienThe),
        opGoiMon((string) Str::uuid(), $luot, $this->mon, $this->bienThe),
    ], deviceId: 'pos-02')->assertOk();

    foreach ($response->json('results') as $ketQua) {
        expect($ketQua['status'])->toBe('applied');
    }

    $session = TableSession::query()->where('uuid', $luot)->sole();
    expect($session->subtotal_amount)->toBe(50_000)
        ->and(SyncConflict::query()->count())->toBe(0);
});

it('dòng 4: hai máy cùng thu tiền một lượt khách — phiếu sau không tạo dòng nào trong payments', function () {
    $session = TableSession::factory()->withTable()->create([
        'shift_id' => $this->ca->id,
        'status' => TableSessionStatus::Billing,
        'subtotal_amount' => 380_000,
        'total_amount' => 380_000,
        'paid_amount' => 380_000,
    ]);
    Payment::factory()->for($session, 'tableSession')->for($this->ca, 'shift')->create([
        'method' => 'cash',
        'amount' => 380_000,
        'tendered_amount' => 380_000,
        'change_amount' => 0,
    ]);

    $response = guiDongBo($this->thuNgan, [
        opThuTienMat((string) Str::uuid(), $session->uuid, 380_000),
    ], deviceId: 'pos-02')->assertOk();

    $ketQua = $response->json('results.0');
    expect($ketQua['status'])->toBe('conflict');

    $conflict = SyncConflict::query()->findOrFail($ketQua['conflict_id']);
    expect($conflict->conflict_kind)->toBe(ConflictKind::ThuTienTrung->value)
        ->and($conflict->auto_action)->toBe('khong_lam_gi')
        ->and($conflict->message_vi)->not->toBeEmpty();

    expect(Payment::query()->where('table_session_id', $session->id)->count())->toBe(1);
});

it('dòng 5: thu offline vượt tổng sau khi máy online đã giảm giá — không tạo phiếu', function () {
    $session = TableSession::factory()->withTable()->create([
        'shift_id' => $this->ca->id,
        'status' => TableSessionStatus::Billing,
        'subtotal_amount' => 500_000,
        'discount_amount' => 100_000,
        'total_amount' => 400_000,
        'discount_reason' => 'Khách quen',
        'paid_amount' => 0,
    ]);

    $response = guiDongBo($this->thuNgan, [
        opThuTienMat((string) Str::uuid(), $session->uuid, 500_000),
    ], deviceId: 'pos-02')->assertOk();

    $ketQua = $response->json('results.0');
    expect($ketQua['status'])->toBe('conflict');

    $conflict = SyncConflict::query()->findOrFail($ketQua['conflict_id']);
    expect($conflict->conflict_kind)->toBe(ConflictKind::ThuVuotGiamGia->value)
        ->and($conflict->auto_action)->toBe('khong_lam_gi')
        ->and($conflict->message_vi)->not->toBeEmpty();

    expect(Payment::query()->where('table_session_id', $session->id)->count())->toBe(0);
});

it('dòng 6: gọi món vào lượt khách máy khác đã đóng — không nhét vào lượt cũ, gom cụm', function () {
    $session = TableSession::factory()->withTable()->create([
        'shift_id' => $this->ca->id,
        'status' => TableSessionStatus::Closed,
        'closed_at' => now(),
        'subtotal_amount' => 100_000,
        'total_amount' => 100_000,
        'paid_amount' => 100_000,
    ]);

    $opMon = (string) Str::uuid();
    $response = guiDongBo($this->thuNgan, [
        opGoiMon($opMon, $session->uuid, $this->mon, $this->bienThe),
    ], deviceId: 'pos-02')->assertOk();

    $ketQua = $response->json('results.0');
    expect($ketQua['status'])->toBe('conflict');

    $conflict = SyncConflict::query()->findOrFail($ketQua['conflict_id']);
    expect($conflict->conflict_kind)->toBe(ConflictKind::LuotDaDong->value)
        ->and($conflict->is_urgent)->toBeTrue()
        ->and($conflict->message_vi)->not->toBeEmpty()
        ->and($conflict->table_session_id)->toBe($session->id);

    expect(Order::query()->where('table_session_id', $session->id)->count())->toBe(0);
});

it('dòng 7: phiếu thu thuộc ca đã đóng ở server — luôn cần người quyết, không tự gán ca, không tạo phiếu', function () {
    // RecordPayment tra CA CỦA CHÍNH LƯỢT KHÁCH (không tự lấy "ca đang mở bất
    // kỳ") — nên dù $this->ca (mở ở beforeEach) đang mở, phiếu thu offline
    // thuộc một ca KHÁC đã đóng vẫn luôn bị từ chối, không tự gán sang ca này.
    $caCu = Shift::factory()->closed()->create();
    $session = TableSession::factory()->withTable()->create([
        'shift_id' => $caCu->id,
        'status' => TableSessionStatus::Billing,
        'subtotal_amount' => 150_000,
        'total_amount' => 150_000,
        'paid_amount' => 0,
    ]);

    $response = guiDongBo($this->thuNgan, [
        opThuTienMat((string) Str::uuid(), $session->uuid, 150_000),
    ], deviceId: 'pos-02')->assertOk();

    $ketQua = $response->json('results.0');
    expect($ketQua['status'])->toBe('conflict');

    $conflict = SyncConflict::query()->findOrFail($ketQua['conflict_id']);
    expect($conflict->conflict_kind)->toBe(ConflictKind::CaDaDong->value)
        ->and($conflict->auto_action)->toBe('khong_lam_gi')
        ->and($conflict->message_vi)->not->toBeEmpty();

    expect(Payment::query()->where('table_session_id', $session->id)->count())->toBe(0);
});

it('dòng 8: giá món đổi giữa lúc gọi và lúc đồng bộ — ghi theo giá server, vẫn báo người', function () {
    $ban = DiningTable::factory()->create();
    $luot = (string) Str::uuid();
    $opMo = (string) Str::uuid();

    guiDongBo($this->thuNgan, [opMoBan($opMo, $luot, [$ban->id], $ban->id)])->assertOk();

    $opMon = (string) Str::uuid();
    $response = guiDongBo($this->thuNgan, [
        opGoiMon($opMon, $luot, $this->mon, $this->bienThe, clientUnitPrice: 20_000),
    ], deviceId: 'pos-02')->assertOk();

    $ketQua = $response->json('results.0');
    expect($ketQua['status'])->toBe('applied');

    $session = TableSession::query()->where('uuid', $luot)->sole();
    expect($session->subtotal_amount)->toBe(25_000); // giá server (bienThe->price), không phải 20.000 client gửi

    $conflict = SyncConflict::query()->where('table_session_id', $session->id)->sole();
    expect($conflict->conflict_kind)->toBe(ConflictKind::GiaLech->value)
        ->and($conflict->auto_action)->toBe('dung_gia_server')
        ->and($conflict->is_urgent)->toBeFalse()
        ->and($conflict->message_vi)->not->toBeEmpty();
});

it('dòng 9: gọi món vào lượt khách đã bị huỷ — không nhận, gom cụm', function () {
    $session = TableSession::factory()->withTable()->create([
        'shift_id' => $this->ca->id,
        'status' => TableSessionStatus::Void,
        'voided_at' => now(),
        'void_reason' => 'Khách bỏ về',
    ]);

    $response = guiDongBo($this->thuNgan, [
        opGoiMon((string) Str::uuid(), $session->uuid, $this->mon, $this->bienThe),
    ], deviceId: 'pos-02')->assertOk();

    $ketQua = $response->json('results.0');
    expect($ketQua['status'])->toBe('conflict');

    $conflict = SyncConflict::query()->findOrFail($ketQua['conflict_id']);
    expect($conflict->conflict_kind)->toBe(ConflictKind::LuotDaHuy->value)
        ->and($conflict->message_vi)->not->toBeEmpty();

    expect(Order::query()->where('table_session_id', $session->id)->count())->toBe(0);
});

it('dòng 10: thiếu thao tác gốc — hoãn 4 lần rồi thành xung đột ở lần thứ 5', function () {
    $opUuid = (string) Str::uuid();
    $luotKhongTonTai = (string) Str::uuid();

    for ($lan = 1; $lan <= 4; $lan++) {
        $response = guiDongBo($this->thuNgan, [
            opGoiMon($opUuid, $luotKhongTonTai, $this->mon, $this->bienThe),
        ], deviceId: 'pos-02')->assertOk();

        expect($response->json('results.0.status'))->toBe('deferred');
    }

    $response = guiDongBo($this->thuNgan, [
        opGoiMon($opUuid, $luotKhongTonTai, $this->mon, $this->bienThe),
    ], deviceId: 'pos-02')->assertOk();

    $ketQua = $response->json('results.0');
    expect($ketQua['status'])->toBe('conflict');

    $conflict = SyncConflict::query()->findOrFail($ketQua['conflict_id']);
    expect($conflict->conflict_kind)->toBe(ConflictKind::ThieuThaoTacGoc->value)
        ->and($conflict->message_vi)->not->toBeEmpty();
});
