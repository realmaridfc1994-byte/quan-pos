<?php

declare(strict_types=1);

namespace App\Domain\Staffing\Queries;

use App\Domain\Staffing\Enums\ShiftStatus;
use App\Domain\Staffing\Models\Shift;

final class GetCurrentShift
{
    public function handle(): ?Shift
    {
        return Shift::query()->where('status', ShiftStatus::Open)->first();
    }
}
