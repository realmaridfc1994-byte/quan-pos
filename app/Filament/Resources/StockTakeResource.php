<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domain\Inventory\Enums\StockTakeStatus;
use App\Domain\Inventory\Models\StockTake;
use App\Filament\Resources\StockTakeResource\Pages;
use App\Filament\Resources\StockTakeResource\RelationManagers;
use App\Support\Money;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Màn hình kiểm kê (Bước 7). Mở phiếu qua nút "Mở phiếu kiểm kê" (gọi
 * OpenStockTake, KHÔNG ghi Eloquent thô — giống PurchaseResource). Đếm và
 * chốt phiếu nằm ở trang Xem (ItemsRelationManager + nút "Chốt phiếu").
 */
final class StockTakeResource extends Resource
{
    protected static ?string $model = StockTake::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationGroup = 'Kho';

    protected static ?string $navigationLabel = 'Kiểm kê';

    protected static ?string $modelLabel = 'phiếu kiểm kê';

    protected static ?string $pluralModelLabel = 'Phiếu kiểm kê';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('code')
                    ->label('Mã phiếu')
                    ->disabled(),
                Forms\Components\Textarea::make('note')
                    ->label('Ghi chú')
                    ->disabled(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Mã phiếu')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->formatStateUsing(fn (StockTakeStatus $state): string => $state->label())
                    ->color(fn (StockTakeStatus $state): string => match ($state) {
                        StockTakeStatus::Open => 'warning',
                        StockTakeStatus::Closed => 'success',
                        StockTakeStatus::Cancelled => 'danger',
                    }),
                Tables\Columns\TextColumn::make('total_diff_cost')
                    ->label('Tổng chênh lệch')
                    ->placeholder('—')
                    ->formatStateUsing(fn (?int $state): string => $state === null ? '—' : Money::fromInt(abs($state))->format().($state < 0 ? ' (thiếu)' : ($state > 0 ? ' (thừa)' : ''))),
                Tables\Columns\TextColumn::make('opened_at')
                    ->label('Mở lúc')
                    ->dateTime('H:i d/m/Y'),
                Tables\Columns\TextColumn::make('closed_at')
                    ->label('Chốt lúc')
                    ->dateTime('H:i d/m/Y')
                    ->placeholder('—'),
            ])
            ->defaultSort('id', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make()->label(fn (StockTake $record) => $record->status === StockTakeStatus::Open ? 'Đếm' : 'Xem'),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageStockTakes::route('/'),
            'view' => Pages\ViewStockTake::route('/{record}'),
        ];
    }
}
