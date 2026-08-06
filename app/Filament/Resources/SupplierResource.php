<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domain\Inventory\Actions\ToggleSupplierActive;
use App\Domain\Inventory\Models\Supplier;
use App\Filament\Resources\SupplierResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

final class SupplierResource extends Resource
{
    protected static ?string $model = Supplier::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationGroup = 'Kho';

    protected static ?string $navigationLabel = 'Nhà cung cấp';

    protected static ?string $modelLabel = 'nhà cung cấp';

    protected static ?string $pluralModelLabel = 'Nhà cung cấp';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('Tên nhà cung cấp')
                ->required()
                ->maxLength(150)
                ->unique(ignoreRecord: true),
            Forms\Components\TextInput::make('phone')
                ->label('Số điện thoại')
                ->tel()
                ->maxLength(20),
            Forms\Components\TextInput::make('address')
                ->label('Địa chỉ')
                ->maxLength(255),
            Forms\Components\TextInput::make('note')
                ->label('Ghi chú')
                ->maxLength(255),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Tên nhà cung cấp')
                    ->searchable(),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Điện thoại')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('address')
                    ->label('Địa chỉ')
                    ->placeholder('—')
                    ->limit(40),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Đang dùng')
                    ->boolean(),
            ])
            ->defaultSort('name')
            ->actions([
                Tables\Actions\EditAction::make()->label('Sửa'),
                Tables\Actions\Action::make('toggleActive')
                    ->label(fn (Supplier $record) => $record->is_active ? 'Ngừng dùng' : 'Dùng lại')
                    ->icon(fn (Supplier $record) => $record->is_active ? 'heroicon-o-eye-slash' : 'heroicon-o-eye')
                    ->color(fn (Supplier $record) => $record->is_active ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->action(fn (Supplier $record) => app(ToggleSupplierActive::class)->handle($record)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageSuppliers::route('/'),
        ];
    }
}
