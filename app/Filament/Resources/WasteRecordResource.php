<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domain\Inventory\Enums\WasteReasonCategory;
use App\Domain\Inventory\Models\Ingredient;
use App\Domain\Inventory\Models\StockMovement;
use App\Filament\Resources\WasteRecordResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Màn hình ghi hao hụt (Bước 6) — chỉ hiện đúng các dòng sổ cái type=waste.
 * KHÔNG dùng Eloquent ghi thẳng: nút "Tạo" override ->using() để gọi
 * WriteOffStock (một cửa duy nhất ghi sổ cái + cập nhật tồn), giống cách
 * PurchaseResource đã làm cho phiếu nhập. Không có nút Sửa/Xoá — sổ cái
 * không bao giờ sửa hay xoá (StockMovement::delete() cũng tự chặn ở tầng
 * Model, đây là lớp phòng thủ thứ hai).
 */
final class WasteRecordResource extends Resource
{
    protected static ?string $model = StockMovement::class;

    protected static ?string $navigationIcon = 'heroicon-o-trash';

    protected static ?string $navigationGroup = 'Kho';

    protected static ?string $navigationLabel = 'Hao hụt';

    protected static ?string $modelLabel = 'dòng hao hụt';

    protected static ?string $pluralModelLabel = 'Hao hụt';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('type', 'waste');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('ingredient_id')
                    ->label('Nguyên liệu')
                    ->options(fn () => Ingredient::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->required(),
                Forms\Components\Select::make('category')
                    ->label('Loại hao hụt')
                    ->options(array_combine(
                        array_map(fn (WasteReasonCategory $c) => $c->value, WasteReasonCategory::cases()),
                        array_map(fn (WasteReasonCategory $c) => $c->label(), WasteReasonCategory::cases()),
                    ))
                    ->required(),
                Forms\Components\TextInput::make('qty')
                    ->label('Số lượng hao hụt (theo đơn vị gốc của nguyên liệu)')
                    ->numeric()
                    ->minValue(1)
                    ->required(),
                Forms\Components\Textarea::make('detail')
                    ->label('Mô tả chi tiết')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('ingredient.name')
                    ->label('Nguyên liệu')
                    ->searchable(),
                Tables\Columns\TextColumn::make('qty_delta')
                    ->label('Số lượng hao hụt')
                    ->formatStateUsing(fn (int $state): string => (string) abs($state)),
                Tables\Columns\TextColumn::make('reason')
                    ->label('Lý do')
                    ->wrap(),
                Tables\Columns\TextColumn::make('createdBy.name')
                    ->label('Người ghi'),
                Tables\Columns\TextColumn::make('occurred_at')
                    ->label('Lúc')
                    ->dateTime('H:i d/m/Y')
                    ->sortable(),
            ])
            ->defaultSort('occurred_at', 'desc')
            ->filters([
                Tables\Filters\Filter::make('thang')
                    ->label('Theo tháng')
                    ->form([
                        Forms\Components\Select::make('nam')
                            ->label('Năm')
                            ->options(fn () => array_combine(
                                range((int) now()->format('Y'), (int) now()->format('Y') - 3),
                                range((int) now()->format('Y'), (int) now()->format('Y') - 3),
                            ))
                            ->default((int) now()->format('Y')),
                        Forms\Components\Select::make('thang')
                            ->label('Tháng')
                            ->options(array_combine(range(1, 12), range(1, 12)))
                            ->default((int) now()->format('n')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (blank($data['nam'] ?? null) || blank($data['thang'] ?? null)) {
                            return $query;
                        }

                        return $query
                            ->whereYear('occurred_at', $data['nam'])
                            ->whereMonth('occurred_at', $data['thang']);
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('Xem'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageWasteRecords::route('/'),
        ];
    }
}
