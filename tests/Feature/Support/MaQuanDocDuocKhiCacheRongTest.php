<?php

declare(strict_types=1);

use App\Support\CauHinhQuan;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Con gà và quả trứng.
 *
 * Từ bước 5A.4, mọi khoá cache của hệ thống sẽ mang tiền tố là mã quán. Nếu
 * việc đọc mã quán lại phải hỏi cache thì hệ thống tự cắn đuôi mình: muốn có
 * khoá phải biết mã quán, muốn biết mã quán phải mở khoá.
 *
 * Nên mã quán đọc THẲNG từ database và nhớ trong bộ nhớ của tiến trình đang
 * chạy. Nhớ như vậy an toàn vì mã quán là thứ ghi một lần, không đổi.
 */
beforeEach(function () {
    CauHinhQuan::quenMaQuanDaNho();
});

afterEach(function () {
    CauHinhQuan::quenMaQuanDaNho();
});

it('cache rỗng trơn vẫn đọc được mã quán', function () {
    Cache::store('database')->flush();
    Cache::flush();

    expect((new CauHinhQuan)->maQuan())->toBe(config('pos.ma_quan'));
});

it('đọc mã quán không hỏi cache một câu nào', function () {
    Cache::spy();

    (new CauHinhQuan)->maQuan();

    Cache::shouldNotHaveReceived('get');
    Cache::shouldNotHaveReceived('remember');
    Cache::shouldNotHaveReceived('store');
});

it('đọc mã quán lần thứ hai lấy trong bộ nhớ, không hỏi lại database', function () {
    $cauHinh = new CauHinhQuan;

    DB::connection('tenant')->flushQueryLog();
    DB::connection('tenant')->enableQueryLog();

    $lanDau = $cauHinh->maQuan();
    $lanHai = (new CauHinhQuan)->maQuan();

    // Kể cả khi dựng đối tượng mới: mã quán nhớ theo cả tiến trình, không theo
    // từng đối tượng — vì nó không đổi trong suốt đời một bản cài đặt.
    expect($lanHai)->toBe($lanDau)
        ->and(DB::connection('tenant')->getQueryLog())->toHaveCount(1);

    DB::connection('tenant')->disableQueryLog();
});

it('không có đường nào sửa mã quán — đó là chủ ý, không phải thiếu sót', function () {
    // Mã quán sẽ nằm trong khoá cache, trong đường dẫn IN LÊN TEM QR GIẤY dán
    // bàn, và trong dữ liệu của máy tính bảng. Một ô nhập liệu để sửa nó là
    // một cái nút làm mồ côi cả ba thứ đó chỉ bằng một cú bấm nhầm.
    $hamCong = get_class_methods(CauHinhQuan::class);

    $hamGhiMaQuan = array_filter(
        $hamCong,
        fn (string $ten): bool => str_starts_with($ten, 'dat') && str_contains(strtolower($ten), 'maquan'),
    );

    expect($hamGhiMaQuan)->toBe([]);
});

it('database từ chối bỏ trống mã quán, không chỉ code từ chối', function () {
    $sua = fn () => DB::connection('tenant')->table(CauHinhQuan::BANG)
        ->where('khoa', CauHinhQuan::KHOA_MA_QUAN)
        ->update(['gia_tri' => null]);

    expect($sua)->toThrow(QueryException::class);

    expect((new CauHinhQuan)->maQuan())->toBe(config('pos.ma_quan'));
});

it('thiếu hẳn dòng mã quán thì báo lỗi rõ ràng, không lấy tạm giá trị khác', function () {
    DB::connection('tenant')->table(CauHinhQuan::BANG)->where('khoa', CauHinhQuan::KHOA_MA_QUAN)->delete();
    CauHinhQuan::quenMaQuanDaNho();

    expect(fn () => (new CauHinhQuan)->maQuan())
        ->toThrow(RuntimeException::class);
});
