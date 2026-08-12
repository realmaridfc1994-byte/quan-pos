<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Spatie\Activitylog\Models\Activity;

/**
 * Hai ngưỡng chủ quán tự chỉnh được trên màn hình Filament.
 *
 * KHÔNG tạo bảng mới: giá trị lưu trong kho cấu hình dùng chung của Laravel
 * (bảng `cache` đã có sẵn từ Phase 0, cùng chỗ VerifyApproverPin đang dùng để
 * đếm PIN sai). Lưu bằng forever() — không bao giờ tự hết hạn.
 *
 * Chưa chỉnh lần nào thì đọc ra giá trị khởi đầu trong config/pos.php. Nếu ai
 * đó chạy `php artisan cache:clear`, hai ngưỡng quay về giá trị khởi đầu chứ
 * KHÔNG về 0 — cảnh báo vẫn chạy, ngưỡng hao hụt vẫn còn hiệu lực, chỉ là về
 * mức mặc định. Đây là điều đánh đổi khi không tạo bảng riêng.
 *
 * ── HAI CHỐT BÙ LẠI CHO VIỆC KHÔNG CÓ BẢNG RIÊNG (quyết định 11/08) ────────
 * Mất một ngưỡng đã chỉnh là NỚI LỎNG lá chắn mà không ai thấy, nên:
 *  1. Mỗi lần đổi ghi một dòng activity_log (log_name = 'cau-hinh-nguong',
 *     event = tên ngưỡng): ai đổi, từ bao nhiêu sang bao nhiêu, lúc nào.
 *  2. nguonGiaTri() trả về giá trị ĐANG dùng kèm NGUỒN của nó — mặc định hay
 *     do người đổi — để màn hình cấu hình nói thẳng cho chủ quán biết. Sau
 *     `cache:clear` thì nguồn tự quay về "mặc định", còn lịch sử đổi vẫn nằm
 *     nguyên trong activity_log.
 */
final class CauHinhQuan
{
    private const TIEN_TO = 'cau-hinh-quan:';

    public const LOG_NAME = 'cau-hinh-nguong';

    public const KHOA_LAI_THAP = 'nguong_ti_le_lai_thap_phan_tram';

    public const KHOA_HAO_HUT_PIN = 'nguong_hao_hut_can_pin';

    public const KHOA_MOC_KIEM_THIEU_GIA_VON = 'moc_ngay_kiem_thieu_gia_von';

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
        $daChinh = Cache::store('database')->get(self::TIEN_TO.self::KHOA_MOC_KIEM_THIEU_GIA_VON);

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
        Cache::store('database')->forever(self::TIEN_TO.self::KHOA_MOC_KIEM_THIEU_GIA_VON, $ngay->toDateString());

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
            Cache::store('database')->forget(self::TIEN_TO.$khoa);

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
        return Cache::store('database')->get(self::TIEN_TO.$khoa) !== null;
    }

    private function docSoNguyen(string $khoa): int
    {
        $daChinh = Cache::store('database')->get(self::TIEN_TO.$khoa);

        if ($daChinh !== null) {
            return (int) $daChinh;
        }

        return (int) config("pos.{$khoa}");
    }

    private function ghi(string $khoa, int $giaTri): void
    {
        $giaTriCu = $this->docSoNguyen($khoa);
        $nguonCu = $this->daTungChinh($khoa) ? 'nguoi_doi' : 'mac_dinh';

        Cache::store('database')->forever(self::TIEN_TO.$khoa, $giaTri);

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
}
