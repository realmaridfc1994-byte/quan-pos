<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Domain\Reporting\Queries\GetOwnerProfitDashboard;
use App\Support\Money;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Gate;

final class BanChayLaiThapWidget extends Widget
{
    protected static string $view = 'filament.widgets.ban-chay-lai-thap';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return Gate::allows('view-cost-profit');
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $duLieu = app(GetOwnerProfitDashboard::class)->handle();

        return [
            'thang' => $duLieu['thang'],
            'monCanChuY' => array_map(fn (array $d) => [
                ...$d,
                'revenue_amount_text' => Money::fromInt($d['revenue_amount'])->format(),
                'margin_text' => number_format($d['margin'] * 100, 1).'%',
            ], $duLieu['ban_chay_lai_thap']),
        ];
    }
}
