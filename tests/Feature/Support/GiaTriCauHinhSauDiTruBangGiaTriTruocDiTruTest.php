<?php

declare(strict_types=1);

use App\Support\CauHinhQuan;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Lời hứa của bước 5A.1: đây là lần CHUYỂN CHỖ Ở, không phải lần đổi giá trị.
 *
 * Chủ quán đăng nhập sau khi chạy migration phải thấy y hệt ba con số hôm
 * trước. Test này dựng lại đúng cảnh đó: đưa hệ thống về trạng thái "chưa di
 * trú" bằng chính hàm quay lui của migration, đặt giá trị vào chỗ cũ (bảng
 * `cache`), rồi chạy di trú và đối chiếu.
 */
function diTruCauHinh(): Migration
{
    // `require` chứ không `require_once`: mỗi lần gọi trả về một đối tượng
    // migration mới, để test nào cần chạy lại up()/down() cũng gọi được.
    return require base_path('database/migrations/2026_08_17_000002_chuyen_nguong_cau_hinh_tu_cache_sang_bang.php');
}

it('ngưỡng chủ quán đã chỉnh trước khi di trú thì sau di trú đọc ra đúng số đó', function () {
    $diTru = diTruCauHinh();

    // Quay về trạng thái trước bước 5A.1: bảng cấu hình trống, giá trị nằm
    // trong kho cấu hình dùng chung.
    $diTru->down();
    Cache::store('database')->forever('cau-hinh-quan:'.CauHinhQuan::KHOA_HAO_HUT_PIN, 333_000);
    Cache::store('database')->forever('cau-hinh-quan:'.CauHinhQuan::KHOA_LAI_THAP, 42);
    Cache::store('database')->forever('cau-hinh-quan:'.CauHinhQuan::KHOA_MOC_KIEM_THIEU_GIA_VON, '2026-05-20');

    $diTru->up();

    $sauDiTru = new CauHinhQuan;

    expect($sauDiTru->nguongHaoHutCanPin()->amount)->toBe(333_000)
        ->and($sauDiTru->nguongLaiThapPhanTram())->toBe(42)
        ->and($sauDiTru->mocNgayKiemThieuGiaVon()->toDateString())->toBe('2026-05-20');
});

it('ngưỡng chưa ai chỉnh thì sau di trú vẫn là "đang dùng mặc định", không bị đóng đinh thành số cứng', function () {
    $diTru = diTruCauHinh();

    $diTru->down();
    Cache::store('database')->flush();

    $diTru->up();

    $sauDiTru = new CauHinhQuan;
    $nguon = $sauDiTru->nguonGiaTri(CauHinhQuan::KHOA_HAO_HUT_PIN);

    expect($sauDiTru->nguongHaoHutCanPin()->amount)->toBe(200_000)
        ->and($nguon['da_chinh'])->toBeFalse()
        ->and($nguon['nguoi_doi'])->toBeNull();
});

it('di trú ghi mã quán làm dòng đầu tiên của bảng', function () {
    $diTru = diTruCauHinh();

    $diTru->down();
    $diTru->up();

    $dongDauTien = DB::connection('tenant')->table(CauHinhQuan::BANG)->orderBy('id')->first();

    expect($dongDauTien->khoa)->toBe(CauHinhQuan::KHOA_MA_QUAN)
        ->and($dongDauTien->gia_tri)->toBe(config('pos.ma_quan'))
        ->and($dongDauTien->kieu_du_lieu)->toBe('chuoi');
});

it('quay lui trả ngưỡng đã chỉnh về đúng chỗ cũ, không làm mất số của chủ quán', function () {
    (new CauHinhQuan)->datNguongHaoHutCanPin(888_000);

    diTruCauHinh()->down();

    expect(Cache::store('database')->get('cau-hinh-quan:'.CauHinhQuan::KHOA_HAO_HUT_PIN))->toBe('888000')
        ->and(DB::connection('tenant')->table(CauHinhQuan::BANG)->count())->toBe(0);
});

it('chạy di trú hai lần không đẻ thêm dòng trùng', function () {
    $truoc = DB::connection('tenant')->table(CauHinhQuan::BANG)->count();

    diTruCauHinh()->up();

    expect(DB::connection('tenant')->table(CauHinhQuan::BANG)->count())->toBe($truoc);
});
