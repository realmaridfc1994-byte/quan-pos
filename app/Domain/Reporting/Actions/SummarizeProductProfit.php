<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Actions;

use App\Domain\Ordering\Models\OrderItem;
use App\Domain\Reporting\Models\ProductProfitDaily;
use App\Domain\Reporting\Queries\DoanhThuThieuGiaVonTheoNgay;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Tổng hợp MỘT NGÀY vào `product_profit_daily` — Phase 3 Bước 8.
 *
 * Nguồn chân lý cho Action này (KHÔNG áp dụng cho màn hình đọc) là
 * `order_items`/`orders`/`table_sessions`/`stock_movements` — bảng
 * `product_profit_daily` chỉ là bản chốt lại để màn hình chủ quán đọc,
 * KHÔNG BAO GIỜ đọc ngược từ bảng đó (giống SummarizeDailyReport ở Phase 2).
 *
 * Luôn TÍNH LẠI TỪ ĐẦU rồi ghi đè (xoá-rồi-chèn lại) cho đúng ngày — gọi lại
 * nhiều lần cho CÙNG một ngày luôn ra đúng một kết quả.
 *
 * Phần soát doanh thu/giá vốn/hai đường thiếu giá vốn đã dời sang
 * `DoanhThuThieuGiaVonTheoNgay` từ Bước 4B.0 để dùng chung với việc tổng hợp
 * ngày — mọi quy tắc nghiệp vụ ghi ở đầu file đó, không đổi phép tính nào.
 * Action này chỉ còn việc gom theo món và ghi xuống bảng.
 */
final class SummarizeProductProfit
{
    public function __construct(
        private readonly DoanhThuThieuGiaVonTheoNgay $soatThieuGiaVon,
    ) {}

    public function handle(string $date): Collection
    {
        $ngay = Carbon::parse($date)->startOfDay();

        return DB::transaction(function () use ($ngay): Collection {
            $soat = $this->soatThieuGiaVon->handle($ngay->toDateString());

            ProductProfitDaily::query()->where('date', $ngay->toDateString())->delete();

            if ($soat->dongMon->isEmpty()) {
                return collect();
            }

            $idKhongDangTin = $soat->idKhongDangTin();

            return $soat->dongMon
                ->groupBy(fn (OrderItem $item): string => "{$item->product_id}:{$item->product_variant_id}")
                ->map(function (Collection $nhom) use ($ngay, $soat, $idKhongDangTin): ProductProfitDaily {
                    $mauDau = $nhom->first();

                    return ProductProfitDaily::query()->create([
                        'date' => $ngay->toDateString(),
                        'product_id' => $mauDau->product_id,
                        'product_variant_id' => $mauDau->product_variant_id,
                        'quantity_sold' => $nhom->sum('quantity'),
                        'revenue_amount' => $nhom->sum(fn (OrderItem $i): int => $soat->doanhThuCua($i)),
                        'cost_amount' => $nhom->sum(fn (OrderItem $i): int => $soat->giaVonCua($i)),
                        'qty_no_cost' => $this->soLuongTrongTap($nhom, $soat->idThieuGiaVon),
                        'qty_not_served' => $this->soLuongTrongTap($nhom, $soat->idChuaBungRa),
                        'revenue_uncosted_amount' => $nhom
                            ->filter(fn (OrderItem $i): bool => in_array($i->id, $idKhongDangTin, true))
                            ->sum(fn (OrderItem $i): int => $soat->doanhThuCua($i)),
                    ]);
                })
                ->values();
        });
    }

    /**
     * @param  Collection<int, OrderItem>  $nhom
     * @param  list<int>  $idCanDem
     */
    private function soLuongTrongTap(Collection $nhom, array $idCanDem): int
    {
        return (int) $nhom
            ->filter(fn (OrderItem $i): bool => in_array($i->id, $idCanDem, true))
            ->sum('quantity');
    }
}
