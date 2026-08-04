<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Domain\Reporting\Queries\GetOwnerDashboard;
use App\Domain\Staffing\Enums\UserRole;
use App\Support\Money;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Phase 2 Bước 8 — chỉ chủ quán xem được (canView()). Đọc thuần từ
 * GetOwnerDashboard, không tự truy vấn gì thêm ở đây.
 */
final class DoanhThuTongQuanWidget extends StatsOverviewWidget
{
    public static function canView(): bool
    {
        return auth()->user()?->role === UserRole::Owner;
    }

    protected function getStats(): array
    {
        $duLieu = app(GetOwnerDashboard::class)->handle();

        $homNay = Money::fromInt($duLieu['hom_nay']['revenue_amount']);
        $homQua = Money::fromInt($duLieu['hom_qua']['revenue_amount']);
        $tuanTruoc = Money::fromInt($duLieu['cung_thu_tuan_truoc']['revenue_amount']);

        $stats = [
            Stat::make('Doanh thu hôm nay', $homNay->format())
                ->description("{$duLieu['hom_nay']['table_session_count']} lượt khách")
                ->color('success'),
            Stat::make('Hôm qua', $homQua->format())
                ->description($this->soSanh($homNay->amount, $homQua->amount)),
            Stat::make('Cùng thứ tuần trước', $tuanTruoc->format())
                ->description($this->soSanh($homNay->amount, $tuanTruoc->amount)),
        ];

        foreach ($duLieu['canh_bao'] as $canhBao) {
            $stats[] = Stat::make('Cảnh báo', $canhBao['message'])
                ->color('danger');
        }

        return $stats;
    }

    private function soSanh(int $hienTai, int $soSanh): string
    {
        if ($soSanh === 0) {
            return $hienTai > 0 ? 'Chưa có số để so sánh' : '';
        }

        $phanTram = round((($hienTai - $soSanh) / $soSanh) * 100);

        return $phanTram >= 0 ? "Tăng {$phanTram}%" : 'Giảm '.abs($phanTram).'%';
    }
}
