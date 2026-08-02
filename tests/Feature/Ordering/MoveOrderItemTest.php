<?php

declare(strict_types=1);

use App\Domain\Ordering\Actions\RecalculateSessionSubtotal;
use App\Domain\Ordering\Enums\OrderItemStatus;
use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Enums\TableSessionStatus;
use App\Domain\Ordering\Models\Order;
use App\Domain\Ordering\Models\OrderItem;
use App\Domain\Ordering\Models\TableSession;
use App\Domain\Staffing\Models\Shift;
use App\Domain\Staffing\Models\User;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

function chuyenMon(User $user, TableSession $nguon, TableSession $dich, array $orderItemIds): TestResponse
{
    return test()->postJson(
        "/api/v1/table-sessions/{$nguon->id}/move-items",
        [
            'target_table_session_id' => $dich->id,
            'order_item_ids' => $orderItemIds,
        ],
        array_merge(authHeaderFor($user), ['Idempotency-Key' => (string) Str::uuid()])
    );
}

beforeEach(function () {
    $ca = Shift::factory()->open()->create();
    $this->staff = User::factory()->staff()->create();

    $this->nguon = TableSession::factory()->withTable()->create(['shift_id' => $ca->id, 'status' => TableSessionStatus::Open]);
    $this->dich = TableSession::factory()->withTable()->create(['shift_id' => $ca->id, 'status' => TableSessionStatus::Open]);

    $donNguon = Order::factory()->for($this->nguon, 'tableSession')->bar()->create(['status' => OrderStatus::Sent]);
    $this->dongMonNguon = collect(range(1, 3))->map(
        fn () => OrderItem::factory()->for($donNguon)->create([
            'unit_price' => 20_000,
            'options_amount' => 0,
            'quantity' => 1,
            'status' => OrderItemStatus::Ordered,
        ])
    );

    $donDich = Order::factory()->for($this->dich, 'tableSession')->bar()->create(['status' => OrderStatus::Sent]);
    OrderItem::factory()->for($donDich)->create([
        'unit_price' => 10_000,
        'options_amount' => 0,
        'quantity' => 1,
        'status' => OrderItemStatus::Ordered,
    ]);

    app(RecalculateSessionSubtotal::class)->handle($this->nguon);
    app(RecalculateSessionSubtotal::class)->handle($this->dich);
});

it('chuyển 1 dòng món sang lượt khách khác — tổng tạm tính hai bên không đổi', function () {
    $tongTruoc = $this->nguon->refresh()->subtotal_amount + $this->dich->refresh()->subtotal_amount;
    expect($tongTruoc)->toBe(70_000);

    $idChuyen = $this->dongMonNguon->take(1)->pluck('id')->all();

    $response = chuyenMon($this->staff, $this->nguon, $this->dich, $idChuyen)->assertOk();

    $tamTinhNguon = $response->json('data.source.subtotal_amount');
    $tamTinhDich = $response->json('data.target.subtotal_amount');

    expect($tamTinhNguon)->toBe(40_000)
        ->and($tamTinhDich)->toBe(30_000)
        ->and($tamTinhNguon + $tamTinhDich)->toBe($tongTruoc);
});

it('dòng món chuyển sang thuộc đúng một phiếu của lượt khách đích, không nhân đôi', function () {
    $soDongTruoc = OrderItem::query()->count();
    $idChuyen = $this->dongMonNguon->take(2)->pluck('id')->all();

    chuyenMon($this->staff, $this->nguon, $this->dich, $idChuyen)->assertOk();

    expect(OrderItem::query()->count())->toBe($soDongTruoc);

    foreach ($idChuyen as $id) {
        $dong = OrderItem::query()->with('order')->findOrFail($id);
        expect($dong->order->table_session_id)->toBe($this->dich->id);
    }
});

it('không chuyển được món sang chính lượt khách đang đứng', function () {
    $idChuyen = $this->dongMonNguon->take(1)->pluck('id')->all();

    chuyenMon($this->staff, $this->nguon, $this->nguon, $idChuyen)->assertUnprocessable();
});

it('không chuyển được dòng món đã huỷ', function () {
    $dongDaHuy = $this->dongMonNguon->first();
    $dongDaHuy->update(['status' => OrderItemStatus::Cancelled, 'cancelled_at' => now(), 'cancelled_by_user_id' => $this->staff->id, 'cancel_reason' => 'Test']);

    chuyenMon($this->staff, $this->nguon, $this->dich, [$dongDaHuy->id])->assertUnprocessable();
});

it('không chuyển được khi lượt khách đích đã đóng', function () {
    $this->dich->refresh();
    $this->dich->update([
        'status' => TableSessionStatus::Closed,
        'closed_at' => now(),
        'closed_by_user_id' => $this->staff->id,
        'paid_amount' => $this->dich->total_amount,
    ]);

    $idChuyen = $this->dongMonNguon->take(1)->pluck('id')->all();

    chuyenMon($this->staff, $this->nguon, $this->dich, $idChuyen)->assertUnprocessable();
});

it('không chuyển được khi một trong hai lượt khách đã thu tiền một phần', function () {
    $this->nguon->update(['paid_amount' => 10_000]);

    $idChuyen = $this->dongMonNguon->take(1)->pluck('id')->all();

    chuyenMon($this->staff, $this->nguon, $this->dich, $idChuyen)->assertUnprocessable();
});

it('bếp không có quyền chuyển món giữa hai lượt khách', function () {
    $bep = User::factory()->kitchen()->create();
    $idChuyen = $this->dongMonNguon->take(1)->pluck('id')->all();

    chuyenMon($bep, $this->nguon, $this->dich, $idChuyen)->assertForbidden();
});
