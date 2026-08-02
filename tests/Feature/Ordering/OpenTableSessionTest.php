<?php

declare(strict_types=1);

use App\Domain\Ordering\Enums\TableSessionStatus;
use App\Domain\Ordering\Models\DiningTable;
use App\Domain\Ordering\Models\TableSession;
use App\Domain\Staffing\Actions\CloseShift;
use App\Domain\Staffing\DTO\CloseShiftData;
use App\Domain\Staffing\Enums\ShiftStatus;
use App\Domain\Staffing\Models\Shift;
use App\Domain\Staffing\Models\User;
use App\Support\Money;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

function moBan(User $user, array $payload = []): TestResponse
{
    $token = $user->createToken('pos-app')->plainTextToken;

    $ban = $payload['dining_table_ids'] ?? [DiningTable::factory()->create()->id];

    return test()->postJson('/api/v1/table-sessions', array_merge([
        'uuid' => (string) Str::uuid(),
        'dining_table_ids' => $ban,
        'primary_dining_table_id' => $ban[0] ?? 0,
        'guest_count' => 4,
    ], $payload), [
        'Authorization' => "Bearer {$token}",
        'Idempotency-Key' => (string) Str::uuid(),
    ]);
}

beforeEach(function () {
    Shift::factory()->open()->create();
});

it('mở lượt khách trên một bàn thành công', function () {
    $staff = User::factory()->staff()->create();
    $ban = DiningTable::factory()->create(['code' => 'B01']);

    moBan($staff, ['dining_table_ids' => [$ban->id], 'primary_dining_table_id' => $ban->id])
        ->assertCreated()
        ->assertJsonPath('data.status', 'open')
        ->assertJsonPath('data.tables.0.code', 'B01')
        ->assertJsonPath('data.tables.0.is_primary', true);

    expect(TableSession::query()->count())->toBe(1);
});

it('ghép nhiều bàn cùng lúc thành công, đúng một bàn chính (B3)', function () {
    $staff = User::factory()->staff()->create();
    $banChinh = DiningTable::factory()->create(['code' => 'B01']);
    $banPhu = DiningTable::factory()->create(['code' => 'B02']);

    $response = moBan($staff, [
        'dining_table_ids' => [$banChinh->id, $banPhu->id],
        'primary_dining_table_id' => $banChinh->id,
    ])->assertCreated();

    $tables = $response->json('data.tables');
    expect($tables)->toHaveCount(2);

    $soLuongChinh = collect($tables)->where('is_primary', true)->count();
    expect($soLuongChinh)->toBe(1);
});

it('B2: không chọn bàn nào thì bị chặn', function () {
    $staff = User::factory()->staff()->create();

    moBan($staff, ['dining_table_ids' => []])->assertUnprocessable();

    expect(TableSession::query()->count())->toBe(0);
});

it('bàn chính phải nằm trong danh sách bàn đã chọn', function () {
    $staff = User::factory()->staff()->create();
    $ban = DiningTable::factory()->create();
    $banKhac = DiningTable::factory()->create();

    moBan($staff, ['dining_table_ids' => [$ban->id], 'primary_dining_table_id' => $banKhac->id])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'DOMAIN_ERROR');

    expect(TableSession::query()->count())->toBe(0);
});

it('B1: bàn đang có khách thì không mở lượt khách mới trên bàn đó được', function () {
    $staff = User::factory()->staff()->create();
    $ban = DiningTable::factory()->create();

    moBan($staff, ['dining_table_ids' => [$ban->id], 'primary_dining_table_id' => $ban->id])->assertCreated();

    moBan($staff, ['dining_table_ids' => [$ban->id], 'primary_dining_table_id' => $ban->id])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Bàn này đang có khách.');

    expect(TableSession::query()->count())->toBe(1);
});

it('bàn đã ngưng sử dụng thì không mở được', function () {
    $staff = User::factory()->staff()->create();
    $ban = DiningTable::factory()->inactive()->create();

    moBan($staff, ['dining_table_ids' => [$ban->id], 'primary_dining_table_id' => $ban->id])
        ->assertUnprocessable();
});

it('chưa mở ca thì không mở bàn được', function () {
    Shift::query()->update(['status' => ShiftStatus::Closed, 'closed_at' => now(), 'counted_cash' => 0, 'expected_cash' => 0, 'closed_by_user_id' => User::factory()->create()->id]);

    $staff = User::factory()->staff()->create();

    moBan($staff)->assertUnprocessable()->assertJsonPath('message', 'Chưa mở ca. Phải mở ca trước khi mở bàn.');
});

it('bếp không có quyền mở bàn', function () {
    $bep = User::factory()->kitchen()->create();

    moBan($bep)->assertForbidden();
});

it('mã lượt khách đúng định dạng PH-yyyymmdd-NNNN', function () {
    $staff = User::factory()->staff()->create();

    moBan($staff)->assertCreated();

    $luot = TableSession::query()->sole();
    expect($luot->code)->toStartWith('PH-'.now()->format('Ymd').'-')
        ->and($luot->status)->toBe(TableSessionStatus::Open);
});

it('Bước 2: mở 5 lượt khách liên tiếp — cả 5 mã đúng định dạng PH-{Ymd}-{số}, đúng ngày hôm nay, không trùng nhau, không còn giữ mã tạm bằng uuid', function () {
    $staff = User::factory()->staff()->create();
    $homNay = now()->format('Ymd');
    $maDaMo = [];

    for ($i = 0; $i < 5; $i++) {
        $response = moBan($staff)->assertCreated();
        $ma = $response->json('data.code');

        expect($ma)->toMatch('/^PH-'.$homNay.'-\d+$/');
        $maDaMo[] = $ma;
    }

    expect($maDaMo)->toHaveCount(5)
        ->and(array_unique($maDaMo))->toHaveCount(5);

    // Không bản ghi nào còn giữ mã tạm (uuid cắt còn 30 ký tự) sau khi
    // transaction xong — mọi code trong bảng đều đúng định dạng PH-Ymd-số.
    foreach (TableSession::query()->pluck('code') as $ma) {
        expect($ma)->toMatch('/^PH-'.$homNay.'-\d+$/');
    }
});

it('không có lượt khách nào thì đóng ca thành công', function () {
    $ca = Shift::query()->where('status', ShiftStatus::Open)->sole();
    $chuCa = User::factory()->owner()->create();

    $caDaDong = app(CloseShift::class)->handle(new CloseShiftData(
        shiftId: $ca->id,
        countedCash: Money::fromInt($ca->opening_cash),
        note: null,
        closedByUserId: $chuCa->id,
    ));

    expect($caDaDong->status)->toBe(ShiftStatus::Closed);
});

it('CHỐNG RACE: đóng ca xong thì mở lượt khách mới ngay sau đó phải bị chặn, không được tạo lượt khách trỏ vào ca đã đóng', function () {
    $ca = Shift::query()->where('status', ShiftStatus::Open)->sole();
    $chuCa = User::factory()->owner()->create();

    app(CloseShift::class)->handle(new CloseShiftData(
        shiftId: $ca->id,
        countedCash: Money::fromInt($ca->opening_cash),
        note: null,
        closedByUserId: $chuCa->id,
    ));

    $staff = User::factory()->staff()->create();

    moBan($staff)
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Chưa mở ca. Phải mở ca trước khi mở bàn.');

    expect(TableSession::query()->where('shift_id', $ca->id)->count())->toBe(0);
});

it('BẤT BIẾN: không tồn tại lượt khách Open/Billing nào trỏ vào một ca đã Closed (quét toàn bảng)', function () {
    // Trường hợp hợp lệ 1: ca cũ đã đóng, lượt khách của nó cũng đã đóng đúng cách.
    $caCu = Shift::factory()->closed()->create();
    TableSession::factory()->closed()->create(['shift_id' => $caCu->id]);

    // Trường hợp hợp lệ 2: ca hiện tại (mở bởi beforeEach) có một lượt khách đang mở.
    $staff = User::factory()->staff()->create();
    moBan($staff)->assertCreated();

    $luotMoTroVaoCaDaDong = TableSession::query()
        ->whereIn('status', [TableSessionStatus::Open, TableSessionStatus::Billing])
        ->whereHas('shift', fn ($q) => $q->where('status', ShiftStatus::Closed))
        ->count();

    expect($luotMoTroVaoCaDaDong)->toBe(0);
});
