<?php

declare(strict_types=1);

namespace App\Filament\Resources\PromotionResource\Pages;

use App\Filament\Resources\PromotionResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

final class ManagePromotions extends ManageRecords
{
    protected static string $resource = PromotionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->mutateFormDataUsing(fn (array $data): array => PromotionResource::chuanHoaTimeRules($data)),
        ];
    }
}
