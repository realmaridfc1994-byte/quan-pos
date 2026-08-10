<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Domain\Reporting\Queries\GetOwnerProfitDashboard;
use App\Support\Money;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Gate;

final class HaoHutThangNayWidget extends Widget
{
    protected static string $view = 'filament.widgets.hao-hut-thang-nay';

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
            'haoHut' => array_map(fn (array $d) => [
                ...$d,
                'waste_cost_text' => Money::fromInt($d['waste_cost'])->format(),
                'waste_cost_thang_truoc_text' => Money::fromInt($d['waste_cost_thang_truoc'])->format(),
                'chenh_lech' => $d['waste_cost'] - $d['waste_cost_thang_truoc'],
            ], $duLieu['hao_hut_thang_nay']),
        ];
    }
}
