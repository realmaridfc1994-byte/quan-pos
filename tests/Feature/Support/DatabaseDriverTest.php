<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

/**
 * BIẾN ĐIỀU-PHẢI-NHỚ THÀNH ĐIỀU-MÁY-KIỂM.
 *
 * Chuyện "cấu hình quay về mysql / cổng 3307 / user quanpos" đã tái phát BỐN
 * LẦN qua ba phase. Mỗi lần sửa đúng rồi lại quay lại, vì bất biến này chỉ được
 * giữ bằng trí nhớ người đọc tài liệu, không có gì gác.
 *
 * Ba test dưới đây là cái gác đó. Ai đổi driver — dù cố ý hay vô tình khi chép
 * lại `.env.example`, khi dựng máy mới, khi sửa CI — là đỏ ngay, kèm câu giải
 * thích vì sao không được đổi.
 *
 * Vì sao phải là `mariadb` chứ không phải `mysql`: Laravel 11 trở lên có driver
 * riêng cho MariaDB, sinh SQL theo đúng phương ngữ của nó. Để `mysql` thì
 * Laravel tưởng đang nói chuyện với MySQL 8 và một số chỗ (cột tự tính, kiểu dữ
 * liệu) lệch đi. Hai engine cũng đã có khác biệt thật được ghi nhận ở Phase 0:
 * khi ai đó cố ghi đè cột máy tự tính, MySQL 8 từ chối cả câu lệnh, còn MariaDB
 * âm thầm bỏ giá trị sai và chỉ kèm cảnh báo #1906 — xem bất biến M5 trong
 * docs/schema.md.
 */
it('kết nối mặc định phải là mariadb, không phải mysql', function () {
    expect(config('database.default'))->toBe(
        'mariadb',
        'Kết nối mặc định phải là "mariadb". Máy dev, CI và máy quán đều chạy '
        .'MariaDB 10.4.32 (XAMPP 8.2.12). Xem CLAUDE.md mục 2.'
    );
});

it('bảng mã của kết nối phải là utf8mb4 / utf8mb4_unicode_ci', function () {
    expect(config('database.connections.mariadb.charset'))->toBe('utf8mb4')
        ->and(config('database.connections.mariadb.collation'))->toBe('utf8mb4_unicode_ci');

    // Kiểm cả phía máy chủ, không chỉ phía cấu hình: cấu hình đúng mà kết nối
    // thật lại đang dùng bảng mã khác thì so sánh chuỗi tiếng Việt sẽ lệch.
    $thuc = DB::selectOne('select @@collation_connection AS c')->c;

    expect($thuc)->toBe(
        'utf8mb4_unicode_ci',
        "Kết nối thật đang dùng bảng mã {$thuc}. Schema chốt utf8mb4_unicode_ci — "
        .'xem docs/schema.md phần đầu.'
    );
});

it('máy chủ database thật sự đang chạy phải là MariaDB', function () {
    $phienBan = DB::selectOne('select version() AS v')->v;

    expect(str_contains($phienBan, 'MariaDB'))->toBeTrue(
        "Máy chủ database đang chạy là {$phienBan}, không phải MariaDB. "
        .'Schema đã kiểm chứng trên MariaDB 10.4.32 ngày 31/07 (4 phép thử, cả 4 đạt). '
        .'Đổi engine thì phải kiểm chứng lại toàn bộ, không chỉ chạy test cho xanh.'
    );
});
