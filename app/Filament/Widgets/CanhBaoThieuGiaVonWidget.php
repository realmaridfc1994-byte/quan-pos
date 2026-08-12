<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Domain\Reporting\Queries\GetOwnerProfitDashboard;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Gate;

/**
 * Dòng cảnh báo ĐẦU TRANG báo cáo (Bước 10): tháng này có bao nhiêu món mà
 * lãi gộp đang cao hơn thực tế.
 *
 * Đặt ở vị trí ĐẦU TIÊN trong BaoCaoChuQuan có chủ ý — cảnh báo "con số dưới
 * đây không đáng tin" mà nằm cuối trang thì người đọc đã tin xong rồi mới
 * thấy. Không món nào thiếu giá vốn thì widget tự ẩn hoàn toàn, không để lại
 * ô trống hay dòng "không có cảnh báo nào" gây nhiễu.
 */
final class CanhBaoThieuGiaVonWidget extends Widget
{
    protected static string $view = 'filament.widgets.canh-bao-thieu-gia-von';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = -10;

    public static function canView(): bool
    {
        if (! Gate::allows('view-cost-profit')) {
            return false;
        }

        return app(GetOwnerProfitDashboard::class)->handle()['thieu_gia_von']['so_mon'] > 0;
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $duLieu = app(GetOwnerProfitDashboard::class)->handle();

        return [
            'thang' => $duLieu['thang'],
            'tongKet' => $duLieu['thieu_gia_von'],
            'monThieu' => array_values(array_filter(
                $duLieu['lai_gop_theo_tong'],
                fn (array $d): bool => $d['thieu_gia_von'],
            )),
        ];
    }
}
