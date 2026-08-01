<?php

declare(strict_types=1);

namespace App\Domain\Billing\Queries;

use App\Domain\Ordering\Models\TableSession;

final class GetSessionBill
{
    public function handle(int $tableSessionId): TableSession
    {
        return TableSession::query()
            ->with([
                'openedBy',
                'tables.diningTable',
                'payments' => fn ($q) => $q->orderBy('paid_at'),
                'payments.receivedBy',
                'payments.voidedBy',
            ])
            ->findOrFail($tableSessionId);
    }
}
