<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

/**
 * BIẾN ĐIỀU-PHẢI-NHỚ THÀNH ĐIỀU-MÁY-KIỂM (cùng khuôn DatabaseDriverTest).
 *
 * Từ Phase 5, mọi bảng nghiệp vụ của quán đi qua kết nối mang tên `tenant`
 * (xem App\Models\BaseModel). Laravel đếm giao dịch THEO TỪNG KẾT NỐI: mở
 * `DB::transaction()` trần là mở trên kết nối MẶC ĐỊNH, trong khi lệnh ghi
 * của Model đi trên `tenant`.
 *
 * Hôm nay hai cái tên đó trỏ vào cùng một phiên nói chuyện với MariaDB
 * (App\Providers\AppServiceProvider::dungChungMotPhien), nên viết sai KHÔNG
 * hỏng gì cả — và đó chính là lý do phải có bộ quét này. Ngày tách phiên thật
 * (Bước 5B, xem docs/viec-ton.md), một `DB::transaction()` trần sẽ lặng lẽ
 * mất tính "hoặc làm trọn cả gói, hoặc không làm gì cả": hỏng giữa chừng thì
 * một nửa nằm lại trong database và không có gì báo lỗi. Sổ cái kho và đường
 * thu tiền đều nằm trong vùng đó.
 *
 * Lỗi loại này không bao giờ tự lộ ra lúc viết. Nên nó phải bị chặn lúc viết.
 */

/** @return list<string> Đường dẫn mọi file Action, Query và lệnh Artisan. */
function fileHayMoGiaoDich(): array
{
    $ketQua = [];

    foreach (File::allFiles(app_path('Domain')) as $file) {
        $duongDan = str_replace('\\', '/', $file->getPathname());

        if ($file->getExtension() === 'php'
            && (str_contains($duongDan, '/Actions/') || str_contains($duongDan, '/Queries/'))) {
            $ketQua[] = $duongDan;
        }
    }

    foreach (File::allFiles(app_path('Console/Commands')) as $file) {
        if ($file->getExtension() === 'php') {
            $ketQua[] = str_replace('\\', '/', $file->getPathname());
        }
    }

    sort($ketQua);

    return $ketQua;
}

/**
 * Mã nguồn đã bỏ hết chú thích, giữ nguyên số dòng.
 *
 * Cần bước này vì rất nhiều chú thích trong dự án nhắc tới `DB::transaction`
 * để giải thích luật — quét trên văn bản thô sẽ báo đỏ chính những dòng đang
 * dặn người đọc làm cho đúng.
 */
function maNguonBoChuThich(string $duongDan): string
{
    $ma = '';

    foreach (token_get_all((string) file_get_contents($duongDan)) as $token) {
        if (! is_array($token)) {
            $ma .= $token;

            continue;
        }

        if (in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
            $ma .= str_repeat("\n", substr_count($token[1], "\n"));

            continue;
        }

        $ma .= $token[1];
    }

    return $ma;
}

/**
 * @param  list<string>  $duongDanFile
 * @return list<string> Danh sách "file:dòng — nội dung" vi phạm.
 */
function choMoGiaoDichSai(array $duongDanFile): array
{
    // Hai hình dạng bị cấm:
    //  1. DB::transaction( / beginTransaction( / commit( / rollBack( — facade
    //     trần, luôn chạy trên kết nối mặc định.
    //  2. connection('<tên khác tenant>')->transaction( — gọi đích danh nhầm
    //     kết nối, hiếm nhưng còn khó thấy hơn cái trên.
    $tran = '/\bDB::\s*(transaction|beginTransaction|commit|rollBack)\s*\(/i';
    $saiTen = "/connection\(\s*['\"](?!tenant)[^'\"]*['\"]\s*\)\s*->\s*(transaction|beginTransaction|commit|rollBack)\s*\(/i";

    $viPham = [];

    foreach ($duongDanFile as $duongDan) {
        $dong = explode("\n", maNguonBoChuThich($duongDan));
        $tenNgan = str_replace(str_replace('\\', '/', base_path()).'/', '', $duongDan);

        foreach ($dong as $soThuTu => $noiDung) {
            if (preg_match($tran, $noiDung) === 1 || preg_match($saiTen, $noiDung) === 1) {
                $viPham[] = $tenNgan.':'.($soThuTu + 1).' — '.trim($noiDung);
            }
        }
    }

    return $viPham;
}

it('bộ quét thật sự nhìn thấy file — không xanh vì quét trúng thư mục rỗng', function () {
    // Chốt chặn cho chính bộ quét: đổi cấu trúc thư mục mà quên sửa hàm tìm
    // file thì ba test dưới xanh hết trong khi không kiểm được gì.
    $file = fileHayMoGiaoDich();

    // Ngưỡng 60 đặt dưới con số thật (87 file lúc viết, 17/08) một quãng rộng:
    // đủ thấp để không đỏ vặt khi dồn vài Action lại, đủ cao để bắt được trường
    // hợp đường quét hỏng và trả về 0 hay dăm ba file.
    expect(count($file))->toBeGreaterThan(
        60,
        'Bộ quét chỉ tìm thấy '.count($file).' file Action/Query/lệnh Artisan. '
        .'Con số này phải hàng chục — nhiều khả năng cấu trúc thư mục đã đổi và '
        .'fileHayMoGiaoDich() không còn tìm đúng chỗ nữa.'
    );

    expect(implode("\n", $file))->toContain('app/Domain/Billing/Actions/RecordPayment.php');
});

it('không Action, Query hay lệnh Artisan nào được mở giao dịch trần', function () {
    $viPham = choMoGiaoDichSai(fileHayMoGiaoDich());

    expect($viPham)->toBe([], count($viPham).' chỗ đang mở giao dịch KHÔNG phải trên kết nối "tenant":'
        ."\n\n".implode("\n", $viPham)."\n\n"
        .'Sửa thành DB::connection(\'tenant\')->transaction(...). Lý do: lệnh ghi của '
        .'Model đi trên kết nối "tenant", còn DB::transaction() trần mở giao dịch trên '
        .'kết nối MẶC ĐỊNH. Hôm nay hai kết nối đó dùng chung một phiên nên không hỏng '
        .'gì, nhưng ngày tách phiên thật (Bước 5B) thì giao dịch sẽ không còn bọc được '
        .'lệnh ghi nào — hỏng giữa chừng là một nửa nằm lại trong database, im lặng. '
        .'Xem CLAUDE.md mục 9 và docs/viec-ton.md.');
});

/**
 * @param  list<string>  $duongDanFile
 * @return list<string>
 */
function choChayCauLenhSai(array $duongDanFile): array
{
    // `DB::table('x')`, `DB::select(...)` bỏ qua Model nên rơi về kết nối mặc
    // định — đúng cái Model vừa được kéo khỏi. `selectRaw`/`whereRaw` gọi trên
    // câu truy vấn của Model KHÔNG thuộc nhóm này: chúng đi theo kết nối của
    // chính Model đó, nên không có mặt trong biểu thức dưới.
    $mau = '/\bDB::\s*(table|select|selectOne|insert|update|delete|statement|unprepared|affectingStatement|scalar|cursor)\s*\(/i';

    $viPham = [];

    foreach ($duongDanFile as $duongDan) {
        $dong = explode("\n", maNguonBoChuThich($duongDan));
        $tenNgan = str_replace(str_replace('\\', '/', base_path()).'/', '', $duongDan);

        foreach ($dong as $soThuTu => $noiDung) {
            if (preg_match($mau, $noiDung) === 1) {
                $viPham[] = $tenNgan.':'.($soThuTu + 1).' — '.trim($noiDung);
            }
        }
    }

    return $viPham;
}

it('không chỗ nào trong app/ chạy câu lệnh thô trên kết nối mặc định', function () {
    $tatCa = [];
    foreach (File::allFiles(app_path()) as $file) {
        if ($file->getExtension() === 'php') {
            $tatCa[] = str_replace('\\', '/', $file->getPathname());
        }
    }

    $viPham = choChayCauLenhSai($tatCa);

    expect($viPham)->toBe([], count($viPham).' chỗ đang chạy câu lệnh thô trên kết nối mặc định:'
        ."\n\n".implode("\n", $viPham)."\n\n"
        .'Sửa thành DB::connection(\'tenant\')->table(...) / ->select(...). Lý do: từ '
        .'Phase 5, mọi bảng của quán nằm trên kết nối "tenant" (xem App\Models\BaseModel). '
        .'DB::table() gọi thẳng thì bỏ qua Model và rơi về kết nối mặc định — hôm nay hai '
        .'kết nối dùng chung một phiên nên không hỏng gì, nhưng ngày tách phiên (Bước 5B) '
        .'nó sẽ đọc/ghi NHẦM DATABASE và vẫn trả lời như thật.');
});

it('không chỗ nào khác trong app/ mở giao dịch trần', function () {
    // Rộng hơn ba thư mục ở trên: Controller, Filament, Middleware, Support...
    // đều không được tự mở giao dịch. Hôm nay không chỗ nào làm việc đó — test
    // này giữ cho ngày mai cũng vậy.
    $tatCa = [];
    foreach (File::allFiles(app_path()) as $file) {
        if ($file->getExtension() === 'php') {
            $tatCa[] = str_replace('\\', '/', $file->getPathname());
        }
    }

    $viPham = choMoGiaoDichSai($tatCa);

    expect($viPham)->toBe([], count($viPham).' chỗ trong app/ đang mở giao dịch trần:'
        ."\n\n".implode("\n", $viPham)."\n\n"
        .'Nghiệp vụ đụng tiền thuộc về Action (CLAUDE.md mục 1), và mọi giao dịch phải '
        .'mở bằng DB::connection(\'tenant\')->transaction(...).');
});
