<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domain\Catalog\Actions\ToggleOptionActive;
use App\Domain\Catalog\Models\Option;
use App\Filament\Resources\OptionResource\Pages;
use App\Support\Money;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

final class OptionResource extends Resource
{
    protected static ?string $model = Option::class;

    protected static ?string $navigationIcon = 'heroicon-o-list-bullet';

    protected static ?string $navigationLabel = 'Tùy chọn';

    protected static ?string $modelLabel = 'tùy chọn';

    protected static ?string $pluralModelLabel = 'Tùy chọn';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('option_group_id')
                ->label('Nhóm tùy chọn')
                ->relationship('optionGroup', 'name')
                ->searchable()
                ->required(),
            Forms\Components\TextInput::make('name')
                ->label('Tên tùy chọn')
                ->helperText('Thêm ớt, Ít đá, Không rau, Thêm mì...')
                ->required()
                ->maxLength(100),
            Forms\Components\TextInput::make('price_delta')
                ->label('Tiền cộng thêm (đồng)')
                ->helperText('Phần lớn là 0. Nhập số nguyên, đơn vị đồng.')
                ->numeric()
                ->minValue(0)
                ->default(0)
                ->required(),
            Forms\Components\Toggle::make('is_default')
                ->label('Chọn sẵn khi khách chưa chọn gì'),
            Forms\Components\TextInput::make('sort_order')
                ->label('Thứ tự hiển thị')
                ->numeric()
                ->minValue(0)
                ->default(0)
                ->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('optionGroup.name')
                    ->label('Nhóm tùy chọn')
                    ->searchable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Tên tùy chọn'),
                Tables\Columns\TextColumn::make('price_delta')
                    ->label('Cộng thêm')
                    ->formatStateUsing(fn (int $state) => Money::fromInt($state)->format()),
                Tables\Columns\IconColumn::make('is_default')
                    ->label('Mặc định')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Đang dùng')
                    ->boolean(),
            ])
            ->defaultSort('sort_order')
            ->actions([
                Tables\Actions\EditAction::make()->label('Sửa'),
                Tables\Actions\Action::make('toggleActive')
                    ->label(fn (Option $record) => $record->is_active ? 'Ngừng dùng' : 'Dùng lại')
                    ->icon(fn (Option $record) => $record->is_active ? 'heroicon-o-eye-slash' : 'heroicon-o-eye')
                    ->color(fn (Option $record) => $record->is_active ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->action(fn (Option $record) => app(ToggleOptionActive::class)->handle($record)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageOptions::route('/'),
        ];
    }
}
