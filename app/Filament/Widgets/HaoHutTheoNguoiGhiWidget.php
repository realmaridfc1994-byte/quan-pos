<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Domain\Reporting\Queries\GetWasteByUser;
use App\Support\Money;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

/**
 * Hao hụt theo NGƯỜI GHI, tháng này — Phase 3 Bước 6.
 *
 * Cột "trung bình mỗi lần" là cột đáng nhìn nhất: một người ghi nhiều lần nhỏ
 * sẽ có tổng cao mà trung bình thấp — đúng kiểu không bao giờ chạm ngưỡng PIN.
 */
final class HaoHutTheoNguoiGhiWidget extends Widget
{
    protected static string $view = 'filament.widgets.hao-hut-theo-nguoi-ghi';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return Gate::allows('view-cost-profit');
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $thang = Carbon::today();
        $duLieu = app(GetWasteByUser::class)->handle($thang);

        $tongTatCa = array_sum(array_column($duLieu, 'tong_cost'));

        return [
            'thang' => $thang->translatedFormat('m/Y'),
            'tongTatCaText' => Money::fromInt(max(0, $tongTatCa))->format(),
            'theoNguoi' => array_map(fn (array $d): array => [
                ...$d,
                'tong_cost_text' => Money::fromInt(max(0, $d['tong_cost']))->format(),
                'trung_binh_text' => Money::fromInt(
                    $d['so_lan'] > 0 ? max(0, intdiv($d['tong_cost'], $d['so_lan'])) : 0
                )->format(),
            ], $duLieu),
        ];
    }
}
