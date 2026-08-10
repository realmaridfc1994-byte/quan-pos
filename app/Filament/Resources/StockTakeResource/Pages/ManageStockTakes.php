<?php

declare(strict_types=1);

namespace App\Filament\Resources\StockTakeResource\Pages;

use App\Domain\Inventory\Actions\OpenStockTake;
use App\Domain\Inventory\DTO\OpenStockTakeData;
use App\Exceptions\DomainException;
use App\Filament\Resources\StockTakeResource;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

final class ManageStockTakes extends ListRecords
{
    protected static string $resource = StockTakeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('mo-phieu')
                ->label('Mở phiếu kiểm kê')
                ->icon('heroicon-o-plus-circle')
                ->form([
                    Forms\Components\Textarea::make('note')
                        ->label('Ghi chú (không bắt buộc)'),
                ])
                ->action(function (array $data): void {
                    try {
                        $phieu = app(OpenStockTake::class)->handle(new OpenStockTakeData(
                            note: $data['note'] ?? null,
                            openedByUserId: (int) auth()->id(),
                        ));

                        Notification::make()->success()->title("Đã mở phiếu {$phieu->code}.")->send();

                        $this->redirect(StockTakeResource::getUrl('view', ['record' => $phieu]));
                    } catch (DomainException $e) {
                        Notification::make()->danger()->title($e->getMessage())->send();
                    }
                }),
        ];
    }
}
