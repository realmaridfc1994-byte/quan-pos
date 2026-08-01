<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Queries;

use App\Domain\Ordering\Models\TableSession;

final class GetTableSessionDetail
{
    public function handle(int $tableSessionId): TableSession
    {
        return TableSession::query()
            ->with([
                'openedBy',
                'tables.diningTable',
                'orders' => fn ($q) => $q->orderBy('sequence_no'),
                'orders.createdBy',
                'orders.items' => fn ($q) => $q->orderBy('id'),
                'orders.items.options',
            ])
            ->findOrFail($tableSessionId);
    }
}
