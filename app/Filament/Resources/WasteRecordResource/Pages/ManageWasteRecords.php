<?php

declare(strict_types=1);

namespace App\Filament\Resources\WasteRecordResource\Pages;

use App\Domain\Inventory\Actions\WriteOffStock;
use App\Domain\Inventory\DTO\WriteOffStockData;
use App\Domain\Inventory\Enums\WasteReasonCategory;
use App\Domain\Inventory\Models\StockMovement;
use App\Filament\Resources\WasteRecordResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

final class ManageWasteRecords extends ManageRecords
{
    protected static string $resource = WasteRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->using(function (array $data): StockMovement {
                    return app(WriteOffStock::class)->handle(new WriteOffStockData(
                        ingredientId: (int) $data['ingredient_id'],
                        category: WasteReasonCategory::from($data['category']),
                        detail: (string) $data['detail'],
                        qty: (int) $data['qty'],
                        createdByUserId: (int) auth()->id(),
                        shiftId: null,
                    ));
                }),
        ];
    }
}
