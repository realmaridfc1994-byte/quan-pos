<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseResource\Pages;

use App\Domain\Inventory\Actions\CreatePurchase;
use App\Domain\Inventory\DTO\CreatePurchaseData;
use App\Domain\Inventory\Models\Purchase;
use App\Filament\Resources\PurchaseResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

final class ManagePurchases extends ManageRecords
{
    protected static string $resource = PurchaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->using(function (array $data): Purchase {
                    return app(CreatePurchase::class)->handle(new CreatePurchaseData(
                        supplierId: (int) $data['supplier_id'],
                        note: $data['note'] ?? null,
                        invoiceNo: $data['invoice_no'] ?? null,
                        lines: PurchaseResource::linesFromFormData($data['lines'] ?? []),
                        createdByUserId: (int) auth()->id(),
                    ));
                }),
        ];
    }
}
