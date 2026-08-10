<?php

declare(strict_types=1);

namespace App\Filament\Resources\StockTakeResource\RelationManagers;

use App\Domain\Inventory\Actions\RecordStockTakeCount;
use App\Domain\Inventory\DTO\RecordStockTakeCountData;
use App\Domain\Inventory\Enums\StockTakeStatus;
use App\Domain\Inventory\Models\StockTakeItem;
use App\Exceptions\DomainException;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Danh sách dài để đếm — cố ý tối giản cột, ô nhập số TO (extraInputAttributes)
 * để dễ bấm trên máy tính bảng khi đứng đếm kho. Số đếm KHÔNG ghi thẳng qua
 * Eloquent: updateStateUsing() gọi RecordStockTakeCount, giữ đúng chỗ chặn
 * "sửa phiếu đã chốt" (K10) và có chỗ viết test cho luật đó.
 */
final class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Đếm từng nguyên liệu';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('ingredient.name')
                    ->label('Nguyên liệu')
                    ->searchable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('ingredient.base_unit')
                    ->label('Đơn vị')
                    ->formatStateUsing(fn ($state) => $state?->label()),
                Tables\Columns\TextColumn::make('system_qty')
                    ->label('Tồn hệ thống'),
                Tables\Columns\TextInputColumn::make('counted_qty')
                    ->label('Đếm được')
                    ->type('number')
                    ->rules(['nullable', 'integer', 'min:0'])
                    ->extraInputAttributes(['style' => 'font-size:1.5rem; font-weight:bold; text-align:center; height:3rem;'])
                    ->disabled(fn (StockTakeItem $record): bool => $record->stockTake->status !== StockTakeStatus::Open)
                    ->updateStateUsing(function (StockTakeItem $record, $state): void {
                        try {
                            app(RecordStockTakeCount::class)->handle(new RecordStockTakeCountData(
                                stockTakeItemId: $record->id,
                                countedQty: (int) $state,
                            ));
                        } catch (DomainException $e) {
                            Notification::make()->danger()->title($e->getMessage())->send();
                        }
                    }),
                Tables\Columns\TextColumn::make('diff_qty')
                    ->label('Chênh lệch')
                    ->placeholder('chưa đếm')
                    ->color(fn (?int $state): ?string => match (true) {
                        $state === null => null,
                        $state === 0 => 'success',
                        $state < 0 => 'danger',
                        default => 'warning',
                    }),
            ])
            ->defaultSort('ingredient_id')
            ->paginated([25, 50, 100, 'all']);
    }
}
