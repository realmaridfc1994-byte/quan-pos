<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\Staffing\Enums\UserRole;
use App\Filament\Widgets\DoanhThu7NgayWidget;
use App\Filament\Widgets\DoanhThuTongQuanWidget;
use App\Filament\Widgets\Top10MonBanChayWidget;
use Filament\Pages\Page;

/**
 * Phase 2 Bước 8 — màn hình chủ quán. CHỈ owner truy cập được (canAccess()),
 * xem được trên điện thoại (Filament responsive sẵn, không cần thêm gì).
 *
 * Trang này không tự truy vấn gì — chỉ ghép ba widget, mỗi widget tự đọc
 * qua GetOwnerDashboard (CHỈ đọc daily_summaries/product_sales_daily).
 */
final class BaoCaoChuQuan extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'Báo cáo';

    protected static ?string $title = 'Báo cáo tổng hợp';

    protected static string $view = 'filament.pages.bao-cao-chu-quan';

    public static function canAccess(): bool
    {
        return auth()->user()?->role === UserRole::Owner;
    }

    protected function getHeaderWidgets(): array
    {
        return [
            DoanhThuTongQuanWidget::class,
            DoanhThu7NgayWidget::class,
            Top10MonBanChayWidget::class,
        ];
    }
}
