<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Domain\Reporting\Queries\GetOwnerProfitDashboard;
use App\Support\Money;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Gate;

final class LaiGopTheoMonWidget extends Widget
{
    protected static string $view = 'filament.widgets.lai-gop-theo-mon';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return Gate::allows('view-cost-profit');
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $duLieu = app(GetOwnerProfitDashboard::class)->handle();

        $ganNhan = fn (array $ds) => array_map(fn (array $d) => [
            ...$d,
            'revenue_amount_text' => Money::fromInt($d['revenue_amount'])->format(),
            'profit_amount_text' => Money::fromInt(abs($d['profit_amount']))->format().($d['profit_amount'] < 0 ? ' (lỗ)' : ''),
            'margin_text' => number_format($d['margin'] * 100, 1).'%',
        ], $ds);

        return [
            'thang' => $duLieu['thang'],
            'theoTong' => $ganNhan($duLieu['lai_gop_theo_tong']),
            'theoTiLe' => $ganNhan($duLieu['lai_gop_theo_ti_le']),
        ];
    }
}
