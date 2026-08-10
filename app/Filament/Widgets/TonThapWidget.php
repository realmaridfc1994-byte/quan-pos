<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Domain\Reporting\Queries\GetOwnerProfitDashboard;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Gate;

final class TonThapWidget extends Widget
{
    protected static string $view = 'filament.widgets.ton-thap';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return Gate::allows('view-cost-profit');
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        return [
            'tonThap' => app(GetOwnerProfitDashboard::class)->handle()['ton_thap'],
        ];
    }
}
