<?php

declare(strict_types=1);

/**
 * Phase 2 Bước 5 (sửa 04/08): xử lý xung đột đi qua hệ đăng nhập của máy POS
 * (auth:sanctum), KHÔNG qua Filament — thu ngân xử lý ngay tại quầy, không
 * đăng nhập lại. Xem docs/viec-ton.md.
 */

use App\Domain\Ordering\Models\DiningTable;
use App\Domain\Ordering\Models\TableSession;
use App\Domain\Ordering\Models\TableSessionTable;
use App\Domain\Staffing\Models\Shift;
use App\Domain\Staffing\Models\User;
use App\Domain\Sync\Enums\ConflictKind;
use App\Domain\Sync\Enums\ConflictStatus;
use App\Domain\Sync\Models\SyncConflict;
use Illuminate\Support\Str;

/** Header xác thực + một Idempotency-Key MỚI mỗi lần gọi (route resolve() bắt buộc header này). */
function headersChoResolve(User $user): array
{
    return array_merge(authHeaderFor($user), ['Idempotency-Key' => (string) Str::uuid()]);
}

it('đếm đúng số xung đột đang chờ, cho chấm đỏ trên máy POS', function () {
    $nhanVien = User::factory()->staff()->create();

    SyncConflict::factory()->count(2)->create(['status' => ConflictStatus::Pending, 'is_urgent' => false]);
    SyncConflict::factory()->create(['status' => ConflictStatus::Pending, 'is_urgent' => true]);
    SyncConflict::factory()->resolved()->create(['resolved_by_user_id' => $nhanVien->id]);

    $this->getJson('/api/v1/sync/conflicts/pending-count', authHeaderFor($nhanVien))
        ->assertOk()
        ->assertJsonPath('data.pending', 3)
        ->assertJsonPath('data.urgent', 1);
});

it('không hỏi được nếu chưa đăng nhập', function () {
    $this->getJson('/api/v1/sync/conflicts/pending-count')->assertUnauthorized();
});

it('thu ngân xử lý một xung đột NGAY qua API của máy POS, không cần đăng nhập lại', function () {
    $thuNgan = User::factory()->cashier()->create();
    $session = TableSession::factory()->closed()->withTable()->create();

    $xungDot = SyncConflict::factory()->create([
        'conflict_kind' => ConflictKind::LuotDaDong->value,
        'table_session_id' => $session->id,
        'status' => ConflictStatus::Pending,
        'options' => [
            ['key' => 'huy_mon', 'label' => 'Huỷ các món này'],
            ['key' => 'mo_luot_moi', 'label' => 'Mở lượt khách mới'],
        ],
        'payload' => ['goc' => ['type' => 'place_order', 'payload' => ['uuid' => (string) Str::uuid(), 'table_session_uuid' => $session->uuid, 'items' => []]], 'cum' => []],
    ]);

    $this->postJson("/api/v1/sync/conflicts/{$xungDot->id}/resolve", [
        'dismiss' => false,
        'resolution' => 'huy_mon',
        'note' => 'Gọi nhầm, khách đã trả bàn xong.',
    ], headersChoResolve($thuNgan))
        ->assertOk()
        ->assertJsonPath('data.status', 'resolved');

    expect($xungDot->refresh()->status)->toBe(ConflictStatus::Resolved);
});

it('xung đột thu trùng KHÔNG có mã PIN thì bị chặn, chưa xử lý', function () {
    $thuNgan = User::factory()->cashier()->create();
    $session = TableSession::factory()->closed()->withTable()->create([
        'subtotal_amount' => 380_000,
        'total_amount' => 380_000,
        'paid_amount' => 380_000,
    ]);

    $xungDot = SyncConflict::factory()->create([
        'conflict_kind' => ConflictKind::ThuTienTrung->value,
        'table_session_id' => $session->id,
        'status' => ConflictStatus::Pending,
        'options' => [['key' => 'ket_khong_thua', 'label' => 'Két không thừa']],
        'payload' => ['goc' => ['type' => 'record_payment', 'payload' => ['uuid' => (string) Str::uuid(), 'amount' => 380_000]], 'cum' => []],
    ]);

    $this->postJson("/api/v1/sync/conflicts/{$xungDot->id}/resolve", [
        'dismiss' => false,
        'resolution' => 'ket_khong_thua',
        'note' => 'Két không thừa, bỏ phiếu này.',
    ], headersChoResolve($thuNgan))
        ->assertUnprocessable();

    expect($xungDot->refresh()->status)->toBe(ConflictStatus::Pending);
});

it('xung đột thu trùng có mã PIN đúng thì xử lý được', function () {
    $thuNgan = User::factory()->cashier()->withPin('1234')->create();
    $session = TableSession::factory()->closed()->withTable()->create([
        'subtotal_amount' => 380_000,
        'total_amount' => 380_000,
        'paid_amount' => 380_000,
    ]);

    $xungDot = SyncConflict::factory()->create([
        'conflict_kind' => ConflictKind::ThuTienTrung->value,
        'table_session_id' => $session->id,
        'status' => ConflictStatus::Pending,
        'options' => [['key' => 'ket_khong_thua', 'label' => 'Két không thừa']],
        'payload' => ['goc' => ['type' => 'record_payment', 'payload' => ['uuid' => (string) Str::uuid(), 'amount' => 380_000]], 'cum' => []],
    ]);

    $this->postJson("/api/v1/sync/conflicts/{$xungDot->id}/resolve", [
        'dismiss' => false,
        'resolution' => 'ket_khong_thua',
        'note' => 'Két không thừa, bỏ phiếu này.',
        'approver_user_id' => $thuNgan->id,
        'approver_pin' => '1234',
    ], headersChoResolve($thuNgan))
        ->assertOk()
        ->assertJsonPath('data.status', 'resolved');
});

it('nhân viên phục vụ bấm xử lý bị chặn, nhưng vẫn xem được danh sách', function () {
    $nhanVien = User::factory()->staff()->create();
    $xungDot = SyncConflict::factory()->create(['status' => ConflictStatus::Pending]);

    $this->postJson("/api/v1/sync/conflicts/{$xungDot->id}/resolve", [
        'dismiss' => true,
        'note' => 'Thử xử lý.',
    ], headersChoResolve($nhanVien))
        ->assertForbidden();

    $this->getJson('/api/v1/sync/conflicts?status=pending', authHeaderFor($nhanVien))
        ->assertOk()
        ->assertJsonCount(1, 'data');

    expect($xungDot->refresh()->status)->toBe(ConflictStatus::Pending);
});

it('xử lý cùng một xung đột hai lần — lần hai không tạo thêm bản ghi nào, không đổi gì thêm', function () {
    $thuNgan = User::factory()->cashier()->create();
    $xungDot = SyncConflict::factory()->create([
        'status' => ConflictStatus::Pending,
        'options' => [['key' => 'bo_qua', 'label' => 'Bỏ qua']],
    ]);

    $this->postJson("/api/v1/sync/conflicts/{$xungDot->id}/resolve", [
        'dismiss' => true,
        'note' => 'Lần đầu.',
    ], headersChoResolve($thuNgan))->assertOk();

    $this->postJson("/api/v1/sync/conflicts/{$xungDot->id}/resolve", [
        'dismiss' => true,
        'note' => 'Lần hai, thử lại.',
    ], headersChoResolve($thuNgan))->assertUnprocessable();

    expect(SyncConflict::query()->count())->toBe(1)
        ->and($xungDot->refresh()->resolution_note)->toBe('Lần đầu.');
});

it('xử lý hết xung đột thì CloseShift không còn bị chặn nữa', function () {
    $thuNgan = User::factory()->cashier()->create();
    $ca = Shift::factory()->open()->create(['opened_by_user_id' => $thuNgan->id]);
    $ban = DiningTable::factory()->create();
    $session = TableSession::factory()->closed()->create(['shift_id' => $ca->id]);
    TableSessionTable::factory()->for($session)->create(['dining_table_id' => $ban->id, 'detached_at' => now()]);

    $xungDot = SyncConflict::factory()->create([
        'conflict_kind' => ConflictKind::LuotDaDong->value,
        'table_session_id' => $session->id,
        'status' => ConflictStatus::Pending,
        'options' => [['key' => 'huy_mon', 'label' => 'Huỷ các món này']],
        'payload' => ['goc' => ['type' => 'place_order', 'payload' => ['uuid' => (string) Str::uuid(), 'table_session_uuid' => $session->uuid, 'items' => []]], 'cum' => []],
    ]);

    $this->postJson("/api/v1/shifts/{$ca->id}/close", [
        'counted_cash' => $ca->opening_cash,
    ], headersChoResolve($thuNgan))->assertUnprocessable();

    $this->postJson("/api/v1/sync/conflicts/{$xungDot->id}/resolve", [
        'dismiss' => false,
        'resolution' => 'huy_mon',
        'note' => 'Gọi nhầm.',
    ], headersChoResolve($thuNgan))->assertOk();

    $this->postJson("/api/v1/shifts/{$ca->id}/close", [
        'counted_cash' => $ca->opening_cash,
    ], headersChoResolve($thuNgan))->assertOk();

    expect($ca->refresh()->status->value)->toBe('closed');
});
