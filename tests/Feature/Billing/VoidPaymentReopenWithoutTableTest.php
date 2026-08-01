<?php

declare(strict_types=1);

use App\Domain\Billing\Enums\PaymentMethod;
use App\Domain\Billing\Models\Payment;
use App\Domain\Ordering\Enums\TableSessionStatus;
use App\Domain\Ordering\Models\DiningTable;
use App\Domain\Ordering\Models\TableSession;
use App\Domain\Ordering\Models\TableSessionTable;
use App\Domain\Staffing\Models\Shift;
use App\Domain\Staffing\Models\User;
use Illuminate\Support\Str;

/**
 * Ngoại lệ của bất biến B2 (xem docs/viec-ton.md, mục Bước 11): huỷ phiếu
 * thu của một lượt khách ĐÃ ĐÓNG có thể mở lại lượt khách đó dù bàn cũ đã
 * có khách MỚI ngồi vào — lượt khách mở lại không chiếm bàn nào, nhưng vẫn
 * phải thu tiền và đóng lại được bình thường.
 */
it('B2 có ngoại lệ hợp lệ: lượt khách mở lại sau huỷ phiếu thu có thể không chiếm bàn nào, không đụng lượt khách mới ở bàn cũ', function () {
    $ca = Shift::factory()->open()->create();
    $thuNgan = User::factory()->cashier()->create();
    $ban5 = DiningTable::factory()->indoorTable(5)->create();

    // Bước 1: mở bàn 5, gọi món (mô phỏng bằng tổng tiền có sẵn), thu tiền, đóng lượt khách.
    $luotCu = TableSession::factory()->for($ca, 'shift')->create([
        'subtotal_amount' => 300_000, 'total_amount' => 300_000, 'paid_amount' => 0, 'status' => TableSessionStatus::Open,
    ]);
    TableSessionTable::factory()->for($luotCu)->for($ban5, 'diningTable')->create(['is_primary' => true]);

    test()->postJson(
        "/api/v1/table-sessions/{$luotCu->id}/payments",
        ['uuid' => (string) Str::uuid(), 'method' => PaymentMethod::Cash->value, 'amount' => 300_000, 'tendered_amount' => 300_000],
        array_merge(authHeaderFor($thuNgan), ['Idempotency-Key' => (string) Str::uuid()])
    )->assertCreated();

    $luotCu->refresh();
    expect($luotCu->status)->toBe(TableSessionStatus::Closed);
    expect($luotCu->tables()->whereNull('detached_at')->count())->toBe(0);

    // Bước 2: bàn 5 có lượt khách MỚI ngồi vào.
    $luotMoi = TableSession::factory()->for($ca, 'shift')->create([
        'subtotal_amount' => 150_000, 'total_amount' => 150_000, 'paid_amount' => 0, 'status' => TableSessionStatus::Open,
    ]);
    TableSessionTable::factory()->for($luotMoi)->for($ban5, 'diningTable')->create(['is_primary' => true]);

    // Bước 3: huỷ phiếu thu của lượt khách cũ — lượt cũ mở lại, KHÔNG chiếm bàn nào,
    // lượt khách mới ở bàn 5 không bị ảnh hưởng gì.
    $phieuThuCu = Payment::query()->where('table_session_id', $luotCu->id)->sole();

    test()->postJson(
        "/api/v1/payments/{$phieuThuCu->id}/void",
        ['reason' => 'Khách khiếu nại, thu nhầm'],
        array_merge(authHeaderFor($thuNgan), ['Idempotency-Key' => (string) Str::uuid()])
    )->assertOk();

    $luotCu->refresh();
    expect($luotCu->status)->toBe(TableSessionStatus::Billing)
        ->and($luotCu->paid_amount)->toBe(0)
        ->and($luotCu->closed_at)->toBeNull()
        ->and($luotCu->tables()->whereNull('detached_at')->count())->toBe(0); // B2: mở nhưng không chiếm bàn nào

    $luotMoi->refresh();
    expect($luotMoi->status)->toBe(TableSessionStatus::Open)
        ->and($luotMoi->paid_amount)->toBe(0)
        ->and($luotMoi->tables()->whereNull('detached_at')->pluck('dining_table_id')->all())->toBe([$ban5->id]);

    // Bước 4: thu tiền lại cho lượt khách cũ — thành công dù không có bàn nào.
    test()->postJson(
        "/api/v1/table-sessions/{$luotCu->id}/payments",
        ['uuid' => (string) Str::uuid(), 'method' => PaymentMethod::Cash->value, 'amount' => 300_000, 'tendered_amount' => 300_000],
        array_merge(authHeaderFor($thuNgan), ['Idempotency-Key' => (string) Str::uuid()])
    )->assertCreated();

    // Bước 5: thu đủ tự đóng lại — đóng lượt khách không bàn vẫn thành công.
    $luotCu->refresh();
    expect($luotCu->status)->toBe(TableSessionStatus::Closed)
        ->and($luotCu->paid_amount)->toBe(300_000);

    // Bàn 5 của lượt khách mới không hề bị đụng tới trong suốt quá trình trên.
    expect($luotMoi->refresh()->tables()->whereNull('detached_at')->count())->toBe(1);
});
