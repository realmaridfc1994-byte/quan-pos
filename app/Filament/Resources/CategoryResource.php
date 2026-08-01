<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domain\Catalog\Actions\ToggleCategoryActive;
use App\Domain\Catalog\Enums\Station;
use App\Domain\Catalog\Models\Category;
use App\Filament\Resources\CategoryResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

final class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationLabel = 'Nhóm món';

    protected static ?string $modelLabel = 'nhóm món';

    protected static ?string $pluralModelLabel = 'Nhóm món';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('Tên nhóm món')
                ->required()
                ->maxLength(100)
                ->unique(ignoreRecord: true),
            Forms\Components\Select::make('station')
                ->label('In tem ở')
                ->options([
                    Station::Kitchen->value => 'Bếp',
                    Station::Bar->value => 'Quầy pha chế',
                ])
                ->required(),
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
                Tables\Columns\TextColumn::make('name')
                    ->label('Tên nhóm món')
                    ->searchable(),
                Tables\Columns\TextColumn::make('station')
                    ->label('In tem ở')
                    ->formatStateUsing(fn (Station $state) => $state === Station::Kitchen ? 'Bếp' : 'Quầy pha chế')
                    ->badge(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Thứ tự'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Đang bán')
                    ->boolean(),
            ])
            ->defaultSort('sort_order')
            ->actions([
                Tables\Actions\EditAction::make()->label('Sửa'),
                Tables\Actions\Action::make('toggleActive')
                    ->label(fn (Category $record) => $record->is_active ? 'Ngừng bán' : 'Bán lại')
                    ->icon(fn (Category $record) => $record->is_active ? 'heroicon-o-eye-slash' : 'heroicon-o-eye')
                    ->color(fn (Category $record) => $record->is_active ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->action(fn (Category $record) => app(ToggleCategoryActive::class)->handle($record)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageCategories::route('/'),
        ];
    }
}
