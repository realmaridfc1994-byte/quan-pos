<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Queries;

use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Inventory\Models\Ingredient;
use App\Domain\Inventory\Models\StockBalance;
use App\Domain\Reporting\Models\IngredientWasteMonthly;
use App\Domain\Reporting\Models\ProductProfitDaily;
use App\Support\CauHinhQuan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Số liệu lãi gộp/hao hụt/tồn thấp cho màn hình chủ quán — Phase 3 Bước 8.
 *
 * CHỈ đọc `product_profit_daily`/`ingredient_waste_monthly`/`stock_balances`
 * — TUYỆT ĐỐI KHÔNG đọc `order_items`/`orders`/`stock_movements` (mục 4 đề
 * bài). `stock_balances` là bảng TỒN HIỆN TẠI (không phải sổ cái), nên đọc
 * thẳng để cảnh báo tồn thấp là hợp lệ — không phạm luật này.
 */
final class GetOwnerProfitDashboard
{
    /** Lấy top bao nhiêu dòng cho mỗi bảng xếp hạng. */
    private const TOP_N = 20;

    public function __construct(
        private readonly CauHinhQuan $cauHinhQuan,
    ) {}

    /** @return array<string, mixed> */
    public function handle(): array
    {
        $dauThangNay = Carbon::today()->startOfMonth();
        $tongHopThangNay = $this->tongHopThangNay($dauThangNay, Carbon::today());

        return [
            'thang' => $dauThangNay->translatedFormat('m/Y'),
            'nguong_lai_thap_phan_tram' => $this->cauHinhQuan->nguongLaiThapPhanTram(),
            'canh_bao_moc_ngay' => $this->canhBaoMocNgay($dauThangNay),
            'lai_gop_theo_tong' => $tongHopThangNay->sortByDesc('profit_amount')->take(self::TOP_N)->values()->all(),
            'lai_gop_theo_ti_le' => $tongHopThangNay
                ->filter(fn (array $d) => $d['revenue_amount'] > 0)
                ->sortByDesc('margin')
                ->take(self::TOP_N)
                ->values()
                ->all(),
            'ban_chay_lai_thap' => $this->banChayLaiThap($tongHopThangNay),
            'thieu_gia_von' => $this->tongKetThieuGiaVon($tongHopThangNay),
            'hao_hut_thang_nay' => $this->haoHutThangNay($dauThangNay),
            'ton_thap' => $this->tonThap(),
        ];
    }

    /**
     * @return Collection<int, array{product_id: int, product_variant_id: int, product_name: string, variant_name: string, quantity_sold: int, revenue_amount: int, cost_amount: int, profit_amount: int, margin: float}>
     */
    private function tongHopThangNay(Carbon $tu, Carbon $den): Collection
    {
        $tongHop = ProductProfitDaily::query()
            ->whereBetween('date', [$tu->toDateString(), $den->toDateString()])
            ->selectRaw('product_id, product_variant_id, SUM(quantity_sold) as so_luong, SUM(revenue_amount) as doanh_thu, SUM(cost_amount) as gia_von, SUM(qty_no_cost) as sl_thieu_gia_von, SUM(qty_not_served) as sl_chua_bung_ra')
            ->groupBy('product_id', 'product_variant_id')
            ->get();

        $bienThe = ProductVariant::query()
            ->whereIn('id', $tongHop->pluck('product_variant_id'))
            ->with('product:id,name')
            ->get()
            ->keyBy('id');

        return $tongHop->map(function ($dong) use ($bienThe): array {
            $v = $bienThe->get((int) $dong->product_variant_id);
            $doanhThu = (int) $dong->doanh_thu;
            $giaVon = (int) $dong->gia_von;
            $laiGop = $doanhThu - $giaVon;
            $soLuong = (int) $dong->so_luong;
            $thieuGiaVon = (int) $dong->sl_thieu_gia_von;
            $chuaBungRa = (int) $dong->sl_chua_bung_ra;
            $tenMon = $v?->product?->name ?? '(món đã xoá)';
            $margin = $doanhThu > 0 ? $laiGop / $doanhThu : 0.0;

            return [
                'product_id' => (int) $dong->product_id,
                'product_variant_id' => (int) $dong->product_variant_id,
                'product_name' => $tenMon,
                'variant_name' => $v?->name ?? '',
                'quantity_sold' => $soLuong,
                'revenue_amount' => $doanhThu,
                'cost_amount' => $giaVon,
                'profit_amount' => $laiGop,
                'margin' => $margin,
                'qty_no_cost' => $thieuGiaVon,
                'qty_not_served' => $chuaBungRa,
                'thieu_gia_von' => $thieuGiaVon > 0 || $chuaBungRa > 0,
                'canh_bao' => $this->cauCanhBao($tenMon, $soLuong, $thieuGiaVon, $chuaBungRa, $margin),
            ];
        });
    }

    /**
     * Câu nói thẳng khi khoảng đang xem có phần CHƯA ĐƯỢC KIỂM phần thiếu giá
     * vốn (bắt đầu trước mốc trong cấu hình).
     *
     * Vì sao cần: hai cột qty_no_cost/qty_not_served chỉ có số từ lần tổng hợp
     * sau khi chúng ra đời. Dòng chốt từ trước mang số 0 — màn hình sẽ hiện
     * "không có cảnh báo nào" cho đúng những tháng chưa ai đếm. Cảnh báo IM
     * LẶNG còn tệ hơn không có cảnh báo: không có thì người ta còn nghi ngờ.
     */
    private function canhBaoMocNgay(Carbon $dauKhoangDangXem): ?string
    {
        $moc = $this->cauHinhQuan->mocNgayKiemThieuGiaVon();

        if ($dauKhoangDangXem->greaterThanOrEqualTo($moc)) {
            return null;
        }

        return "Số liệu trước ngày {$moc->format('d/m/Y')} chưa được kiểm phần thiếu giá vốn — ".
            'con số lãi có thể cao hơn thực tế.';
    }

    /**
     * Dòng tổng đặt ở ĐẦU TRANG báo cáo: có bao nhiêu món đang bị thiếu giá
     * vốn. Không món nào thiếu thì `so_mon` = 0 và màn hình không hiện gì cả.
     *
     * @param  Collection<int, array<string, mixed>>  $tongHopThangNay
     * @return array{so_mon: int, qty_no_cost: int, qty_not_served: int, cau: ?string}
     */
    private function tongKetThieuGiaVon(Collection $tongHopThangNay): array
    {
        $monThieu = $tongHopThangNay->filter(fn (array $d): bool => $d['thieu_gia_von']);
        $soMon = $monThieu->count();

        return [
            'so_mon' => $soMon,
            'qty_no_cost' => (int) $monThieu->sum('qty_no_cost'),
            'qty_not_served' => (int) $monThieu->sum('qty_not_served'),
            'cau' => $soMon === 0
                ? null
                : "Tháng này có {$soMon} món bị thiếu giá vốn — lãi gộp dưới đây cao hơn thực tế.",
        ];
    }

    /**
     * Câu cảnh báo tiếng Việt cho đúng món đó — viết như nói với chủ quán,
     * nêu rõ CON SỐ LÃI ĐANG CAO HƠN THỰC TẾ chứ không chỉ nói "thiếu dữ liệu".
     * Món nào không thiếu gì thì trả về null (màn hình không hiện dấu nào).
     */
    private function cauCanhBao(string $tenMon, int $soLuong, int $thieuGiaVon, int $chuaBungRa, float $margin): ?string
    {
        if ($thieuGiaVon === 0 && $chuaBungRa === 0) {
            return null;
        }

        $ve = [];
        if ($thieuGiaVon > 0) {
            $ve[] = "{$thieuGiaVon} phần bán lúc kho đang âm nên chưa tính được giá vốn";
        }
        if ($chuaBungRa > 0) {
            $ve[] = "{$chuaBungRa} phần bếp chưa bấm xong";
        }

        $tiLe = number_format($margin * 100, 1).'%';

        return "Trong {$soLuong} phần {$tenMon} tháng này, ".implode(', và ', $ve).
            ". Con số lãi {$tiLe} đang CAO HƠN thực tế.";
    }

    /**
     * "Bán chạy" = số lượng bán >= trung bình số lượng bán mọi món tháng này.
     * "Lãi thấp" = tỉ lệ lãi dưới ngưỡng chủ quán đặt (mặc định 25%, chỉnh
     * được trên màn hình Filament "Ngưỡng cảnh báo" — xem CauHinhQuan).
     *
     * @param  Collection<int, array<string, mixed>>  $tongHopThangNay
     * @return list<array<string, mixed>>
     */
    private function banChayLaiThap(Collection $tongHopThangNay): array
    {
        if ($tongHopThangNay->isEmpty()) {
            return [];
        }

        $trungBinhSoLuong = $tongHopThangNay->avg('quantity_sold');
        $nguongLaiThap = $this->cauHinhQuan->nguongLaiThapTiLe();

        return $tongHopThangNay
            ->filter(fn (array $d) => $d['revenue_amount'] > 0
                && $d['quantity_sold'] >= $trungBinhSoLuong
                && $d['margin'] < $nguongLaiThap)
            ->sortBy('margin')
            ->take(self::TOP_N)
            ->values()
            ->all();
    }

    /** @return list<array{ingredient_id: int, ingredient_name: string, waste_qty: int, waste_cost: int, waste_cost_thang_truoc: int}> */
    private function haoHutThangNay(Carbon $dauThangNay): array
    {
        $dauThangTruoc = $dauThangNay->clone()->subMonthNoOverflow();

        $thangNay = IngredientWasteMonthly::query()->where('month', $dauThangNay->toDateString())->get()->keyBy('ingredient_id');
        $thangTruoc = IngredientWasteMonthly::query()->where('month', $dauThangTruoc->toDateString())->get()->keyBy('ingredient_id');

        $idNguyenLieu = $thangNay->keys()->merge($thangTruoc->keys())->unique();
        if ($idNguyenLieu->isEmpty()) {
            return [];
        }

        $nguyenLieu = Ingredient::query()->whereIn('id', $idNguyenLieu)->get(['id', 'name'])->keyBy('id');

        return $idNguyenLieu
            ->map(fn (int $id): array => [
                'ingredient_id' => $id,
                'ingredient_name' => $nguyenLieu->get($id)?->name ?? '(nguyên liệu đã xoá)',
                'waste_qty' => $thangNay->get($id)?->waste_qty ?? 0,
                'waste_cost' => $thangNay->get($id)?->waste_cost ?? 0,
                'waste_cost_thang_truoc' => $thangTruoc->get($id)?->waste_cost ?? 0,
            ])
            ->sortByDesc('waste_cost')
            ->values()
            ->all();
    }

    /** @return list<array{ingredient_id: int, ingredient_name: string, qty: int, min_qty: int}> */
    private function tonThap(): array
    {
        return StockBalance::query()
            ->join('ingredients', 'ingredients.id', '=', 'stock_balances.ingredient_id')
            ->where('ingredients.min_qty', '>', 0)
            ->whereColumn('stock_balances.qty', '<=', 'ingredients.min_qty')
            ->orderBy('stock_balances.qty')
            ->get(['ingredients.id as ingredient_id', 'ingredients.name as ingredient_name', 'ingredients.min_qty', 'stock_balances.qty'])
            ->map(fn ($dong): array => [
                'ingredient_id' => (int) $dong->ingredient_id,
                'ingredient_name' => $dong->ingredient_name,
                'qty' => (int) $dong->qty,
                'min_qty' => (int) $dong->min_qty,
            ])
            ->all();
    }
}
