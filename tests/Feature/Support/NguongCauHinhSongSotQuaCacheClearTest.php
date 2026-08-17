<?php

declare(strict_types=1);

use App\Support\CauHinhQuan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Lý do bước 5A.1 tồn tại, viết thành test.
 *
 * Trước bước này ba ngưỡng nằm nhờ trong bảng `cache`, nên `php artisan
 * cache:clear` — một lệnh vô hại, ai cũng chạy khi máy giở chứng — lặng lẽ đưa
 * cả ba về mặc định. Trong đó có ngưỡng bắt PIN khi hao hụt: mất nó là lá chắn
 * nới lỏng ra mà không ai được báo.
 */
it('xoá sạch cache thì ba ngưỡng đã chỉnh vẫn còn nguyên', function () {
    $cauHinh = new CauHinhQuan;

    $cauHinh->datNguongLaiThapPhanTram(40);
    $cauHinh->datNguongHaoHutCanPin(750_000);
    $cauHinh->haMocNgayKiemThieuGiaVon(Carbon::parse('2026-06-01'));

    // Đúng việc mà `php artisan cache:clear` làm.
    Cache::store('database')->flush();
    Cache::flush();

    $sauKhiXoa = new CauHinhQuan;

    expect($sauKhiXoa->nguongLaiThapPhanTram())->toBe(40)
        ->and($sauKhiXoa->nguongHaoHutCanPin()->amount)->toBe(750_000)
        ->and($sauKhiXoa->mocNgayKiemThieuGiaVon()->toDateString())->toBe('2026-06-01');
});

it('xoá sạch cache thì màn hình vẫn báo đúng là "do người đổi", không quay về "mặc định"', function () {
    (new CauHinhQuan)->datNguongHaoHutCanPin(750_000);

    Cache::store('database')->flush();
    Cache::flush();

    $nguon = (new CauHinhQuan)->nguonGiaTri(CauHinhQuan::KHOA_HAO_HUT_PIN);

    expect($nguon['da_chinh'])->toBeTrue()
        ->and($nguon['gia_tri'])->toBe(750_000);
});

it('ba ngưỡng nằm trong bảng cấu hình, không nằm trong bảng cache nữa', function () {
    (new CauHinhQuan)->datNguongHaoHutCanPin(750_000);

    $dong = DB::connection('tenant')->table(CauHinhQuan::BANG)
        ->where('khoa', CauHinhQuan::KHOA_HAO_HUT_PIN)
        ->sole();

    expect((string) $dong->gia_tri)->toBe('750000')
        ->and($dong->kieu_du_lieu)->toBe('so_nguyen');

    // Không còn dòng nào mang tiền tố cũ trong kho cấu hình dùng chung.
    expect(DB::connection('tenant')->table('cache')->where('key', 'like', '%cau-hinh-quan:%')->count())->toBe(0);
});

it('chưa ai chỉnh thì bảng giữ dòng rỗng chứ không đóng đinh giá trị mặc định', function () {
    // Dòng vẫn có mặt (để đọc là biết khoá này tồn tại), nhưng giá trị để
    // trống — nghĩa là "đang dùng số khởi đầu trong config/pos.php". Nhờ vậy
    // đổi mặc định trong config vẫn có tác dụng với quán chưa chỉnh lần nào.
    $dong = DB::connection('tenant')->table(CauHinhQuan::BANG)
        ->where('khoa', CauHinhQuan::KHOA_LAI_THAP)
        ->sole();

    expect($dong->gia_tri)->toBeNull()
        ->and((new CauHinhQuan)->nguongLaiThapPhanTram())->toBe(25);
});

it('đặt lại mặc định thì xoá giá trị chứ không xoá dòng — sổ cấu hình cũng không có cục tẩy', function () {
    $cauHinh = new CauHinhQuan;
    $cauHinh->datNguongLaiThapPhanTram(40);

    $cauHinh->datLaiMacDinh();

    $dong = DB::connection('tenant')->table(CauHinhQuan::BANG)
        ->where('khoa', CauHinhQuan::KHOA_LAI_THAP)
        ->sole();

    expect($dong->gia_tri)->toBeNull()
        ->and((new CauHinhQuan)->nguongLaiThapPhanTram())->toBe(25);
});
