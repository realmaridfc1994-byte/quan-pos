<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Reporting\Actions\SummarizeDailyReport;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Chạy tay lại việc tổng hợp báo cáo cho một ngày bất kỳ — Phase 2 Bước 8.
 * Chạy NGAY (không đẩy vào hàng đợi) để chủ dự án thấy kết quả tức thì khi
 * gỡ sự cố hoặc backfill dữ liệu cũ.
 */
final class SummarizeDailyReportCommand extends Command
{
    protected $signature = 'report:summarize {date? : Ngày cần tổng hợp lại, định dạng YYYY-MM-DD — mặc định hôm nay}';

    protected $description = 'Tổng hợp lại báo cáo ngày (daily_summaries, product_sales_daily) cho một ngày bất kỳ';

    public function handle(SummarizeDailyReport $action): int
    {
        $ngay = $this->argument('date') !== null
            ? Carbon::parse($this->argument('date'))
            : Carbon::today();

        $tomTat = $action->handle($ngay->toDateString());

        $this->line("Đã tổng hợp xong ngày {$ngay->toDateString()}:");
        $this->line("   Doanh thu: {$tomTat->revenue_amount} đ");
        $this->line("   Số lượt khách: {$tomTat->table_session_count}");
        $this->line("   Chênh lệch két: {$tomTat->cash_variance_amount} đ");

        return self::SUCCESS;
    }
}
