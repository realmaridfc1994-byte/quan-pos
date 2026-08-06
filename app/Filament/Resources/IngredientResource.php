<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domain\Inventory\Actions\ToggleIngredientActive;
use App\Domain\Inventory\Enums\IngredientBaseUnit;
use App\Domain\Inventory\Models\Ingredient;
use App\Filament\Resources\IngredientResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

final class IngredientResource extends Resource
{
    protected static ?string $model = Ingredient::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationGroup = 'Kho';

    protected static ?string $navigationLabel = 'Nguyên liệu';

    protected static ?string $modelLabel = 'nguyên liệu';

    protected static ?string $pluralModelLabel = 'Nguyên liệu';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('code')
                ->label('Mã gõ nhanh')
                ->helperText('TIGER-LON, GA-TA...')
                ->required()
                ->maxLength(30)
                ->unique(ignoreRecord: true),
            Forms\Components\TextInput::make('name')
                ->label('Tên nguyên liệu')
                ->required()
                ->maxLength(150)
                ->unique(ignoreRecord: true),
            Forms\Components\Select::make('base_unit')
                ->label('Đơn vị gốc')
                ->helperText('Mọi số lượng của nguyên liệu này tính theo đơn vị này. Phải đủ nhỏ để luôn là số nguyên.')
                ->options(array_combine(
                    array_map(fn (IngredientBaseUnit $u) => $u->value, IngredientBaseUnit::cases()),
                    array_map(fn (IngredientBaseUnit $u) => $u->label(), IngredientBaseUnit::cases()),
                ))
                ->required(),
            Forms\Components\TextInput::make('category')
                ->label('Nhóm nguyên liệu')
                ->helperText('Bia rượu, Thịt, Rau, Gia vị...')
                ->maxLength(50),
            Forms\Components\TextInput::make('min_qty')
                ->label('Cảnh báo dưới mức (theo đơn vị gốc)')
                ->helperText('0 = không cảnh báo tồn thấp')
                ->numeric()
                ->minValue(0)
                ->default(0)
                ->required(),

            Forms\Components\Repeater::make('units')
                ->relationship()
                ->label('Đơn vị quy đổi')
                ->helperText('1 đơn vị này = bao nhiêu đơn vị gốc. VD Thùng = 24 (nếu gốc là lon).')
                ->addActionLabel('Thêm đơn vị quy đổi')
                ->defaultItems(0)
                ->schema([
                    Forms\Components\TextInput::make('unit_name')
                        ->label('Tên đơn vị')
                        ->helperText('Thùng, Kg, Lít, Két...')
                        ->required()
                        ->maxLength(30),
                    Forms\Components\TextInput::make('factor')
                        ->label('Bằng bao nhiêu đơn vị gốc')
                        ->numeric()
                        ->minValue(1)
                        ->required(),
                    Forms\Components\Toggle::make('is_purchase_default')
                        ->label('Chọn sẵn khi nhập hàng'),
                ])
                ->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Mã')
                    ->searchable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Tên nguyên liệu')
                    ->searchable(),
                Tables\Columns\TextColumn::make('base_unit')
                    ->label('Đơn vị gốc')
                    ->formatStateUsing(fn (IngredientBaseUnit $state) => $state->label())
                    ->badge(),
                Tables\Columns\TextColumn::make('category')
                    ->label('Nhóm')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('min_qty')
                    ->label('Mức cảnh báo'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Đang dùng')
                    ->boolean(),
            ])
            ->defaultSort('name')
            ->actions([
                Tables\Actions\EditAction::make()->label('Sửa'),
                Tables\Actions\Action::make('toggleActive')
                    ->label(fn (Ingredient $record) => $record->is_active ? 'Ngừng dùng' : 'Dùng lại')
                    ->icon(fn (Ingredient $record) => $record->is_active ? 'heroicon-o-eye-slash' : 'heroicon-o-eye')
                    ->color(fn (Ingredient $record) => $record->is_active ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->action(fn (Ingredient $record) => app(ToggleIngredientActive::class)->handle($record)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageIngredients::route('/'),
        ];
    }
}
