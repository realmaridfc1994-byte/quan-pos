<?php

declare(strict_types=1);

namespace App\Filament\Resources\OptionGroupResource\Pages;

use App\Filament\Resources\OptionGroupResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

final class ManageOptionGroups extends ManageRecords
{
    protected static string $resource = OptionGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
