<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Domain\Reporting\Queries\GetOwnerDashboard;
use App\Domain\Staffing\Enums\UserRole;
use App\Support\Money;
use Filament\Widgets\Widget;

final class Top10MonBanChayWidget extends Widget
{
    protected static string $view = 'filament.widgets.top-10-mon-ban-chay';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->role === UserRole::Owner;
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $top10 = app(GetOwnerDashboard::class)->handle()['top_10_mon_tuan_nay'];

        return [
            'top10' => array_map(fn (array $dong) => [
                ...$dong,
                'revenue_amount_text' => Money::fromInt($dong['revenue_amount'])->format(),
            ], $top10),
        ];
    }
}
