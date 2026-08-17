<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;
use Spatie\Activitylog\Models\Activity;

/**
 * Cấu hình của quán: mã quán và ba ngưỡng chủ quán tự chỉnh được.
 *
 * ── CHỖ Ở MỚI, TỪ PHASE 5 BƯỚC 5A.1 ───────────────────────────────────────
 * Trước bước này, ba ngưỡng nằm nhờ trong bảng `cache`. Nghĩa là một lệnh
 * `php artisan cache:clear` đưa cả ba về mặc định mà không ai thấy — trong đó
 * có ngưỡng hao hụt cần PIN, tức là NỚI LỎNG một lá chắn trong im lặng. Nay
 * chúng nằm trong bảng riêng `cau_hinh_quan`, xoá cache không đụng tới nữa.
 *
 * Hai chốt cũ giữ nguyên vì chúng vẫn đáng giá:
 *  1. Mỗi lần đổi ghi một dòng activity_log (log_name = 'cau-hinh-nguong',
 *     event = tên khoá): ai đổi, từ bao nhiêu sang bao nhiêu, lúc nào.
 *  2. nguonGiaTri() trả về giá trị ĐANG dùng kèm nguồn của nó — mặc định hay
 *     do người đổi — để màn hình cấu hình nói thẳng cho chủ quán biết.
 *
 * ── BA ĐIỀU PHẢI GIỮ KHI SỬA FILE NÀY ─────────────────────────────────────
 *
 * 1. `ma_quan` đọc THẲNG từ database, KHÔNG đi qua cache. Từ bước 5A.4, mọi
 *    khoá cache đều mang tiền tố là mã quán — nếu đọc mã quán lại phải hỏi
 *    cache thì thành vòng lặp con gà và quả trứng. Vì mã quán là ghi một lần,
 *    nhớ nó trong bộ nhớ tiến trình là an toàn.
 *
 * 2. Ba ngưỡng nhớ tạm trong phạm vi MỘT lần dựng đối tượng (một request là
 *    một lần), không nhớ kiểu `forever`. Điều này không phải để chạy nhanh, nó
 *    là chốt chặn thật: `WriteOffStock` đọc ngưỡng hao hụt hai lần — lần một ở
 *    ngoài để hỏi PIN, lần hai ở BÊN TRONG giao dịch để kiểm lại bằng số thật.
 *    Nhờ nhớ tạm, lần hai lấy trong bộ nhớ chứ không chạy thêm câu hỏi nào
 *    xuống database khi giao dịch đang mở và đang giữ khoá.
 *    Nhớ tạm mà không có đường làm mới thì thành nhớ nhầm, nên mọi lần ghi
 *    đều báo cho các đối tượng khác trong cùng tiến trình biết mà hỏi lại —
 *    xem `$theHe` bên dưới.
 *
 * 3. Ghi bằng `updateOrInsert` trên khoá UNIQUE `khoa`. KHÔNG đọc-rồi-ghi:
 *    hai người cùng bấm lưu thì đọc-rồi-ghi làm mất lặng lẽ một trong hai lần
 *    đổi, mà lần bị mất lại không để lại dấu vết nào ngoài dòng nhật ký nói
 *    rằng nó đã xảy ra.
 *
 * `gia_tri` bằng NULL nghĩa là "chưa ai chỉnh, đang dùng giá trị khởi đầu
 * trong config/pos.php". Nút "đặt lại mặc định" ghi NULL chứ không xoá dòng —
 * sổ của quán không có cục tẩy, kể cả sổ cấu hình.
 */
final class CauHinhQuan
{
    public const BANG = 'cau_hinh_quan';

    public const LOG_NAME = 'cau-hinh-nguong';

    public const KHOA_MA_QUAN = 'ma_quan';

    public const KHOA_LAI_THAP = 'nguong_ti_le_lai_thap_phan_tram';

    public const KHOA_HAO_HUT_PIN = 'nguong_hao_hut_can_pin';

    public const KHOA_MOC_KIEM_THIEU_GIA_VON = 'moc_ngay_kiem_thieu_gia_von';

    /** Kiểu dữ liệu của từng khoá — dùng để ép kiểu lúc đọc và lúc ghi. */
    private const KIEU_DU_LIEU = [
        self::KHOA_MA_QUAN => 'chuoi',
        self::KHOA_LAI_THAP => 'so_nguyen',
        self::KHOA_HAO_HUT_PIN => 'so_nguyen',
        self::KHOA_MOC_KIEM_THIEU_GIA_VON => 'ngay',
    ];

    /**
     * Nhớ tạm trong phạm vi một lần dựng đối tượng — xem điều 2 ở đầu file.
     *
     * @var array<string, string|null>
     */
    private array $nhoTam = [];

    /**
     * Đếm số lần có ai đó ghi xuống bảng cấu hình trong tiến trình này.
     *
     * Có nó vì nhớ tạm mà không có đường làm mới là nhớ nhầm: lệnh
     * `report:summarize` hạ mốc ngày rồi đọc lại mốc ngay trong cùng một lần
     * chạy, và người đọc là một đối tượng CauHinhQuan khác với đối tượng vừa
     * ghi. Ai ghi thì tăng số đếm này lên, ai đang nhớ số cũ thì tự bỏ đi và
     * hỏi lại database — nên không có chuyện đọc ra con số đã lỗi thời.
     */
    private static int $theHe = 0;

    private int $theHeDaDoc = 0;

    /** Mã quán là ghi một lần nên nhớ được cho cả tiến trình — xem điều 1. */
    private static ?string $maQuanDaNho = null;

    /**
     * Mã định danh của quán này.
     *
     * Ném lỗi thay vì lấy tạm một giá trị khác khi thiếu: mã quán đã nằm trong
     * khoá cache, trên tem QR giấy dán bàn và trong máy tính bảng. Đoán bừa
     * một giá trị là làm mồ côi cả ba thứ đó mà không ai thấy lỗi.
     */
    public function maQuan(): string
    {
        if (self::$maQuanDaNho !== null) {
            return self::$maQuanDaNho;
        }

        $giaTri = $this->bang()->where('khoa', self::KHOA_MA_QUAN)->value('gia_tri');

        if ($giaTri === null || (string) $giaTri === '') {
            throw new RuntimeException(
                'Bảng '.self::BANG.' chưa có dòng mã quán. Chạy `php artisan migrate` để dựng lại dòng đó; '.
                'không được lấy tạm một giá trị khác — mã quán đã in lên tem QR và nằm trong máy tính bảng.'
            );
        }

        return self::$maQuanDaNho = (string) $giaTri;
    }

    /**
     * Quên mã quán đang nhớ trong bộ nhớ tiến trình.
     *
     * Chỉ dùng trong test. Ngoài đời mã quán không đổi nên không có chỗ nào
     * cần gọi hàm này — và đó là chủ ý, không phải thiếu sót.
     */
    public static function quenMaQuanDaNho(): void
    {
        self::$maQuanDaNho = null;
    }

    /** Tỉ lệ lãi dưới mức này thì coi là "lãi thấp". Trả về phần trăm nguyên: 25 nghĩa là 25%. */
    public function nguongLaiThapPhanTram(): int
    {
        return $this->docSoNguyen(self::KHOA_LAI_THAP);
    }

    /** Cùng ngưỡng trên nhưng ở dạng tỉ lệ để so với margin: 25% → 0.25. */
    public function nguongLaiThapTiLe(): float
    {
        return $this->nguongLaiThapPhanTram() / 100;
    }

    /** Hao hụt một lần đáng giá TỪ mức này trở lên thì phải có chủ quán duyệt bằng PIN. */
    public function nguongHaoHutCanPin(): Money
    {
        return Money::fromInt($this->docSoNguyen(self::KHOA_HAO_HUT_PIN));
    }

    public function datNguongLaiThapPhanTram(int $phanTram): void
    {
        if ($phanTram < 0 || $phanTram > 100) {
            throw new InvalidArgumentException("Ngưỡng lãi thấp phải nằm trong khoảng 0-100%: {$phanTram}");
        }

        $this->ghi(self::KHOA_LAI_THAP, $phanTram);
    }

    public function datNguongHaoHutCanPin(int $soTien): void
    {
        if ($soTien < 0) {
            throw new InvalidArgumentException("Ngưỡng hao hụt không được âm: {$soTien}");
        }

        $this->ghi(self::KHOA_HAO_HUT_PIN, $soTien);
    }

    /**
     * Ngày CŨ NHẤT mà số liệu lãi gộp đã được kiểm phần thiếu giá vốn.
     * Dữ liệu từ ngày này trở về sau: hai cột qty_no_cost/qty_not_served có
     * số thật. Trước ngày này: số 0 không có nghĩa là sạch, chỉ là chưa đếm.
     */
    public function mocNgayKiemThieuGiaVon(): Carbon
    {
        $daChinh = $this->docTho(self::KHOA_MOC_KIEM_THIEU_GIA_VON);

        return Carbon::parse($daChinh ?? config('pos.'.self::KHOA_MOC_KIEM_THIEU_GIA_VON))->startOfDay();
    }

    /**
     * Hạ mốc xuống sau khi đã tổng hợp lại một khoảng ngày cũ.
     *
     * CHỈ HẠ, KHÔNG BAO GIỜ NÂNG: nâng mốc lên nghĩa là tự nhận "đoạn này chưa
     * kiểm" cho đoạn đã kiểm rồi — thừa cảnh báo thì chỉ phiền, còn thiếu cảnh
     * báo thì chủ quán tin nhầm vào con số. Trả về true nếu mốc thật sự đổi.
     */
    public function haMocNgayKiemThieuGiaVon(Carbon $ngay): bool
    {
        $ngay = $ngay->clone()->startOfDay();

        if ($ngay->greaterThanOrEqualTo($this->mocNgayKiemThieuGiaVon())) {
            return false;
        }

        $mocCu = $this->mocNgayKiemThieuGiaVon();
        $this->ghiVaoBang(self::KHOA_MOC_KIEM_THIEU_GIA_VON, $ngay->toDateString());

        activity(self::LOG_NAME)
            ->event(self::KHOA_MOC_KIEM_THIEU_GIA_VON)
            ->causedBy(auth()->user())
            ->withProperties([
                'khoa' => self::KHOA_MOC_KIEM_THIEU_GIA_VON,
                'gia_tri_cu' => $mocCu->toDateString(),
                'gia_tri_moi' => $ngay->toDateString(),
            ])
            ->log("Hạ mốc đã kiểm thiếu giá vốn: {$mocCu->toDateString()} → {$ngay->toDateString()}");

        return true;
    }

    /**
     * Về lại giá trị khởi đầu trong config/pos.php.
     *
     * CHỈ hai ngưỡng, KHÔNG đụng mốc ngày đã kiểm — mốc đó không phải thứ chủ
     * quán chỉnh tay, nó là dấu vết của việc đã chạy tổng hợp lại thật.
     */
    public function datLaiMacDinh(): void
    {
        foreach ([self::KHOA_LAI_THAP, self::KHOA_HAO_HUT_PIN] as $khoa) {
            if (! $this->daTungChinh($khoa)) {
                continue;
            }

            $giaTriCu = $this->docSoNguyen($khoa);
            $this->ghiVaoBang($khoa, null);

            activity(self::LOG_NAME)
                ->event($khoa)
                ->causedBy(auth()->user())
                ->withProperties([
                    'khoa' => $khoa,
                    'gia_tri_cu' => $giaTriCu,
                    'gia_tri_moi' => (int) config("pos.{$khoa}"),
                    'nguon_gia_tri_cu' => 'nguoi_doi',
                ])
                ->log("Đặt lại ngưỡng {$khoa} về mặc định: {$giaTriCu} → ".(int) config("pos.{$khoa}"));
        }
    }

    /**
     * Giá trị ĐANG dùng của một ngưỡng, kèm nguồn của nó: mặc định trong
     * config/pos.php, hay do người nào đó đổi (đọc từ activity_log).
     *
     * @return array{da_chinh: bool, gia_tri: int, nguoi_doi: ?string, luc: ?Carbon}
     */
    public function nguonGiaTri(string $khoa): array
    {
        $giaTri = $this->docSoNguyen($khoa);

        if (! $this->daTungChinh($khoa)) {
            return ['da_chinh' => false, 'gia_tri' => $giaTri, 'nguoi_doi' => null, 'luc' => null];
        }

        $lanCuoi = Activity::query()
            ->where('log_name', self::LOG_NAME)
            ->where('event', $khoa)
            ->latest('id')
            ->first();

        return [
            'da_chinh' => true,
            'gia_tri' => $giaTri,
            'nguoi_doi' => $lanCuoi?->causer?->name,
            'luc' => $lanCuoi?->created_at,
        ];
    }

    private function daTungChinh(string $khoa): bool
    {
        return $this->docTho($khoa) !== null;
    }

    private function docSoNguyen(string $khoa): int
    {
        $daChinh = $this->docTho($khoa);

        if ($daChinh !== null) {
            return (int) $daChinh;
        }

        return (int) config("pos.{$khoa}");
    }

    /** Giá trị thô trong bảng, hoặc null khi chưa ai chỉnh khoá này. */
    private function docTho(string $khoa): ?string
    {
        if ($this->theHeDaDoc !== self::$theHe) {
            $this->nhoTam = [];
            $this->theHeDaDoc = self::$theHe;
        }

        if (! array_key_exists($khoa, $this->nhoTam)) {
            $this->nhoTam[$khoa] = $this->bang()->where('khoa', $khoa)->value('gia_tri');
        }

        return $this->nhoTam[$khoa] === null ? null : (string) $this->nhoTam[$khoa];
    }

    private function ghi(string $khoa, int $giaTri): void
    {
        $giaTriCu = $this->docSoNguyen($khoa);
        $nguonCu = $this->daTungChinh($khoa) ? 'nguoi_doi' : 'mac_dinh';

        $this->ghiVaoBang($khoa, (string) $giaTri);

        // Nới lỏng một lá chắn phải để lại dấu vết. Ghi cả khi giá trị không
        // đổi: "đã vào chỉnh mà giữ nguyên" cũng là một sự kiện đáng biết.
        activity(self::LOG_NAME)
            ->event($khoa)
            ->causedBy(auth()->user())
            ->withProperties([
                'khoa' => $khoa,
                'gia_tri_cu' => $giaTriCu,
                'gia_tri_moi' => $giaTri,
                'nguon_gia_tri_cu' => $nguonCu,
            ])
            ->log("Đổi ngưỡng {$khoa}: {$giaTriCu} → {$giaTri}");
    }

    /**
     * Một cửa duy nhất để ghi xuống bảng. `updateOrInsert` trên khoá UNIQUE
     * `khoa` — xem điều 3 ở đầu file về việc vì sao cấm đọc-rồi-ghi.
     */
    private function ghiVaoBang(string $khoa, ?string $giaTri): void
    {
        $luc = now();

        $this->bang()->updateOrInsert(
            ['khoa' => $khoa],
            fn (bool $daCo): array => array_merge([
                'gia_tri' => $giaTri,
                'kieu_du_lieu' => self::KIEU_DU_LIEU[$khoa] ?? 'chuoi',
                'updated_at' => $luc,
            ], $daCo ? [] : ['created_at' => $luc]),
        );

        // Báo cho mọi đối tượng CauHinhQuan khác đang sống trong tiến trình
        // này biết là số đã đổi, kể cả đối tượng được dựng từ trước.
        self::$theHe++;
        $this->nhoTam = [];
        $this->theHeDaDoc = self::$theHe;
    }

    private function bang(): Builder
    {
        return DB::connection('tenant')->table(self::BANG);
    }
}
