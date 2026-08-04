<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Jobs;

use App\Domain\Reporting\Actions\SummarizeDailyReport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Vỏ hàng đợi mỏng cho SummarizeDailyReport — Phase 2 Bước 8.
 *
 * Đẩy vào hàng đợi lúc CloseShift chạy XONG (không nằm trong transaction đóng
 * ca): lỗi tổng hợp báo cáo không bao giờ được chặn việc đóng ca. Toàn bộ
 * logic nghiệp vụ nằm ở Action — Job chỉ gọi lại, không tính gì ở đây.
 */
final class SummarizeDailyReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $date,
    ) {}

    public function handle(SummarizeDailyReport $action): void
    {
        $action->handle($this->date);
    }
}
