<?php

declare(strict_types=1);

/**
 * Phase 4 — LÁ CHẮN: kênh công khai của khách chỉ trả về đúng những trường
 * nằm trong DANH SÁCH TRẮNG.
 *
 * Vì sao không kiểm bằng danh sách đen ("không được có giá vốn, không được có
 * has_cost..."): danh sách đen chỉ chặn được những cái người viết NHỚ RA lúc
 * viết. Thêm một cột mới vào bảng ba tháng sau là nó lặng lẽ ra ngoài, không
 * test nào đỏ.
 *
 * Test này khẳng định danh sách khoá trả ra BẰNG ĐÚNG danh sách mong đợi —
 * thêm một trường là đỏ ngay, buộc người thêm phải cân nhắc có nên cho khách
 * thấy không, và ghi quyết định đó vào lịch sử code.
 */

use App\Domain\Ordering\Enums\TableSessionStatus;
use App\Domain\Ordering\Models\DiningTable;
use App\Domain\Ordering\Models\TableSession;
use App\Domain\Staffing\Models\User;

require_once __DIR__.'/../../Fixtures/KhachQuetQr.php';

/** ĐÚNG những trường khách được thấy. Sửa danh sách này là một quyết định. */
const TRUONG_KHACH_DUOC_THAY_KHI_CAP = [
    'token',
    'ma_doi_chieu',
    'ten_ban',
    'khu_vuc',
    'het_han_luc',
    'con_lai_giay',
];

const TRUONG_KHACH_DUOC_THAY_KHI_XEM = [
    'ma_doi_chieu',
    'ten_ban',
    'khu_vuc',
    'het_han_luc',
    'con_lai_giay',
];

it('lúc cấp token: trả về ĐÚNG danh sách trắng, không thừa một trường nào', function () {
    [$ban] = banDangCoKhach();

    $data = quetMaQr($ban->public_code)->assertCreated()->json('data');

    expect(array_keys($data))->toBe(TRUONG_KHACH_DUOC_THAY_KHI_CAP);
});

it('lúc xem lại: trả về ĐÚNG danh sách trắng, và KHÔNG phát lại chuỗi token', function () {
    [$token] = tokenChoBanDangCoKhach();

    $data = xemPhien($token)->assertOk()->json('data');

    expect(array_keys($data))->toBe(TRUONG_KHACH_DUOC_THAY_KHI_XEM)
        // Token chỉ phát đúng một lần lúc cấp; các lần sau client tự giữ.
        ->and($data)->not->toHaveKey('token');
});

it('không lọt một trường nội bộ nào ra kênh công khai', function () {
    $ban = DiningTable::factory()->create(['code' => 'B07', 'seats' => 8, 'sort_order' => 3]);
    $session = TableSession::factory()->create([
        'status' => TableSessionStatus::Open,
        'subtotal_amount' => 1_234_000,
        'discount_amount' => 34_000,
        'discount_reason' => 'Khách quen',
        'total_amount' => 1_200_000,
        'paid_amount' => 500_000,
    ]);
    $session->tables()->create([
        'dining_table_id' => $ban->id,
        'is_primary' => true,
        'attached_at' => now(),
        'attached_by_user_id' => User::factory()->create()->id,
    ]);

    $tho = quetMaQr($ban->public_code)->assertCreated()->getContent();

    // Không id nào, không đồng tiền nào, không mã nội bộ nào.
    foreach ([
        'table_session_id', 'session_id', 'shift_id', 'dining_table_id',
        'cost', 'has_cost', 'gia_von', 'profit',
        'subtotal_amount', 'discount_amount', 'total_amount', 'paid_amount',
        '1234000', '1200000', '500000',
        'public_code', 'opened_by_user_id', 'is_primary', 'sort_order', 'seats',
        (string) $session->code,
    ] as $khongDuocCo) {
        expect($tho)->not->toContain($khongDuocCo);
    }

    // Mã bàn in trên tem (B07) cũng không trả ra: khách không cần biết, mà nó
    // là thứ dùng để đối chiếu nội bộ giữa nhân viên với nhau.
    expect($tho)->not->toContain('B07');
});

it('tên bàn và khu vực thì được trả — khách phải biết mình đang ngồi bàn nào', function () {
    [$ban] = banDangCoKhach();

    quetMaQr($ban->public_code)
        ->assertCreated()
        ->assertJsonPath('data.ten_ban', 'Bàn 3')
        ->assertJsonPath('data.khu_vuc', 'Sân');
});
