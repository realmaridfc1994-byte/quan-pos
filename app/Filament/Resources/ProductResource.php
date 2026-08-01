<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domain\Catalog\Actions\ToggleProductActive;
use App\Domain\Catalog\Enums\Station;
use App\Domain\Catalog\Models\Product;
use App\Filament\Resources\ProductResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

final class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-cake';

    protected static ?string $navigationLabel = 'Món';

    protected static ?string $modelLabel = 'món';

    protected static ?string $pluralModelLabel = 'Món';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('category_id')
                ->label('Nhóm món')
                ->relationship('category', 'name')
                ->searchable()
                ->required(),
            Forms\Components\TextInput::make('code')
                ->label('Mã món (gõ nhanh)')
                ->required()
                ->maxLength(30)
                ->unique(ignoreRecord: true),
            Forms\Components\TextInput::make('name')
                ->label('Tên món')
                ->required()
                ->maxLength(150),
            Forms\Components\Textarea::make('description')
                ->label('Mô tả')
                ->maxLength(500),
            Forms\Components\Select::make('station_override')
                ->label('In tem ở (bỏ trống = theo nhóm món)')
                ->options([
                    Station::Kitchen->value => 'Bếp',
                    Station::Bar->value => 'Quầy pha chế',
                ])
                ->placeholder('(theo nhóm món)'),
            Forms\Components\TextInput::make('image_path')
                ->label('Đường dẫn ảnh')
                ->maxLength(255),
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
                    ->label('Tên món')
                    ->searchable(),
                Tables\Columns\TextColumn::make('code')
                    ->label('Mã')
                    ->searchable(),
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Nhóm món'),
                Tables\Columns\TextColumn::make('effective_station')
                    ->label('In tem ở')
                    ->state(fn (Product $record) => $record->effectiveStation() === Station::Kitchen ? 'Bếp' : 'Quầy pha chế')
                    ->badge(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Đang bán')
                    ->boolean(),
            ])
            ->defaultSort('sort_order')
            ->actions([
                Tables\Actions\EditAction::make()->label('Sửa'),
                Tables\Actions\Action::make('toggleActive')
                    ->label(fn (Product $record) => $record->is_active ? 'Ngừng bán' : 'Bán lại')
                    ->icon(fn (Product $record) => $record->is_active ? 'heroicon-o-eye-slash' : 'heroicon-o-eye')
                    ->color(fn (Product $record) => $record->is_active ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->action(fn (Product $record) => app(ToggleProductActive::class)->handle($record)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageProducts::route('/'),
        ];
    }
}
