<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Domain\Reporting\Queries\GetOwnerDashboard;
use App\Domain\Staffing\Enums\UserRole;
use Filament\Widgets\ChartWidget;

final class DoanhThu7NgayWidget extends ChartWidget
{
    protected static ?string $heading = 'Doanh thu 7 ngày gần nhất';

    public static function canView(): bool
    {
        return auth()->user()?->role === UserRole::Owner;
    }

    protected function getData(): array
    {
        $bieuDo = app(GetOwnerDashboard::class)->handle()['bieu_do_7_ngay'];

        return [
            'datasets' => [
                [
                    'label' => 'Doanh thu (đồng)',
                    'data' => array_column($bieuDo, 'revenue_amount'),
                    'backgroundColor' => '#f59e0b',
                    'borderColor' => '#f59e0b',
                ],
            ],
            'labels' => array_column($bieuDo, 'label'),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
