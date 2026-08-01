<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domain\Catalog\Actions\ToggleOptionGroupActive;
use App\Domain\Catalog\Models\OptionGroup;
use App\Filament\Resources\OptionGroupResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

final class OptionGroupResource extends Resource
{
    protected static ?string $model = OptionGroup::class;

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?string $navigationLabel = 'Nhóm tùy chọn';

    protected static ?string $modelLabel = 'nhóm tùy chọn';

    protected static ?string $pluralModelLabel = 'Nhóm tùy chọn';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('Tên nhóm tùy chọn')
                ->helperText('Độ cay, Đá, Rau ăn kèm...')
                ->required()
                ->maxLength(100),

            // E5: đúng một trong hai — món cụ thể HOẶC cả nhóm món, không bao giờ cả hai.
            Forms\Components\Radio::make('scope')
                ->label('Áp dụng cho')
                ->options([
                    'product' => 'Một món cụ thể',
                    'category' => 'Cả một nhóm món',
                ])
                ->required()
                ->live()
                ->dehydrated(false),

            Forms\Components\Select::make('product_id')
                ->label('Món')
                ->relationship('product', 'name')
                ->searchable()
                ->visible(fn (Get $get) => $get('scope') === 'product')
                ->required(fn (Get $get) => $get('scope') === 'product')
                ->dehydratedWhenHidden()
                ->dehydrateStateUsing(fn ($state, Get $get) => $get('scope') === 'product' ? $state : null),

            Forms\Components\Select::make('category_id')
                ->label('Nhóm món')
                ->relationship('category', 'name')
                ->searchable()
                ->visible(fn (Get $get) => $get('scope') === 'category')
                ->required(fn (Get $get) => $get('scope') === 'category')
                ->dehydratedWhenHidden()
                ->dehydrateStateUsing(fn ($state, Get $get) => $get('scope') === 'category' ? $state : null),

            Forms\Components\Toggle::make('is_required')
                ->label('Bắt buộc khách phải chọn'),
            Forms\Components\TextInput::make('min_select')
                ->label('Chọn tối thiểu')
                ->numeric()
                ->minValue(0)
                ->default(0)
                ->required(),
            Forms\Components\TextInput::make('max_select')
                ->label('Chọn tối đa (1 = chỉ chọn một)')
                ->numeric()
                ->minValue(1)
                ->default(1)
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
                    ->label('Tên nhóm tùy chọn')
                    ->searchable(),
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Áp cho món')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Áp cho nhóm món')
                    ->placeholder('—'),
                Tables\Columns\IconColumn::make('is_required')
                    ->label('Bắt buộc')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Đang dùng')
                    ->boolean(),
            ])
            ->defaultSort('sort_order')
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Sửa')
                    ->mutateRecordDataUsing(function (array $data): array {
                        $data['scope'] = $data['category_id'] !== null ? 'category' : 'product';

                        return $data;
                    }),
                Tables\Actions\Action::make('toggleActive')
                    ->label(fn (OptionGroup $record) => $record->is_active ? 'Ngừng dùng' : 'Dùng lại')
                    ->icon(fn (OptionGroup $record) => $record->is_active ? 'heroicon-o-eye-slash' : 'heroicon-o-eye')
                    ->color(fn (OptionGroup $record) => $record->is_active ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->action(fn (OptionGroup $record) => app(ToggleOptionGroupActive::class)->handle($record)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageOptionGroups::route('/'),
        ];
    }
}
