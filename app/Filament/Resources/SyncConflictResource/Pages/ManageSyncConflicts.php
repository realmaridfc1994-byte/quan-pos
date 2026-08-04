<?php

declare(strict_types=1);

namespace App\Filament\Resources\SyncConflictResource\Pages;

use App\Filament\Resources\SyncConflictResource;
use Filament\Resources\Pages\ManageRecords;

final class ManageSyncConflicts extends ManageRecords
{
    protected static string $resource = SyncConflictResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
