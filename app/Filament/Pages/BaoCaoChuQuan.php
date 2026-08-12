<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\Staffing\Enums\UserRole;
use App\Filament\Widgets\BanChayLaiThapWidget;
use App\Filament\Widgets\CanhBaoDoiSoatKhoWidget;
use App\Filament\Widgets\CanhBaoThieuGiaVonWidget;
use App\Filament\Widgets\DoanhThu7NgayWidget;
use App\Filament\Widgets\DoanhThuTongQuanWidget;
use App\Filament\Widgets\HaoHutThangNayWidget;
use App\Filament\Widgets\HaoHutTheoNguoiGhiWidget;
use App\Filament\Widgets\LaiGopTheoMonWidget;
use App\Filament\Widgets\TonThapWidget;
use App\Filament\Widgets\Top10MonBanChayWidget;
use Filament\Pages\Page;

/**
 * Phase 2 Bước 8 — màn hình chủ quán. CHỈ owner truy cập được (canAccess()),
 * xem được trên điện thoại (Filament responsive sẵn, không cần thêm gì).
 *
 * Trang này không tự truy vấn gì — chỉ ghép các widget, mỗi widget tự đọc
 * qua GetOwnerDashboard/GetOwnerProfitDashboard (CHỈ đọc các bảng tổng hợp,
 * không bao giờ đọc thẳng orders/order_items/stock_movements).
 *
 * Bốn widget cuối (Phase 3 Bước 8) bổ sung lãi gộp/hao hụt/tồn thấp — cùng
 * trang, không tạo màn hình riêng.
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
            // ĐẦU TIÊN có chủ ý: cảnh báo "số dưới đây không đáng tin" phải
            // đọc được TRƯỚC khi người ta tin vào bảng lãi gộp bên dưới.
            CanhBaoThieuGiaVonWidget::class,
            DoanhThuTongQuanWidget::class,
            DoanhThu7NgayWidget::class,
            Top10MonBanChayWidget::class,
            LaiGopTheoMonWidget::class,
            BanChayLaiThapWidget::class,
            HaoHutThangNayWidget::class,
            HaoHutTheoNguoiGhiWidget::class,
            TonThapWidget::class,
            CanhBaoDoiSoatKhoWidget::class,
        ];
    }
}
