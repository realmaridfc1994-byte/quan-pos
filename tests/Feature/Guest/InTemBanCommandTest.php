<?php

declare(strict_types=1);

/**
 * Phase 4 — vẽ tem QR để in dán lên bàn.
 *
 * Test quan trọng nhất trong file này là cái thứ ba: LỆNH IN KHÔNG ĐƯỢC ĐỔI
 * MÃ BÀN. Mã sinh đúng một lần lúc tạo bàn và sống mãi; lệnh in mà lỡ sinh mã
 * mới thì mọi tem đang dán trên bàn thành vô dụng, và không ai phát hiện ra
 * cho tới khi khách quét không được giữa giờ cao điểm.
 */

use App\Domain\Ordering\Models\DiningTable;
use App\Support\MaBanCongKhai;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    config(['pos.khach_tu_goi.duong_dan_goc' => 'http://192.168.1.10']);
});

it('vẽ tem cho toàn bộ bàn đang hoạt động', function () {
    DiningTable::factory()->create(['code' => 'B01', 'name' => 'Bàn 1']);
    DiningTable::factory()->create(['code' => 'B02', 'name' => 'Bàn 2']);

    expect(Artisan::call('pos:in-tem-ban --tat-ca'))->toBe(0);

    Storage::disk('local')->assertExists('tem-ban/B01.svg');
    Storage::disk('local')->assertExists('tem-ban/B02.svg');
});

it('vẽ tem cho đúng bàn được chọn, không đụng bàn khác', function () {
    DiningTable::factory()->create(['code' => 'B01']);
    DiningTable::factory()->create(['code' => 'B02']);

    Artisan::call('pos:in-tem-ban --ban=B01');

    Storage::disk('local')->assertExists('tem-ban/B01.svg');
    Storage::disk('local')->assertMissing('tem-ban/B02.svg');
});

it('IN LẠI KHÔNG ĐỔI MÃ BÀN — tem đã dán trên bàn vẫn dùng được', function () {
    $ban = DiningTable::factory()->create(['code' => 'B01']);
    $maBanDau = $ban->public_code;

    Artisan::call('pos:in-tem-ban --tat-ca');
    Artisan::call('pos:in-tem-ban --tat-ca');
    Artisan::call('pos:in-tem-ban --ban=B01');

    expect($ban->refresh()->public_code)->toBe($maBanDau);

    // Và tem vẽ ra vẫn trỏ đúng vào mã cũ đó.
    expect(Storage::disk('local')->get('tem-ban/B01.svg'))->toContain($maBanDau);
});

it('tem chứa đúng đường link của bàn đó, không phải bàn khác', function () {
    $banA = DiningTable::factory()->create(['code' => 'B01']);
    $banB = DiningTable::factory()->create(['code' => 'B02']);

    Artisan::call('pos:in-tem-ban --tat-ca');

    $temA = Storage::disk('local')->get('tem-ban/B01.svg');

    expect($temA)->toContain(MaBanCongKhai::duongDan($banA->public_code))
        ->and($temA)->not->toContain($banB->public_code);
});

it('tem là SVG hợp lệ, đọc được bằng trình duyệt', function () {
    DiningTable::factory()->create(['code' => 'B01', 'name' => 'Bàn sân 1']);

    Artisan::call('pos:in-tem-ban --tat-ca');
    $tem = Storage::disk('local')->get('tem-ban/B01.svg');

    expect(simplexml_load_string($tem))->not->toBeFalse()
        // Chỉ MỘT dòng khai báo XML — lồng hai tài liệu vào nhau là file hỏng.
        ->and(substr_count($tem, '<?xml'))->toBe(1)
        // Tên bàn in to bên dưới mã để nhân viên dán không nhầm bàn.
        ->and($tem)->toContain('Bàn sân 1')
        ->and($tem)->toContain('B01');
});

it('tên bàn có dấu nháy không làm hỏng file', function () {
    DiningTable::factory()->create(['code' => 'B09', 'name' => 'Bàn "VIP" & sân']);

    Artisan::call('pos:in-tem-ban --tat-ca');

    expect(simplexml_load_string(Storage::disk('local')->get('tem-ban/B09.svg')))->not->toBeFalse();
});

it('bàn đã dẹp thì không in tem', function () {
    DiningTable::factory()->create(['code' => 'B01', 'is_active' => false]);

    expect(Artisan::call('pos:in-tem-ban --tat-ca'))->toBe(1);
    Storage::disk('local')->assertMissing('tem-ban/B01.svg');
});

it('không chọn gì thì lệnh từ chối chạy, không in bừa cả quán', function () {
    DiningTable::factory()->create(['code' => 'B01']);

    expect(Artisan::call('pos:in-tem-ban'))->toBe(1);

    expect(Artisan::output())->toContain('Phải chọn');
    Storage::disk('local')->assertMissing('tem-ban/B01.svg');
});

it('in cảnh báo địa chỉ máy quán TRƯỚC khi vẽ, để đối chiếu trước lúc tốn giấy', function () {
    DiningTable::factory()->create(['code' => 'B01']);

    Artisan::call('pos:in-tem-ban --tat-ca');

    expect(Artisan::output())
        ->toContain('http://192.168.1.10')
        ->toContain('PHẢI IN LẠI TOÀN BỘ TEM');
});
