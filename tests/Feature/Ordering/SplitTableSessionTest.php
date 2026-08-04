<?php

declare(strict_types=1);

use App\Domain\Ordering\Actions\RecalculateSessionSubtotal;
use App\Domain\Ordering\Enums\OrderItemStatus;
use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Enums\TableSessionStatus;
use App\Domain\Ordering\Models\DiningTable;
use App\Domain\Ordering\Models\Order;
use App\Domain\Ordering\Models\OrderItem;
use App\Domain\Ordering\Models\TableSession;
use App\Domain\Ordering\Models\TableSessionTable;
use App\Domain\Staffing\Models\Shift;
use App\Domain\Staffing\Models\User;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

function tachBan(User $user, TableSession $luot, array $orderItemIds, array $diningTableIds, int $guestCount = 2, ?string $uuid = null): TestResponse
{
    return test()->postJson(
        "/api/v1/table-sessions/{$luot->id}/split",
        [
            'uuid' => $uuid ?? (string) Str::uuid(),
            'order_item_ids' => $orderItemIds,
            'dining_table_ids' => $diningTableIds,
            'guest_count' => $guestCount,
        ],
        array_merge(authHeaderFor($user), ['Idempotency-Key' => (string) Str::uuid()])
    );
}

beforeEach(function () {
    $this->ca = Shift::factory()->open()->create();
    $this->staff = User::factory()->staff()->create();
    $this->banChinh = DiningTable::factory()->create();
    $this->banGhep = DiningTable::factory()->create();

    $this->luot = TableSession::factory()->create(['shift_id' => $this->ca->id, 'status' => TableSessionStatus::Open]);

    TableSessionTable::factory()->for($this->luot)->create([
        'dining_table_id' => $this->banChinh->id,
        'is_primary' => true,
        'attached_by_user_id' => $this->staff->id,
    ]);
    TableSessionTable::factory()->for($this->luot)->notPrimary()->create([
        'dining_table_id' => $this->banGhep->id,
        'attached_by_user_id' => $this->staff->id,
    ]);

    $this->order = Order::factory()->for($this->luot, 'tableSession')->kitchen()->create(['status' => OrderStatus::Sent]);

    $this->dongMon = collect(range(1, 5))->map(
        fn () => OrderItem::factory()->for($this->order)->create([
            'unit_price' => 25_000,
            'options_amount' => 0,
            'quantity' => 1,
            'status' => OrderItemStatus::Ordered,
        ])
    );

    app(RecalculateSessionSubtotal::class)->handle($this->luot);
});

it('tách 2 trong 5 dòng món — tổng tạm tính hai bên bằng tạm tính ban đầu', function () {
    $tamTinhTruoc = $this->luot->refresh()->subtotal_amount;
    expect($tamTinhTruoc)->toBe(125_000);

    $idChuyen = $this->dongMon->take(2)->pluck('id')->all();

    $response = tachBan($this->staff, $this->luot, $idChuyen, [$this->banGhep->id])
        ->assertCreated();

    $tamTinhCu = $response->json('data.source.subtotal_amount');
    $tamTinhMoi = $response->json('data.new.subtotal_amount');

    expect($tamTinhMoi)->toBe(50_000)
        ->and($tamTinhCu)->toBe(75_000)
        ->and($tamTinhCu + $tamTinhMoi)->toBe($tamTinhTruoc);
});

it('tách hết dòng món sang bên mới — bên cũ tạm tính 0, vẫn hợp lệ', function () {
    $idChuyen = $this->dongMon->pluck('id')->all();

    $response = tachBan($this->staff, $this->luot, $idChuyen, [$this->banGhep->id])
        ->assertCreated();

    expect($response->json('data.source.subtotal_amount'))->toBe(0)
        ->and($response->json('data.source.status'))->toBe('open')
        ->and($response->json('data.new.subtotal_amount'))->toBe(125_000);
});

it('không tách được lượt khách đã thu tiền một phần', function () {
    $this->luot->update(['paid_amount' => 50_000]);

    $idChuyen = $this->dongMon->take(1)->pluck('id')->all();

    tachBan($this->staff, $this->luot, $idChuyen, [$this->banGhep->id])
        ->assertUnprocessable();

    expect(TableSession::query()->count())->toBe(1);
});

it('B2: tách mà không gán bàn nào cho lượt mới thì bị chặn', function () {
    $idChuyen = $this->dongMon->take(1)->pluck('id')->all();

    tachBan($this->staff, $this->luot, $idChuyen, [])
        ->assertUnprocessable();

    expect(TableSession::query()->count())->toBe(1);
});

it('B1: sau tách, mỗi bàn chỉ thuộc đúng một lượt khách đang mở', function () {
    $idChuyen = $this->dongMon->take(2)->pluck('id')->all();

    $response = tachBan($this->staff, $this->luot, $idChuyen, [$this->banGhep->id])->assertCreated();
    $idLuotMoi = $response->json('data.new.id');

    $banGhepDangChiem = TableSessionTable::query()
        ->where('dining_table_id', $this->banGhep->id)
        ->whereNull('detached_at')
        ->get();
    expect($banGhepDangChiem)->toHaveCount(1)
        ->and($banGhepDangChiem->first()->table_session_id)->toBe($idLuotMoi);

    $banChinhDangChiem = TableSessionTable::query()
        ->where('dining_table_id', $this->banChinh->id)
        ->whereNull('detached_at')
        ->get();
    expect($banChinhDangChiem)->toHaveCount(1)
        ->and($banChinhDangChiem->first()->table_session_id)->toBe($this->luot->id);
});

it('quét toàn bảng: không có order_item nào bị nhân đôi, mỗi dòng chỉ thuộc đúng một lượt khách sau tách', function () {
    $soDongTruoc = OrderItem::query()->count();
    $idChuyen = $this->dongMon->take(2)->pluck('id')->all();
    $idKhongChuyen = $this->dongMon->skip(2)->pluck('id')->all();

    $response = tachBan($this->staff, $this->luot, $idChuyen, [$this->banGhep->id])->assertCreated();
    $idLuotMoi = $response->json('data.new.id');

    expect(OrderItem::query()->count())->toBe($soDongTruoc);

    foreach ($idChuyen as $id) {
        $dong = OrderItem::query()->with('order')->findOrFail($id);
        expect($dong->order->table_session_id)->toBe($idLuotMoi);
    }

    foreach ($idKhongChuyen as $id) {
        $dong = OrderItem::query()->with('order')->findOrFail($id);
        expect($dong->order->table_session_id)->toBe($this->luot->id);
    }
});

it('không tách được dòng món đã huỷ', function () {
    $dongDaHuy = $this->dongMon->first();
    $dongDaHuy->update(['status' => OrderItemStatus::Cancelled, 'cancelled_at' => now(), 'cancelled_by_user_id' => $this->staff->id, 'cancel_reason' => 'Test']);

    tachBan($this->staff, $this->luot, [$dongDaHuy->id], [$this->banGhep->id])
        ->assertUnprocessable();
});

it('Phase 2 Bước 2: gửi lại đúng uuid lượt khách mới hai lần chỉ tách một lần, không tạo thêm lượt khách', function () {
    $uuidLuotMoi = (string) Str::uuid();
    $idChuyen = $this->dongMon->take(1)->pluck('id')->all();

    $lan1 = tachBan($this->staff, $this->luot, $idChuyen, [$this->banGhep->id], uuid: $uuidLuotMoi)->assertCreated();
    $lan2 = tachBan($this->staff, $this->luot, $idChuyen, [$this->banGhep->id], uuid: $uuidLuotMoi)->assertSuccessful();

    expect($lan2->json('data.new.id'))->toBe($lan1->json('data.new.id'))
        ->and(TableSession::query()->where('uuid', $uuidLuotMoi)->count())->toBe(1);
});

it('bếp không có quyền tách bàn', function () {
    $bep = User::factory()->kitchen()->create();
    $idChuyen = $this->dongMon->take(1)->pluck('id')->all();

    tachBan($bep, $this->luot, $idChuyen, [$this->banGhep->id])->assertForbidden();
});
