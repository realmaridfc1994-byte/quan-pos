<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Jobs;

use App\Domain\Reporting\Actions\SummarizeProductProfit;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Vỏ hàng đợi mỏng cho SummarizeProductProfit — Phase 3 Bước 8, cùng khuôn
 * với SummarizeDailyReportJob ở Phase 2. Đẩy vào hàng đợi lúc CloseShift
 * chạy XONG — lỗi tổng hợp lãi gộp không bao giờ được chặn việc đóng ca.
 */
final class SummarizeProductProfitJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $date,
    ) {}

    public function handle(SummarizeProductProfit $action): void
    {
        $action->handle($this->date);
    }
}
