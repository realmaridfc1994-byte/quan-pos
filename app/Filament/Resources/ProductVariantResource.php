<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domain\Catalog\Actions\SetDefaultProductVariant;
use App\Domain\Catalog\Actions\ToggleProductVariantActive;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Inventory\Models\Ingredient;
use App\Domain\Inventory\Queries\EstimateVariantCost;
use App\Exceptions\DomainException;
use App\Filament\Resources\ProductVariantResource\Pages;
use App\Support\Money;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

final class ProductVariantResource extends Resource
{
    protected static ?string $model = ProductVariant::class;

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationLabel = 'Biến thể món';

    protected static ?string $modelLabel = 'biến thể';

    protected static ?string $pluralModelLabel = 'Biến thể món';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('product_id')
                ->label('Món')
                ->relationship('product', 'name')
                ->searchable()
                ->required(),
            Forms\Components\TextInput::make('name')
                ->label('Tên biến thể')
                ->helperText('Lon, Chai, Thùng, Phần nhỏ... Món không có biến thể thì ghi "Mặc định".')
                ->required()
                ->maxLength(100),
            Forms\Components\TextInput::make('price')
                ->label('Giá bán (đồng)')
                ->helperText('Nhập số nguyên, đơn vị đồng — không có số lẻ.')
                ->numeric()
                ->minValue(0)
                ->required(),
            Forms\Components\TextInput::make('sort_order')
                ->label('Thứ tự hiển thị')
                ->numeric()
                ->minValue(0)
                ->default(0)
                ->required(),

            Forms\Components\Toggle::make('deducts_stock')
                ->label('Trừ kho theo định lượng')
                ->helperText('Bật lên nếu bán biến thể này thì phải trừ nguyên liệu trong kho — cả món nấu và bia/nước. Không bật với phí phục vụ, khăn lạnh.')
                ->live(),

            Forms\Components\Placeholder::make('estimated_cost')
                ->label('Giá vốn ước tính')
                ->visible(fn (?ProductVariant $record) => $record !== null)
                ->content(fn (?ProductVariant $record) => $record === null
                    ? '—'
                    : app(EstimateVariantCost::class)->handle($record)->format().' (tính theo giá vốn nguyên liệu hiện tại, chưa gồm Bước 4)'),

            Forms\Components\Repeater::make('recipes')
                ->relationship()
                ->label('Định lượng nguyên liệu')
                ->helperText('Một dòng cho mỗi nguyên liệu, số lượng theo đơn vị GỐC của nguyên liệu đó (gam, ml, lon...).')
                ->addActionLabel('Thêm nguyên liệu')
                ->defaultItems(0)
                ->visible(fn (Forms\Get $get) => (bool) $get('deducts_stock'))
                ->dehydrated(fn (Forms\Get $get) => (bool) $get('deducts_stock'))
                ->schema([
                    Forms\Components\Select::make('ingredient_id')
                        ->label('Nguyên liệu')
                        ->options(fn () => Ingredient::query()->orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->required(),
                    Forms\Components\TextInput::make('qty_base')
                        ->label('Số lượng (đơn vị gốc)')
                        ->numeric()
                        ->minValue(1)
                        ->required(),
                    Forms\Components\TextInput::make('note')
                        ->label('Ghi chú')
                        ->maxLength(255),
                ])
                ->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Món')
                    ->searchable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Biến thể'),
                Tables\Columns\TextColumn::make('price')
                    ->label('Giá bán')
                    ->formatStateUsing(fn (int $state) => Money::fromInt($state)->format()),
                Tables\Columns\IconColumn::make('is_default')
                    ->label('Mặc định')
                    ->boolean(),
                Tables\Columns\IconColumn::make('deducts_stock')
                    ->label('Trừ kho')
                    ->boolean(),
                Tables\Columns\TextColumn::make('estimated_cost')
                    ->label('Giá vốn ước tính')
                    ->state(fn (ProductVariant $record) => $record->deducts_stock
                        ? app(EstimateVariantCost::class)->handle($record)->format()
                        : '—'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Đang bán')
                    ->boolean(),
            ])
            ->defaultSort('sort_order')
            ->actions([
                Tables\Actions\EditAction::make()->label('Sửa'),
                Tables\Actions\Action::make('setDefault')
                    ->label('Đặt làm mặc định')
                    ->icon('heroicon-o-star')
                    ->visible(fn (ProductVariant $record) => ! $record->is_default)
                    ->requiresConfirmation()
                    ->action(fn (ProductVariant $record) => app(SetDefaultProductVariant::class)->handle($record)),
                Tables\Actions\Action::make('toggleActive')
                    ->label(fn (ProductVariant $record) => $record->is_active ? 'Ngừng bán' : 'Bán lại')
                    ->icon(fn (ProductVariant $record) => $record->is_active ? 'heroicon-o-eye-slash' : 'heroicon-o-eye')
                    ->color(fn (ProductVariant $record) => $record->is_active ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->action(function (ProductVariant $record): void {
                        try {
                            app(ToggleProductVariantActive::class)->handle($record);
                        } catch (DomainException $e) {
                            Notification::make()
                                ->danger()
                                ->title($e->getMessage())
                                ->send();
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageProductVariants::route('/'),
        ];
    }
}
