<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Support\Money;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Gate;
use Spatie\Activitylog\Models\Activity;

/**
 * Cảnh báo đối soát kho — Phase 3 Bước 9. CHỈ đọc activity_log
 * (log_name = 'doi-soat-kho', ghi bởi ReconcileStockLedger), không tự truy
 * vấn stock_movements/stock_balances/order_items.
 */
final class CanhBaoDoiSoatKhoWidget extends Widget
{
    protected static string $view = 'filament.widgets.canh-bao-doi-soat-kho';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return Gate::allows('view-cost-profit');
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $lanGanNhat = Activity::query()->where('log_name', 'doi-soat-kho')->latest('id')->first();

        if ($lanGanNhat === null) {
            return ['daChay' => false];
        }

        $props = $lanGanNhat->properties;

        return [
            'daChay' => true,
            'sach' => (bool) $props->get('sach'),
            'thoiDiem' => $lanGanNhat->created_at->format('H:i d/m/Y'),
            'lechQty' => array_map(fn (array $d) => [
                ...$d,
                'lech_text' => ($d['lech'] > 0 ? '+' : '').$d['lech'],
            ], $props->get('lech_qty', [])),
            'lechCost' => array_map(fn (array $d) => [
                ...$d,
                'lech_text' => ($d['lech'] > 0 ? '+' : '-').Money::fromInt(abs($d['lech']))->format(),
            ], $props->get('lech_cost', [])),
            'thieuSoCai' => $props->get('thieu_so_cai', []),
            'soCaiMoCoi' => $props->get('so_cai_mo_coi', []),
            // Hai mục CẢNH BÁO (Bước 10) — không tính vào "sạch/lệch", chỉ là
            // việc cần dọn. Lần đối soát cũ chạy trước Bước 10 không có hai
            // khoá này trong nhật ký, nên mặc định mảng rỗng.
            'thieuGiaVon' => $props->get('thieu_gia_von', []),
            'quenBamXong' => $props->get('quen_bam_xong', []),
        ];
    }
}
