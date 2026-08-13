<?php

declare(strict_types=1);

/**
 * Phase 4 — cơ chế token cho khách quét mã QR trên bàn.
 *
 * SỬA THIẾT KẾ so với bản đồ gốc: bản gốc định dán một token CỐ ĐỊNH lên bàn.
 * Đó là lỗ hổng — ai chụp ảnh mã QR một lần là gọi món được mãi mãi, từ nhà,
 * và muốn thu hồi thì phải đi bóc lại tem của cả 15 bàn.
 *
 * Thiết kế đang chạy: mã QR chỉ là MÃ ĐỊNH DANH BÀN (công khai, không cấp
 * quyền gì). Muốn gọi món phải đổi nó lấy token phiên ngắn hạn, và server chỉ
 * cấp khi bàn ĐANG CÓ KHÁCH NGỒI THẬT. Bàn đóng là token chết ngay.
 */

use App\Domain\Ordering\Enums\TableSessionStatus;
use App\Domain\Ordering\Models\DiningTable;
use App\Domain\Ordering\Support\GuestSessionToken;
use App\Domain\Staffing\Models\User;
use App\Support\MaBanCongKhai;
use Illuminate\Support\Carbon;

require_once __DIR__.'/../../Fixtures/KhachQuetQr.php';

// ── Đổi mã bàn lấy token ─────────────────────────────────────────────────

it('bàn đang có khách thì quét mã QR đổi được token', function () {
    [$ban] = banDangCoKhach();

    quetMaQr($ban->public_code)
        ->assertCreated()
        ->assertJsonPath('data.ten_ban', 'Bàn 3')
        ->assertJsonPath('data.khu_vuc', 'Sân')
        ->assertJsonStructure(['data' => ['token', 'ma_doi_chieu', 'het_han_luc', 'con_lai_giay']]);
});

it('bàn KHÔNG có phiên mở thì từ chối, không cấp token, trả mã lỗi để client hiện gọi phục vụ', function () {
    $ban = DiningTable::factory()->create();

    quetMaQr($ban->public_code)
        ->assertStatus(409)
        ->assertJsonPath('code', 'TABLE_NOT_OPEN')
        ->assertJsonMissingPath('data.token');
});

it('bàn đã đóng phiên rồi thì cũng không cấp token nữa', function () {
    [$ban, $session] = banDangCoKhach();
    $session->update(['status' => TableSessionStatus::Closed, 'closed_at' => now()]);

    quetMaQr($ban->public_code)->assertStatus(409)->assertJsonPath('code', 'TABLE_NOT_OPEN');
});

it('bàn đã bấm in tạm tính thì không cấp token mới — khách đòi tính tiền rồi', function () {
    [$ban, $session] = banDangCoKhach();
    $session->update(['status' => TableSessionStatus::Billing]);

    quetMaQr($ban->public_code)->assertStatus(409)->assertJsonPath('code', 'TABLE_NOT_OPEN');
});

it('bàn đã dẹp (tắt cờ) trả về đúng câu như bàn chưa có khách, không lộ là bàn có thật', function () {
    [$ban] = banDangCoKhach();
    $ban->update(['is_active' => false]);

    $daDep = quetMaQr($ban->public_code)->assertStatus(409);
    $khongCo = quetMaQr(MaBanCongKhai::sinh())->assertStatus(409);

    expect($daDep->json('message'))->toBe($khongCo->json('message'))
        ->and($daDep->json('code'))->toBe($khongCo->json('code'));
});

it('mã bàn sai định dạng bị chặn ngay ở cửa', function (string $maSai) {
    quetMaQr($maSai)->assertUnprocessable();
})->with(['', 'B01', 'ngan-qua', 'co-gach-ngang-va-du-22ky']);

// ── Mã bàn không đoán được ───────────────────────────────────────────────

it('mã bàn không đoán được từ id tuần tự hay từ mã bàn in trên tem', function () {
    $ban = DiningTable::factory()->create(['code' => 'B01']);

    expect($ban->public_code)->toHaveLength(MaBanCongKhai::DO_DAI)
        ->and($ban->public_code)->not->toContain((string) $ban->id)
        ->and($ban->public_code)->not->toBe($ban->code)
        ->and($ban->public_code)->not->toContain('B01');

    // Và hai bàn liền nhau không ra hai mã liền nhau.
    $ban2 = DiningTable::factory()->create();
    expect($ban2->public_code)->not->toBe($ban->public_code);
});

it('200 bàn sinh ra 200 mã khác nhau, không trùng cái nào', function () {
    $ma = collect(range(1, 200))->map(fn (): string => MaBanCongKhai::sinh());

    expect($ma->unique())->toHaveCount(200);
});

// ── Token chết khi bàn đóng ──────────────────────────────────────────────

it('token của phiên đã đóng thì bị từ chối', function () {
    [$token, , $session] = tokenChoBanDangCoKhach();

    xemPhien($token)->assertOk();

    $session->update(['status' => TableSessionStatus::Closed, 'closed_at' => now()]);

    xemPhien($token)
        ->assertUnauthorized()
        ->assertJsonPath('code', 'GUEST_SESSION_CLOSED');
});

it('token chết ngay cả khi chưa hết hạn giờ — bàn đóng là chết', function () {
    [$token, , $session] = tokenChoBanDangCoKhach();

    // Mới cấp được một phút, còn thừa hạn giờ.
    $this->travel(1)->minutes();
    $session->update([
        'status' => TableSessionStatus::Void,
        'voided_at' => now(),
        'voided_by_user_id' => User::factory()->create()->id,
        'void_reason' => 'Khách bỏ về không trả tiền',
    ]);

    xemPhien($token)->assertUnauthorized()->assertJsonPath('code', 'GUEST_SESSION_CLOSED');
});

// ── Token hết hạn theo giờ, độc lập với việc bàn đóng ────────────────────

it('hết hạn giờ thì token chết dù bàn vẫn còn mở', function () {
    [$token, , $session] = tokenChoBanDangCoKhach();

    $this->travel(181)->minutes();

    // Bàn vẫn mở nguyên.
    expect($session->refresh()->status)->toBe(TableSessionStatus::Open);

    xemPhien($token)->assertUnauthorized()->assertJsonPath('code', 'GUEST_TOKEN_EXPIRED');
});

it('còn trong hạn thì vẫn dùng được', function () {
    [$token] = tokenChoBanDangCoKhach();

    $this->travel(179)->minutes();

    xemPhien($token)->assertOk();
});

// ── Token bàn A không dùng được cho bàn B ────────────────────────────────

it('token bàn A không dùng được sau khi bàn A bị nhả khỏi phiên', function () {
    [$token, $banA, $session] = tokenChoBanDangCoKhach();

    // Thu ngân dời khách sang bàn khác: nhả bàn A, gắn bàn B.
    $session->tables()->where('dining_table_id', $banA->id)->update(['detached_at' => now()]);
    $banB = DiningTable::factory()->create();
    $session->tables()->create([
        'dining_table_id' => $banB->id,
        'is_primary' => true,
        'attached_at' => now(),
        'attached_by_user_id' => User::factory()->create()->id,
    ]);

    xemPhien($token)->assertUnauthorized()->assertJsonPath('code', 'GUEST_SESSION_CLOSED');
});

it('token của bàn A ghi đúng bàn A, không phải bàn B', function () {
    [$tokenA, $banA] = tokenChoBanDangCoKhach();
    [$tokenB, $banB] = tokenChoBanDangCoKhach();

    expect(GuestSessionToken::doc($tokenA)->diningTableId)->toBe($banA->id)
        ->and(GuestSessionToken::doc($tokenB)->diningTableId)->toBe($banB->id)
        ->and($tokenA)->not->toBe($tokenB);

    xemPhien($tokenA)->assertOk()->assertJsonPath('data.ten_ban', $banA->name);
    xemPhien($tokenB)->assertOk()->assertJsonPath('data.ten_ban', $banB->name);
});

// ── Token hỏng / thiếu ───────────────────────────────────────────────────

it('không gửi token thì không vào được', function () {
    $this->getJson('/api/v1/guest/session')
        ->assertUnauthorized()
        ->assertJsonPath('code', 'GUEST_TOKEN_INVALID');
});

it('token bịa hoặc sửa một ký tự đều không đọc được', function () {
    [$token] = tokenChoBanDangCoKhach();

    xemPhien('bia-dat-hoan-toan')->assertUnauthorized()->assertJsonPath('code', 'GUEST_TOKEN_INVALID');
    xemPhien(substr($token, 0, -1).'X')->assertUnauthorized()->assertJsonPath('code', 'GUEST_TOKEN_INVALID');
});

it('token không lộ id nào của hệ thống ra ngoài', function () {
    [$token, , $session] = tokenChoBanDangCoKhach();

    // Lớp ngoài của token chỉ là ba phần kỹ thuật của Laravel; phần `value`
    // là bản đã mã hoá, không đọc được nếu không có khoá của server.
    $lopNgoai = json_decode((string) base64_decode($token, true), true);

    expect($lopNgoai)->toBeArray()
        ->toHaveKeys(['iv', 'value', 'mac'])
        // Nội dung thật (khoá "s" giữ table_session_id) KHÔNG nằm dạng chữ
        // đọc được ở bất cứ đâu trong token.
        ->and((string) base64_decode($token, true))->not->toContain('"s"')
        ->and((string) base64_decode($token, true))->not->toContain('table_session');

    // Đọc được nội dung là việc CHỈ server làm được.
    expect(GuestSessionToken::doc($token)->tableSessionId)->toBe($session->id);

    // Mã đối chiếu chỉ là dấu vân tay 12 ký tự hệ 16 — không suy ngược ra gì.
    $maDoiChieu = xemPhien($token)->json('data.ma_doi_chieu');
    expect($maDoiChieu)->toHaveLength(12)
        ->toMatch('/^[0-9a-f]{12}$/');
});

// ── Chặn gọi dồn dập ─────────────────────────────────────────────────────

it('đổi mã quá 10 lần một phút thì bị chặn', function () {
    $ban = DiningTable::factory()->create();

    for ($i = 0; $i < 10; $i++) {
        quetMaQr($ban->public_code)->assertStatus(409);
    }

    quetMaQr($ban->public_code)
        ->assertStatus(429)
        ->assertJsonPath('code', 'TOO_MANY_ATTEMPTS');
});

it('gọi quá 60 lần một phút bằng cùng một token thì bị chặn', function () {
    [$token] = tokenChoBanDangCoKhach();

    for ($i = 0; $i < 60; $i++) {
        xemPhien($token)->assertOk();
    }

    xemPhien($token)->assertStatus(429)->assertJsonPath('code', 'TOO_MANY_ATTEMPTS');
});

it('bộ đếm tính theo TỪNG TOKEN, bàn này gọi nhiều không chặn bàn kia', function () {
    [$tokenA] = tokenChoBanDangCoKhach();
    [$tokenB] = tokenChoBanDangCoKhach();

    for ($i = 0; $i < 60; $i++) {
        xemPhien($tokenA)->assertOk();
    }

    xemPhien($tokenA)->assertStatus(429);
    // Bàn kia vẫn gọi được bình thường — cả quán dùng chung wifi, đếm theo
    // máy khách sẽ chặn oan cả bàn bên cạnh.
    xemPhien($tokenB)->assertOk();
});

// ── Đường link in vào mã QR ──────────────────────────────────────────────

it('dựng đúng đường link để in vào mã QR', function () {
    config(['pos.khach_tu_goi.duong_dan_goc' => 'http://192.168.1.10/']);

    expect(MaBanCongKhai::duongDan('abc123'))->toBe('http://192.168.1.10/g/abc123');
});

it('token ghi đúng hạn 3 tiếng theo cấu hình', function () {
    $this->travelTo(Carbon::parse('2026-08-14 18:00:00'));

    [$token] = tokenChoBanDangCoKhach();

    expect(GuestSessionToken::doc($token)->hetHanLuc->toDateTimeString())->toBe('2026-08-14 21:00:00');
});
