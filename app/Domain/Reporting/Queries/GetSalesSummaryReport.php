<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Queries;

use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Reporting\Models\DailySummary;
use App\Domain\Reporting\Models\ProductProfitDaily;
use App\Domain\Reporting\Models\ProductSaleDaily;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Số liệu báo cáo tổng hợp cho máy POS gọi qua API — Phase 4 Bước 4B.1.
 *
 * ── CHỈ ĐỌC BẢNG TỔNG HỢP, KHÔNG BAO GIỜ ĐỌC BẢNG GIAO DỊCH ───────────────
 * Ba nguồn duy nhất: `daily_summaries`, `product_sales_daily`,
 * `product_profit_daily`. TUYỆT ĐỐI không `orders`, `order_items`,
 * `table_sessions`, `payments` — có test tự động gác điều này bằng cách nghe
 * toàn bộ câu SQL sinh ra trong một lần gọi
 * (tests/Feature/Reporting/KhongChamBangGiaoDichTest.php).
 *
 * Vì sao gắt vậy: bảng giao dịch thay đổi từng phút trong lúc bán, mỗi lần mở
 * báo cáo lại ra một số khác; và một câu truy vấn nặng trên `order_items` lúc
 * 8 giờ tối làm chậm chính cái máy đang thu tiền.
 *
 * ── LÃI GỘP KHÔNG BAO GIỜ ĐI MỘT MÌNH ─────────────────────────────────────
 * Mỗi con số lãi gộp luôn kèm khối `chat_luong_du_lieu`: bao nhiêu phần trăm
 * doanh thu đến từ món chưa biết giá vốn. Vượt ngưỡng NGUONG_KHONG_DU_TIN_CAY
 * thì lãi gộp trả về NULL kèm câu giải thích, KHÔNG trả một con số đẹp mà sai
 * — một dashboard trưng số lãi sai còn tệ hơn không có dashboard.
 */
final class GetSalesSummaryReport
{
    /** Quá ngần này phần trăm doanh thu không biết giá vốn thì thôi không trả số lãi nữa. */
    private const NGUONG_KHONG_DU_TIN_CAY = 20;

    private const TOP_N = 10;

    /** @return array<string, mixed> */
    public function handle(Carbon $tu, Carbon $den): array
    {
        $ngay = [$tu->toDateString(), $den->toDateString()];
        $chatLuong = $this->chatLuongDuLieu($ngay);

        return [
            'ky' => [
                'tu' => $tu->toDateString(),
                'den' => $den->toDateString(),
                'so_ngay' => (int) $tu->diffInDays($den) + 1,
            ],
            'doanh_thu' => $this->doanhThu($ngay),
            'theo_ngay' => $this->theoNgay($ngay),
            'top_mon_ban_chay' => $this->topMonBanChay($ngay),
            'chat_luong_du_lieu' => $chatLuong,
            'lai_gop' => $this->laiGop($ngay, (bool) $chatLuong['du_tin_cay']),
        ];
    }

    /**
     * @param  array{0: string, 1: string}  $ngay
     * @return array<string, mixed>
     */
    private function doanhThu(array $ngay): array
    {
        $tong = DailySummary::query()
            ->whereBetween('date', $ngay)
            ->selectRaw('COALESCE(SUM(revenue_amount), 0) as a')
            ->selectRaw('COALESCE(SUM(cash_amount), 0) as b')
            ->selectRaw('COALESCE(SUM(transfer_amount), 0) as c')
            ->selectRaw('COALESCE(SUM(discount_amount), 0) as d')
            ->selectRaw('COALESCE(SUM(table_session_count), 0) as e')
            ->selectRaw('COALESCE(SUM(guest_count), 0) as f')
            ->selectRaw('COALESCE(SUM(cancelled_item_count), 0) as g')
            ->selectRaw('COALESCE(SUM(cancelled_item_amount), 0) as h')
            ->selectRaw('COALESCE(SUM(cash_variance_amount), 0) as i')
            ->first();

        $tongThu = (int) ($tong->a ?? 0);

        return [
            'tong_amount' => $tongThu,
            'tong_text' => Money::fromInt($tongThu)->format(),
            'tien_mat_amount' => (int) ($tong->b ?? 0),
            'chuyen_khoan_amount' => (int) ($tong->c ?? 0),
            'giam_gia_amount' => (int) ($tong->d ?? 0),
            'so_luot_khach' => (int) ($tong->e ?? 0),
            'so_khach' => (int) ($tong->f ?? 0),
            'so_mon_huy' => (int) ($tong->g ?? 0),
            'gia_tri_mon_huy_amount' => (int) ($tong->h ?? 0),
            // Cột DUY NHẤT trong dự án cố ý cho phép âm: âm = két thiếu tiền.
            'chenh_lech_ket_amount' => (int) ($tong->i ?? 0),
        ];
    }

    /**
     * @param  array{0: string, 1: string}  $ngay
     * @return list<array<string, mixed>>
     */
    private function theoNgay(array $ngay): array
    {
        return DailySummary::query()
            ->whereBetween('date', $ngay)
            ->orderBy('date')
            ->get(['date', 'revenue_amount', 'table_session_count', 'guest_count'])
            ->map(fn (DailySummary $d): array => [
                'ngay' => $d->date->toDateString(),
                'doanh_thu_amount' => $d->revenue_amount,
                'so_luot_khach' => $d->table_session_count,
                'so_khach' => $d->guest_count,
            ])
            ->all();
    }

    /**
     * @param  array{0: string, 1: string}  $ngay
     * @return list<array<string, mixed>>
     */
    private function topMonBanChay(array $ngay): array
    {
        $tongHop = ProductSaleDaily::query()
            ->whereBetween('date', $ngay)
            ->selectRaw('product_variant_id, SUM(quantity_sold) as so_luong, SUM(revenue_amount) as doanh_thu')
            ->groupBy('product_variant_id')
            ->orderByDesc('so_luong')
            ->limit(self::TOP_N)
            ->get();

        if ($tongHop->isEmpty()) {
            return [];
        }

        $bienThe = $this->tenBienThe($tongHop->pluck('product_variant_id'));

        return $tongHop
            ->map(fn ($dong): array => [
                'product_variant_id' => (int) $dong->product_variant_id,
                'ten_mon' => $bienThe[(int) $dong->product_variant_id] ?? '(món đã xoá)',
                'so_luong' => (int) $dong->so_luong,
                'doanh_thu_amount' => (int) $dong->doanh_thu,
            ])
            ->all();
    }

    /**
     * Cờ chất lượng dữ liệu — đi kèm MỌI con số lãi gộp, không bao giờ im lặng.
     *
     * Tử số và mẫu số cùng đo theo DÒNG MÓN (cặp cột `item_*` của Bước 4B.0),
     * không trộn với `revenue_amount` vốn đo tiền ĐÃ THU VÀO KÉT — xem
     * docs/schema.md phần "K19 ở cấp NGÀY".
     *
     * @param  array{0: string, 1: string}  $ngay
     * @return array<string, mixed>
     */
    private function chatLuongDuLieu(array $ngay): array
    {
        $tong = DailySummary::query()
            ->whereBetween('date', $ngay)
            ->selectRaw('COALESCE(SUM(item_revenue_amount), 0) as a')
            ->selectRaw('COALESCE(SUM(item_revenue_uncosted_amount), 0) as b')
            ->first();

        $mauSo = (int) ($tong->a ?? 0);
        $chuaBiet = (int) ($tong->b ?? 0);

        // Tỉ lệ phần trăm ĐỂ HIỂN THỊ, tính bằng số nguyên (intdiv) chứ không
        // phải phép chia số thực — luật cấm float cho tiền, CLAUDE.md mục 7.
        $phanTram = $mauSo === 0 ? 0 : intdiv($chuaBiet * 100, $mauSo);
        $duTinCay = $phanTram < self::NGUONG_KHONG_DU_TIN_CAY;

        return [
            'doanh_thu_theo_mon_amount' => $mauSo,
            'doanh_thu_thieu_gia_von_amount' => $chuaBiet,
            'phan_tram_thieu_gia_von' => $phanTram,
            'nguong_khong_du_tin_cay' => self::NGUONG_KHONG_DU_TIN_CAY,
            'du_tin_cay' => $duTinCay,
            'canh_bao' => $this->cauCanhBao($mauSo, $chuaBiet, $phanTram, $duTinCay),
        ];
    }

    private function cauCanhBao(int $mauSo, int $chuaBiet, int $phanTram, bool $duTinCay): ?string
    {
        if ($mauSo === 0) {
            return 'Kỳ này chưa có dòng món nào được tổng hợp — không có gì để tính lãi.';
        }

        if ($chuaBiet === 0) {
            return null;
        }

        $tien = Money::fromInt($chuaBiet)->format();

        if (! $duTinCay) {
            return "{$phanTram}% doanh thu ({$tien}) chưa biết giá vốn — quá ngưỡng ".
                self::NGUONG_KHONG_DU_TIN_CAY.'%. KHÔNG hiện số lãi gộp vì con số đó sẽ sai. '.
                'Nguyên nhân thường gặp: bán lúc kho đang âm, hoặc bếp quên bấm xong món.';
        }

        return "{$phanTram}% doanh thu ({$tien}) chưa biết giá vốn. Số lãi gộp bên dưới ".
            'CHỈ tính trên phần còn lại, không phải lãi của cả kỳ.';
    }

    /**
     * Lãi gộp — trả về NULL (kèm lý do) chứ không trả số khi dữ liệu không đủ
     * tin cậy. Thà hiện "không đủ dữ liệu" còn hơn hiện số sai.
     *
     * @param  array{0: string, 1: string}  $ngay
     * @return array<string, mixed>
     */
    private function laiGop(array $ngay, bool $duTinCay): array
    {
        $tong = ProductProfitDaily::query()
            ->whereBetween('date', $ngay)
            ->selectRaw('COALESCE(SUM(revenue_amount), 0) as a')
            ->selectRaw('COALESCE(SUM(revenue_uncosted_amount), 0) as b')
            ->selectRaw('COALESCE(SUM(cost_amount), 0) as c')
            ->first();

        $doanhThu = (int) ($tong->a ?? 0);
        $chuaBiet = (int) ($tong->b ?? 0);
        $giaVon = (int) ($tong->c ?? 0);
        $daBiet = $doanhThu - $chuaBiet;

        if ($daBiet <= 0 || ! $duTinCay) {
            return [
                'du_lieu_du_de_tinh' => false,
                'lai_gop_amount' => null,
                'gia_von_amount' => null,
                'doanh_thu_da_biet_gia_von_amount' => max(0, $daBiet),
                'ly_do' => $daBiet <= 0
                    ? 'Không một đồng doanh thu nào trong kỳ biết được giá vốn.'
                    : 'Phần doanh thu chưa biết giá vốn vượt ngưỡng cho phép.',
            ];
        }

        return [
            'du_lieu_du_de_tinh' => true,
            'lai_gop_amount' => $daBiet - $giaVon,
            'gia_von_amount' => $giaVon,
            'doanh_thu_da_biet_gia_von_amount' => $daBiet,
            'ly_do' => null,
        ];
    }

    /**
     * @param  Collection<int, mixed>  $id
     * @return array<int, string>
     */
    private function tenBienThe(Collection $id): array
    {
        return ProductVariant::query()
            ->whereIn('id', $id)
            ->with('product:id,name')
            ->get()
            ->mapWithKeys(fn (ProductVariant $v): array => [
                $v->id => trim(($v->product?->name ?? '(món đã xoá)').' '.$v->name),
            ])
            ->all();
    }
}
