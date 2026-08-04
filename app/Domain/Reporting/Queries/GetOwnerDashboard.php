<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Queries;

use App\Domain\Reporting\Models\DailySummary;
use App\Domain\Reporting\Models\ProductSaleDaily;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Số liệu cho màn hình chủ quán — Phase 2 Bước 8.
 *
 * CHỈ đọc từ `daily_summaries`/`product_sales_daily` — TUYỆT ĐỐI KHÔNG đọc
 * `orders`/`order_items`/`payments` (cạm bẫy số 6). Hệ quả: "hôm nay" chỉ có
 * số liệu SAU KHI có ít nhất một ca đóng trong ngày — xem docs/viec-ton.md.
 */
final class GetOwnerDashboard
{
    /** Chênh lệch két từ mức này (đồng, trị tuyệt đối) trở lên coi là bất thường. */
    private const NGUONG_CHENH_LECH_KET = 50_000;

    /** Tỉ lệ (giá trị món huỷ / doanh thu) từ mức này trở lên coi là cao. */
    private const NGUONG_TI_LE_HUY = 0.05;

    /** @return array<string, mixed> */
    public function handle(): array
    {
        $homNay = Carbon::today();

        // Cần tới 8 ngày trong bộ nhớ: 7 ngày cho biểu đồ/cảnh báo (hôm nay
        // đến hôm nay-6) CỘNG THÊM đúng một ngày xa hơn (hôm nay-7) chỉ để so
        // sánh "cùng thứ tuần trước" — không nằm trong biểu đồ 7 ngày.
        $theoNgay = DailySummary::query()
            ->whereBetween('date', [$homNay->clone()->subDays(7)->toDateString(), $homNay->toDateString()])
            ->get()
            ->keyBy(fn (DailySummary $d) => $d->date->toDateString());

        return [
            'hom_nay' => $this->tomTat($theoNgay, $homNay),
            'hom_qua' => $this->tomTat($theoNgay, $homNay->clone()->subDay()),
            'cung_thu_tuan_truoc' => $this->tomTat($theoNgay, $homNay->clone()->subWeek()),
            'bieu_do_7_ngay' => $this->bieuDo7Ngay($theoNgay, $homNay),
            'top_10_mon_tuan_nay' => $this->top10MonTuanNay($homNay),
            'canh_bao' => $this->canhBao($theoNgay, $homNay),
        ];
    }

    /**
     * @param  Collection<string, DailySummary>  $theoNgay
     * @return array{date: string, revenue_amount: int, revenue_amount_text: string, table_session_count: int}
     */
    private function tomTat(Collection $theoNgay, Carbon $ngay): array
    {
        $d = $theoNgay->get($ngay->toDateString());

        return [
            'date' => $ngay->toDateString(),
            'revenue_amount' => $d->revenue_amount ?? 0,
            'revenue_amount_text' => Money::fromInt($d->revenue_amount ?? 0)->format(),
            'table_session_count' => $d->table_session_count ?? 0,
        ];
    }

    /**
     * @param  Collection<string, DailySummary>  $theoNgay
     * @return list<array{date: string, label: string, revenue_amount: int}>
     */
    private function bieuDo7Ngay(Collection $theoNgay, Carbon $homNay): array
    {
        $ketQua = [];

        for ($i = 6; $i >= 0; $i--) {
            $ngay = $homNay->clone()->subDays($i);
            $d = $theoNgay->get($ngay->toDateString());

            $ketQua[] = [
                'date' => $ngay->toDateString(),
                'label' => $ngay->translatedFormat('d/m'),
                'revenue_amount' => $d->revenue_amount ?? 0,
            ];
        }

        return $ketQua;
    }

    /** @return list<array{product_id: int, product_name: string, quantity_sold: int, revenue_amount: int}> */
    private function top10MonTuanNay(Carbon $homNay): array
    {
        return ProductSaleDaily::query()
            ->whereBetween('date', [$homNay->clone()->subDays(6)->toDateString(), $homNay->toDateString()])
            ->with('product')
            ->selectRaw('product_id, SUM(quantity_sold) as tong_so_luong, SUM(revenue_amount) as tong_doanh_thu')
            ->groupBy('product_id')
            ->orderByDesc('tong_so_luong')
            ->limit(10)
            ->get()
            ->map(fn ($dong) => [
                'product_id' => (int) $dong->product_id,
                'product_name' => $dong->product->name,
                'quantity_sold' => (int) $dong->tong_so_luong,
                'revenue_amount' => (int) $dong->tong_doanh_thu,
            ])
            ->all();
    }

    /**
     * Chỉ xét đúng 7 ngày gần nhất (không tính ngày thứ 8 chỉ dùng để so
     * sánh "cùng thứ tuần trước").
     *
     * @param  Collection<string, DailySummary>  $theoNgay
     * @return list<array{date: string, message: string}>
     */
    private function canhBao(Collection $theoNgay, Carbon $homNay): array
    {
        $canhBao = [];

        for ($i = 6; $i >= 0; $i--) {
            $ngayStr = $homNay->clone()->subDays($i)->toDateString();
            $d = $theoNgay->get($ngayStr);

            if ($d === null) {
                continue;
            }

            if (abs($d->cash_variance_amount) >= self::NGUONG_CHENH_LECH_KET) {
                $huongChenh = $d->cash_variance_amount < 0 ? 'thiếu' : 'thừa';
                $canhBao[] = [
                    'date' => $ngayStr,
                    'message' => "Ngày {$ngayStr}: két {$huongChenh} ".Money::fromInt(abs($d->cash_variance_amount))->format(),
                ];
            }

            if ($d->revenue_amount > 0 && ($d->cancelled_item_amount / $d->revenue_amount) >= self::NGUONG_TI_LE_HUY) {
                $tiLe = round($d->cancelled_item_amount / $d->revenue_amount * 100);
                $canhBao[] = [
                    'date' => $ngayStr,
                    'message' => "Ngày {$ngayStr}: giá trị món huỷ chiếm {$tiLe}% doanh thu — cao bất thường.",
                ];
            }
        }

        return $canhBao;
    }
}
