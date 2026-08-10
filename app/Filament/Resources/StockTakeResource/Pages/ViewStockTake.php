<?php

declare(strict_types=1);

namespace App\Filament\Resources\StockTakeResource\Pages;

use App\Domain\Inventory\Actions\CloseStockTake;
use App\Domain\Inventory\DTO\CloseStockTakeData;
use App\Domain\Inventory\Enums\StockTakeStatus;
use App\Domain\Inventory\Models\StockTake;
use App\Exceptions\DomainException;
use App\Filament\Resources\StockTakeResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

final class ViewStockTake extends ViewRecord
{
    protected static string $resource = StockTakeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('chot-phieu')
                ->label('Chốt phiếu')
                ->icon('heroicon-o-lock-closed')
                ->color('success')
                ->visible(fn (StockTake $record): bool => $record->status === StockTakeStatus::Open)
                ->requiresConfirmation()
                ->modalDescription('Chốt phiếu sẽ sinh dòng sổ cái điều chỉnh cho từng nguyên liệu lệch và KHÔNG sửa lại được nữa. Dòng chưa đếm sẽ không được tính.')
                ->action(function (StockTake $record): void {
                    try {
                        app(CloseStockTake::class)->handle(new CloseStockTakeData(
                            stockTakeId: $record->id,
                            closedByUserId: (int) auth()->id(),
                        ));

                        Notification::make()->success()->title('Đã chốt phiếu kiểm kê.')->send();
                    } catch (DomainException $e) {
                        Notification::make()->danger()->title($e->getMessage())->send();
                    }
                }),
        ];
    }
}
