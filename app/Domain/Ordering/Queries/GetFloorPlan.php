<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Queries;

use App\Domain\Ordering\Models\DiningTable;
use Illuminate\Support\Collection;

final class GetFloorPlan
{
    /** @return Collection<int, DiningTable> */
    public function handle(): Collection
    {
        return DiningTable::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->with(['tableSessionTables' => fn ($q) => $q
                ->whereNull('detached_at')
                ->with('tableSession'),
            ])
            ->get();
    }
}
