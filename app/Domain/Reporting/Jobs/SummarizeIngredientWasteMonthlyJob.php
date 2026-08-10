<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Jobs;

use App\Domain\Reporting\Actions\SummarizeIngredientWasteMonthly;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Vỏ hàng đợi mỏng cho SummarizeIngredientWasteMonthly — Phase 3 Bước 8,
 * cùng khuôn với SummarizeDailyReportJob ở Phase 2.
 */
final class SummarizeIngredientWasteMonthlyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $anyDateInMonth,
    ) {}

    public function handle(SummarizeIngredientWasteMonthly $action): void
    {
        $action->handle($this->anyDateInMonth);
    }
}
