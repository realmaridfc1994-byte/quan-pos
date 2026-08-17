<?php

declare(strict_types=1);

use App\Support\CauHinhQuan;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Dọn nhà cho ba ngưỡng cấu hình, và ghi dòng `ma_quan` đầu tiên.
 *
 * Đây là lần chuyển chỗ ở, KHÔNG phải lần đổi giá trị. Nguyên tắc: chủ quán
 * đăng nhập sau khi chạy lệnh này phải thấy y hệt ba con số trước đó. Ngưỡng
 * nào chưa ai chỉnh lần nào thì chép sang dạng "chưa chỉnh" (gia_tri = NULL),
 * chứ không đóng đinh giá trị mặc định vào bảng — làm vậy là biến một con số
 * còn sửa được ở config/pos.php thành một con số nằm cứng trong database.
 *
 * `ma_quan` ghi TRƯỚC ba ngưỡng để nó là dòng đầu tiên của bảng, đúng như
 * hướng dẫn Phase 5 mục 2. Giá trị lấy từ config/pos.php (đè được bằng
 * POS_MA_QUAN trong .env, nhưng chỉ có tác dụng ở lần chạy migration này —
 * sau đó database mới là nguồn chân lý).
 *
 * CẢNH BÁO cho người đọc sau: `ma_quan` là GHI MỘT LẦN. Nó sẽ nằm trong khoá
 * cache (5A.4), trong đường dẫn in trên tem QR giấy dán bàn (5A.5) và trong
 * dữ liệu nằm sẵn ở máy tính bảng (5A.6). Đổi nó sau khi ba bước đó xong là
 * làm mồ côi cả ba, và tem thì không sửa được bằng deploy. Muốn đổi thì đổi
 * TRƯỚC 5A.4, và đổi bằng một quy trình có văn bản — không phải bằng một ô
 * nhập liệu trên màn hình.
 *
 * down() quay lui sạch: ngưỡng nào đã từng chỉnh thì trả về đúng chỗ cũ trong
 * bảng `cache`, rồi xoá bốn dòng vừa ghi. Chạy `migrate:rollback` xong, hệ
 * thống cũ đọc lại được y nguyên số của mình.
 */
return new class extends Migration
{
    private const TIEN_TO_CACHE = 'cau-hinh-quan:';

    /** @return array<string, array{kieu: string, mo_ta: string}> */
    private function baNguong(): array
    {
        return [
            CauHinhQuan::KHOA_LAI_THAP => [
                'kieu' => 'so_nguyen',
                'mo_ta' => 'Tỉ lệ lãi dưới mức này thì coi là "lãi thấp" (phần trăm nguyên)',
            ],
            CauHinhQuan::KHOA_HAO_HUT_PIN => [
                'kieu' => 'so_nguyen',
                'mo_ta' => 'Hao hụt đáng giá từ mức này trở lên phải có chủ quán duyệt bằng PIN (đồng)',
            ],
            CauHinhQuan::KHOA_MOC_KIEM_THIEU_GIA_VON => [
                'kieu' => 'ngay',
                'mo_ta' => 'Ngày cũ nhất mà số liệu lãi gộp đã được kiểm phần thiếu giá vốn',
            ],
        ];
    }

    public function up(): void
    {
        $luc = now();

        $this->ghiDong(
            CauHinhQuan::KHOA_MA_QUAN,
            (string) config('pos.'.CauHinhQuan::KHOA_MA_QUAN),
            'chuoi',
            'Mã định danh của quán — GHI MỘT LẦN, không sửa qua giao diện',
            $luc,
        );

        foreach ($this->baNguong() as $khoa => $moTa) {
            // Giá trị đang có trong bảng `cache`; chưa ai chỉnh thì là null và
            // chép sang cũng là null — nghĩa là "vẫn đang dùng mặc định".
            $daChinh = Cache::store('database')->get(self::TIEN_TO_CACHE.$khoa);

            $this->ghiDong(
                $khoa,
                $daChinh === null ? null : (string) $daChinh,
                $moTa['kieu'],
                $moTa['mo_ta'],
                $luc,
            );
        }
    }

    public function down(): void
    {
        foreach (array_keys($this->baNguong()) as $khoa) {
            $giaTri = DB::table('cau_hinh_quan')->where('khoa', $khoa)->value('gia_tri');

            if ($giaTri !== null) {
                Cache::store('database')->forever(self::TIEN_TO_CACHE.$khoa, $giaTri);
            }
        }

        DB::table('cau_hinh_quan')
            ->whereIn('khoa', array_merge([CauHinhQuan::KHOA_MA_QUAN], array_keys($this->baNguong())))
            ->delete();
    }

    private function ghiDong(string $khoa, ?string $giaTri, string $kieu, string $moTa, mixed $luc): void
    {
        DB::table('cau_hinh_quan')->updateOrInsert(
            ['khoa' => $khoa],
            fn (bool $daCo): array => array_merge([
                'gia_tri' => $giaTri,
                'kieu_du_lieu' => $kieu,
                'mo_ta' => $moTa,
                'updated_at' => $luc,
            ], $daCo ? [] : ['created_at' => $luc]),
        );
    }
};
