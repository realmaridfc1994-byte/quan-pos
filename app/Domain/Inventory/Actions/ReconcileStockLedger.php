<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\DTO\StockReconciliationResult;
use App\Domain\Inventory\Enums\PurchaseStatus;
use App\Domain\Inventory\Enums\StockMovementRefType;
use App\Domain\Inventory\Enums\StockTakeStatus;
use App\Domain\Inventory\Models\Ingredient;
use App\Domain\Inventory\Models\PurchaseItem;
use App\Domain\Inventory\Models\StockBalance;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Inventory\Models\StockReconciliationNote;
use App\Domain\Inventory\Models\StockTakeItem;
use App\Domain\Ordering\Enums\OrderItemStatus;
use App\Domain\Ordering\Enums\TableSessionStatus;
use App\Domain\Ordering\Models\OrderItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Đối soát sổ cái kho — Phase 3 Bước 9. Đúng ba bất biến theo docs/schema.md:
 *
 *  a. K2 — với MỌI nguyên liệu: cộng hết qty_delta trong sổ cái = qty trong
 *     bảng tồn. Luôn kiểm TOÀN BỘ lịch sử, không có khái niệm khoảng ngày.
 *  b. K3 — cộng hết cost_delta trong sổ cái = total_cost trong bảng tồn.
 *     Cũng luôn kiểm toàn bộ lịch sử.
 *  c. Mọi order_items có served_at đều có dòng sổ cái tương ứng, và ngược
 *     lại. Đây là chỗ DUY NHẤT nhận khoảng ngày (lọc theo served_at) — hai
 *     mục trên không nhận vì là số cộng dồn không thể cắt theo ngày.
 *     Lưu ý dòng 1894 docs/schema.md: dòng tách ra khi huỷ một phần
 *     (split_from_item_id khác rỗng) CŨNG có served_at kế thừa nhưng KHÔNG
 *     có dòng sổ cái riêng (K7) — phải loại trừ, không thì báo lệch giả.
 *
 * Thêm ở Bước 10 (review mục 8.2-M) — HAI NHÁNH CÒN LẠI của K11, cùng khuôn
 * và cùng ảnh hưởng tới mã lỗi như mục c:
 *  f. Mọi dòng phiếu nhập ĐÃ NHẬN đều có dòng sổ cái tương ứng, và ngược lại.
 *  g. Mọi dòng kiểm kê LỆCH của phiếu ĐÃ CHỐT đều có dòng sổ cái điều chỉnh,
 *     và ngược lại. Dòng chưa đếm và dòng khớp không sinh sổ cái (xem
 *     CloseStockTake) nên phải loại trừ, cùng bẫy với dòng tách ở mục c.
 *
 * Thêm ở Bước 10 — HAI MỤC CẢNH BÁO, không phải mục kiểm lệch:
 *  d. Dòng sổ cái has_cost = false: bán lúc kho đang âm nên không xác định
 *     được giá vốn. Sổ sách KHÔNG sai, nhưng lãi gộp của những món đó đang
 *     cao hơn thực tế cho tới khi nhập hàng bù.
 *  e. Dòng món thuộc lượt khách ĐÃ ĐÓNG mà chưa có served_at: bếp quên bấm
 *     xong, nên chưa trừ kho trong khi tiền đã tính đủ.
 * Cả hai KHÔNG làm sach() thành false — xem lý do trong StockReconciliationResult.
 *
 * Luôn tự ghi kết quả vào activity_log (log_name = 'doi-soat-kho') — CHỖ
 * DUY NHẤT màn hình chủ quán đọc để hiện cảnh báo, không tạo bảng DB mới
 * (spatie/laravel-activitylog đã có sẵn, dùng lại đúng cách VerifyApproverPin
 * đã làm).
 */
final class ReconcileStockLedger
{
    public function handle(?Carbon $tuNgay = null, ?Carbon $denNgay = null): StockReconciliationResult
    {
        $lechQty = $this->doiSoatSoLuong();
        $lechCost = $this->doiSoatGiaTri();
        [$thieuSoCai, $moCoiBanMon] = $this->doiSoatServedAt($tuNgay, $denNgay);
        [$thieuNhapHang, $moCoiNhapHang] = $this->doiSoatNhapHang($tuNgay, $denNgay);
        [$thieuKiemKe, $moCoiKiemKe] = $this->doiSoatKiemKe($tuNgay, $denNgay);

        // Gộp ba nhánh lại rồi tách một lần theo ghi chú, sau đó chia về lại
        // từng nhánh — để chỉ đọc bảng ghi chú đúng một lần.
        [$chuaXem, $moCoiDaGhiChu] = $this->tachTheoGhiChu([
            ...$moCoiBanMon, ...$moCoiNhapHang, ...$moCoiKiemKe,
        ]);

        $ketQua = new StockReconciliationResult(
            $lechQty,
            $lechCost,
            $thieuSoCai,
            $this->locTheoNguon($chuaXem, StockMovementRefType::OrderItem),
            $this->domDongThieuGiaVon($tuNgay, $denNgay),
            $this->domDongMonQuenBamXong($tuNgay, $denNgay),
            $moCoiDaGhiChu,
            $thieuNhapHang,
            $this->locTheoNguon($chuaXem, StockMovementRefType::PurchaseItem),
            $thieuKiemKe,
            $this->locTheoNguon($chuaXem, StockMovementRefType::StockTakeItem),
        );

        activity('doi-soat-kho')
            ->withProperties([
                ...$ketQua->toArray(),
                'tu_ngay' => $tuNgay?->toDateString(),
                'den_ngay' => $denNgay?->toDateString(),
            ])
            ->log($ketQua->sach() ? 'Đối soát kho sạch — không lệch.' : 'Đối soát kho phát hiện lệch.');

        return $ketQua;
    }

    /**
     * Mục 4 — CẢNH BÁO: dòng sổ cái không xác định được giá vốn (bán lúc tồn
     * âm). Gom theo nguyên liệu để chủ quán biết nhập bù cái nào trước.
     * Lọc theo occurred_at trong khoảng ngày, giống mục 3.
     *
     * @return list<array{ingredient_id: int, ingredient_name: string, so_dong: int}>
     */
    private function domDongThieuGiaVon(?Carbon $tuNgay, ?Carbon $denNgay): array
    {
        $theoNguyenLieu = StockMovement::query()
            ->where('has_cost', false)
            ->when($tuNgay !== null, fn ($q) => $q->whereDate('occurred_at', '>=', $tuNgay))
            ->when($denNgay !== null, fn ($q) => $q->whereDate('occurred_at', '<=', $denNgay))
            ->selectRaw('ingredient_id, COUNT(*) as so_dong')
            ->groupBy('ingredient_id')
            ->pluck('so_dong', 'ingredient_id');

        if ($theoNguyenLieu->isEmpty()) {
            return [];
        }

        $ten = Ingredient::query()->whereIn('id', $theoNguyenLieu->keys())->pluck('name', 'id');

        return $theoNguyenLieu
            ->map(fn ($soDong, $id): array => [
                'ingredient_id' => (int) $id,
                'ingredient_name' => $ten[$id] ?? "#{$id}",
                'so_dong' => (int) $soDong,
            ])
            ->sortByDesc('so_dong')
            ->values()
            ->all();
    }

    /**
     * Mục 5 — CẢNH BÁO: dòng món thuộc lượt khách ĐÃ ĐÓNG mà chưa có
     * served_at. Tiền đã tính đủ nhưng kho chưa trừ, nên lãi gộp món đó đang
     * cao hơn thực tế. Lọc theo closed_at của lượt khách trong khoảng ngày.
     *
     * @return list<array{order_item_id: int, product_name: string, variant_name: string, table_session_code: string, closed_at: string}>
     */
    private function domDongMonQuenBamXong(?Carbon $tuNgay, ?Carbon $denNgay): array
    {
        return OrderItem::query()
            ->whereNull('served_at')
            ->where('status', '!=', OrderItemStatus::Cancelled)
            ->whereHas('order.tableSession', fn ($q) => $q
                ->where('status', TableSessionStatus::Closed)
                ->when($tuNgay !== null, fn ($qq) => $qq->whereDate('closed_at', '>=', $tuNgay))
                ->when($denNgay !== null, fn ($qq) => $qq->whereDate('closed_at', '<=', $denNgay)))
            ->with('order.tableSession:id,code,closed_at')
            ->get()
            ->map(fn (OrderItem $item): array => [
                'order_item_id' => $item->id,
                'product_name' => $item->product_name,
                'variant_name' => $item->variant_name,
                'table_session_code' => $item->order->tableSession->code,
                'closed_at' => $item->order->tableSession->closed_at?->toDateTimeString() ?? '',
            ])
            ->values()
            ->all();
    }

    /**
     * Mục 6 — NHẬP HÀNG: mọi dòng của phiếu đã nhận phải có dòng sổ cái, và
     * mọi dòng sổ cái nhập hàng phải trỏ về một dòng phiếu ĐÃ NHẬN.
     *
     * Cùng khuôn với mục 3 (bán món): chiều "thiếu sổ cái" lọc theo khoảng
     * ngày (received_at của phiếu), chiều "mồ côi" quét toàn bộ lịch sử vì đó
     * là bất biến dữ liệu chứ không phải số theo kỳ.
     *
     * @return array{0: list<array{purchase_item_id: int, purchase_id: int, purchase_code: string, ingredient_name: string, received_at: string}>, 1: list<array{stock_movement_id: int, ref_id: ?int, nguon: string}>}
     */
    private function doiSoatNhapHang(?Carbon $tuNgay, ?Carbon $denNgay): array
    {
        $dongDaNhan = PurchaseItem::query()
            ->whereHas('purchase', fn ($q) => $q
                ->where('status', PurchaseStatus::Received)
                ->when($tuNgay !== null, fn ($qq) => $qq->whereDate('received_at', '>=', $tuNgay))
                ->when($denNgay !== null, fn ($qq) => $qq->whereDate('received_at', '<=', $denNgay)))
            ->with(['purchase:id,code,received_at', 'ingredient:id,name'])
            ->get();

        $idDaGhiSo = StockMovement::query()
            ->where('ref_type', StockMovementRefType::PurchaseItem)
            ->pluck('ref_id')
            ->unique();

        $thieu = [];
        foreach ($dongDaNhan as $dong) {
            if ($idDaGhiSo->contains($dong->id)) {
                continue;
            }

            $thieu[] = [
                'purchase_item_id' => $dong->id,
                'purchase_id' => $dong->purchase_id,
                'purchase_code' => $dong->purchase->code ?? "#{$dong->purchase_id}",
                'ingredient_name' => $dong->ingredient->name ?? "#{$dong->ingredient_id}",
                'received_at' => $dong->purchase->received_at?->toDateTimeString() ?? '',
            ];
        }

        // Chiều ngược: dòng sổ cái nhập hàng trỏ về dòng phiếu không còn ở
        // trạng thái đã nhận (hoặc không còn tồn tại) là dòng mồ côi.
        $soCaiNhapHang = StockMovement::query()
            ->where('ref_type', StockMovementRefType::PurchaseItem)
            ->get(['id', 'ref_id']);

        $idHopLe = PurchaseItem::query()
            ->whereIn('id', $soCaiNhapHang->pluck('ref_id')->unique())
            ->whereHas('purchase', fn ($q) => $q->where('status', PurchaseStatus::Received))
            ->pluck('id');

        return [$thieu, $this->danhDauNguon($soCaiNhapHang, $idHopLe, StockMovementRefType::PurchaseItem)];
    }

    /**
     * Mục 7 — KIỂM KÊ: mọi dòng LỆCH của phiếu kiểm kê đã chốt phải có dòng sổ
     * cái điều chỉnh, và ngược lại.
     *
     * Dòng chưa đếm (counted_qty rỗng) và dòng khớp (diff_qty = 0) KHÔNG sinh
     * sổ cái — xem CloseStockTake. Phải loại trừ, không thì báo lệch giả, đúng
     * kiểu bẫy đã gặp với dòng tách ra khi huỷ một phần ở mục 3.
     *
     * @return array{0: list<array{stock_take_item_id: int, stock_take_id: int, ingredient_name: string, diff_qty: int, closed_at: string}>, 1: list<array{stock_movement_id: int, ref_id: ?int, nguon: string}>}
     */
    private function doiSoatKiemKe(?Carbon $tuNgay, ?Carbon $denNgay): array
    {
        $dongLechDaChot = StockTakeItem::query()
            ->whereNotNull('counted_qty')
            ->where('diff_qty', '<>', 0)
            ->whereHas('stockTake', fn ($q) => $q
                ->where('status', StockTakeStatus::Closed)
                ->when($tuNgay !== null, fn ($qq) => $qq->whereDate('closed_at', '>=', $tuNgay))
                ->when($denNgay !== null, fn ($qq) => $qq->whereDate('closed_at', '<=', $denNgay)))
            ->with(['stockTake:id,closed_at', 'ingredient:id,name'])
            ->get();

        $idDaGhiSo = StockMovement::query()
            ->where('ref_type', StockMovementRefType::StockTakeItem)
            ->pluck('ref_id')
            ->unique();

        $thieu = [];
        foreach ($dongLechDaChot as $dong) {
            if ($idDaGhiSo->contains($dong->id)) {
                continue;
            }

            $thieu[] = [
                'stock_take_item_id' => $dong->id,
                'stock_take_id' => $dong->stock_take_id,
                'ingredient_name' => $dong->ingredient->name ?? "#{$dong->ingredient_id}",
                'diff_qty' => (int) $dong->diff_qty,
                'closed_at' => $dong->stockTake->closed_at?->toDateTimeString() ?? '',
            ];
        }

        $soCaiKiemKe = StockMovement::query()
            ->where('ref_type', StockMovementRefType::StockTakeItem)
            ->get(['id', 'ref_id']);

        $idHopLe = StockTakeItem::query()
            ->whereIn('id', $soCaiKiemKe->pluck('ref_id')->unique())
            ->whereNotNull('counted_qty')
            ->where('diff_qty', '<>', 0)
            ->whereHas('stockTake', fn ($q) => $q->where('status', StockTakeStatus::Closed))
            ->pluck('id');

        return [$thieu, $this->danhDauNguon($soCaiKiemKe, $idHopLe, StockMovementRefType::StockTakeItem)];
    }

    /**
     * Dòng sổ cái nào không trỏ về một chứng từ hợp lệ thì là mồ côi. Gắn kèm
     * nguồn để lát nữa chia lại về đúng mục khi in.
     *
     * @param  Collection<int, StockMovement>  $soCai
     * @param  Collection<int, int>  $idHopLe
     * @return list<array{stock_movement_id: int, ref_id: ?int, nguon: string}>
     */
    private function danhDauNguon(Collection $soCai, Collection $idHopLe, StockMovementRefType $nguon): array
    {
        return $soCai
            ->reject(fn ($dong) => $idHopLe->contains($dong->ref_id))
            ->map(fn ($dong) => [
                'stock_movement_id' => $dong->id,
                'ref_id' => $dong->ref_id,
                'nguon' => $nguon->value,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<array{stock_movement_id: int, ref_id: ?int, nguon: string}>  $moCoi
     * @return list<array{stock_movement_id: int, ref_id: ?int, nguon: string}>
     */
    private function locTheoNguon(array $moCoi, StockMovementRefType $nguon): array
    {
        return array_values(array_filter($moCoi, fn ($dong) => $dong['nguon'] === $nguon->value));
    }

    /**
     * Tách danh sách dòng mồ côi thành hai: chưa ai xem, và ĐÃ có người xem
     * và xác nhận không phải lỗi (xem AcknowledgeOrphanMovement).
     *
     * Sổ cái không xoá được, nên không có cách nào làm một dòng mồ côi biến
     * mất. Không có bước này thì nó báo đỏ mãi mãi và người ta sẽ quen với
     * màu đỏ tới mức bỏ qua cả lệch thật.
     *
     * @param  list<array{stock_movement_id: int, ref_id: ?int}>  $moCoi
     * @return array{0: list<array{stock_movement_id: int, ref_id: ?int}>, 1: list<array{stock_movement_id: int, ref_id: ?int, note: string, reason: string, acknowledged_by: string, acknowledged_at: string}>}
     */
    private function tachTheoGhiChu(array $moCoi): array
    {
        if ($moCoi === []) {
            return [[], []];
        }

        $ghiChu = StockReconciliationNote::query()
            ->whereIn('stock_movement_id', array_column($moCoi, 'stock_movement_id'))
            ->with('acknowledgedBy:id,name')
            ->get()
            ->keyBy('stock_movement_id');

        $chuaXem = [];
        $daXem = [];

        foreach ($moCoi as $dong) {
            $note = $ghiChu->get($dong['stock_movement_id']);

            if ($note === null) {
                $chuaXem[] = $dong;

                continue;
            }

            $daXem[] = [
                ...$dong,
                'note' => $note->note,
                'reason' => $note->reason,
                'acknowledged_by' => $note->acknowledgedBy->name,
                'acknowledged_at' => $note->acknowledged_at->toDateTimeString(),
            ];
        }

        return [$chuaXem, $daXem];
    }

    /** @return list<array{ingredient_id: int, ingredient_name: string, so_cai: int, ton_kho: int, lech: int}> */
    private function doiSoatSoLuong(): array
    {
        return $this->soSanh(
            soCai: StockMovement::query()->selectRaw('ingredient_id, SUM(qty_delta) as tong')->groupBy('ingredient_id')->pluck('tong', 'ingredient_id'),
            tonKho: StockBalance::query()->pluck('qty', 'ingredient_id'),
        );
    }

    /** @return list<array{ingredient_id: int, ingredient_name: string, so_cai: int, ton_kho: int, lech: int}> */
    private function doiSoatGiaTri(): array
    {
        return $this->soSanh(
            soCai: StockMovement::query()->selectRaw('ingredient_id, SUM(cost_delta) as tong')->groupBy('ingredient_id')->pluck('tong', 'ingredient_id'),
            tonKho: StockBalance::query()->pluck('total_cost', 'ingredient_id'),
        );
    }

    /**
     * @param  Collection<int, int>  $soCai
     * @param  Collection<int, int>  $tonKho
     * @return list<array{ingredient_id: int, ingredient_name: string, so_cai: int, ton_kho: int, lech: int}>
     */
    private function soSanh(Collection $soCai, Collection $tonKho): array
    {
        $idTatCa = $soCai->keys()->merge($tonKho->keys())->unique();
        if ($idTatCa->isEmpty()) {
            return [];
        }

        $ten = Ingredient::query()->whereIn('id', $idTatCa)->pluck('name', 'id');

        $ketQua = [];
        foreach ($idTatCa as $id) {
            $giaTriSoCai = (int) ($soCai[$id] ?? 0);
            $giaTriTonKho = (int) ($tonKho[$id] ?? 0);

            if ($giaTriSoCai !== $giaTriTonKho) {
                $ketQua[] = [
                    'ingredient_id' => (int) $id,
                    'ingredient_name' => $ten[$id] ?? "#{$id}",
                    'so_cai' => $giaTriSoCai,
                    'ton_kho' => $giaTriTonKho,
                    'lech' => $giaTriTonKho - $giaTriSoCai,
                ];
            }
        }

        return $ketQua;
    }

    /**
     * @return array{0: list<array{order_item_id: int, product_name: string, variant_name: string, served_at: string}>, 1: list<array{stock_movement_id: int, ref_id: ?int, nguon: string}>}
     */
    private function doiSoatServedAt(?Carbon $tuNgay, ?Carbon $denNgay): array
    {
        $donMonServed = OrderItem::query()
            ->whereNotNull('served_at')
            ->whereNull('split_from_item_id')
            ->when($tuNgay !== null, fn ($q) => $q->whereDate('served_at', '>=', $tuNgay))
            ->when($denNgay !== null, fn ($q) => $q->whereDate('served_at', '<=', $denNgay))
            ->with('productVariant.recipes')
            ->get();

        $idDaTru = StockMovement::query()
            ->where('ref_type', StockMovementRefType::OrderItem)
            ->pluck('ref_id')
            ->unique();

        $thieuSoCai = [];
        foreach ($donMonServed as $item) {
            if (! $item->productVariant->deducts_stock || $item->productVariant->recipes->isEmpty()) {
                continue;
            }

            if (! $idDaTru->contains($item->id)) {
                $thieuSoCai[] = [
                    'order_item_id' => $item->id,
                    'product_name' => $item->product_name,
                    'variant_name' => $item->variant_name,
                    'served_at' => $item->served_at->toDateTimeString(),
                ];
            }
        }

        // Ngược lại: mọi dòng sổ cái ref_type=order_item phải trỏ về một
        // order_item CÓ served_at — không lọc theo khoảng ngày (bất biến dữ
        // liệu, không phải số theo kỳ).
        $soCaiOrderItem = StockMovement::query()
            ->where('ref_type', StockMovementRefType::OrderItem)
            ->get(['id', 'ref_id']);

        $idDaServed = OrderItem::query()
            ->whereIn('id', $soCaiOrderItem->pluck('ref_id')->unique())
            ->whereNotNull('served_at')
            ->pluck('id');

        return [$thieuSoCai, $this->danhDauNguon($soCaiOrderItem, $idDaServed, StockMovementRefType::OrderItem)];
    }
}
