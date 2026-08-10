<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domain\Inventory\Actions\CancelPurchase;
use App\Domain\Inventory\Actions\ReceivePurchase;
use App\Domain\Inventory\Actions\UpdatePurchase;
use App\Domain\Inventory\DTO\CancelPurchaseData;
use App\Domain\Inventory\DTO\PurchaseLineData;
use App\Domain\Inventory\DTO\ReceivePurchaseData;
use App\Domain\Inventory\DTO\UpdatePurchaseData;
use App\Domain\Inventory\Enums\PurchaseStatus;
use App\Domain\Inventory\Models\Ingredient;
use App\Domain\Inventory\Models\IngredientUnit;
use App\Domain\Inventory\Models\Purchase;
use App\Domain\Inventory\Models\PurchaseItem;
use App\Domain\Inventory\Models\Supplier;
use App\Exceptions\DomainException;
use App\Filament\Resources\PurchaseResource\Pages;
use App\Support\Money;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Màn hình nhập hàng cho chủ quán. KHÔNG dùng Repeater ->relationship() như
 * ProductVariantResource/IngredientResource đang làm cho các quan hệ đơn
 * giản — dòng phiếu nhập có nghiệp vụ (sinh mã, chụp factor_snapshot, kiểm
 * đơn vị) bắt buộc đi qua CreatePurchase/UpdatePurchase, không để Filament tự
 * ghi thẳng qua Eloquent. Repeater 'lines' chỉ là khoá ảo trong form state;
 * nút Tạo/Sửa override ->using() để gọi đúng Action.
 */
final class PurchaseResource extends Resource
{
    protected static ?string $model = Purchase::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Kho';

    protected static ?string $navigationLabel = 'Nhập hàng';

    protected static ?string $modelLabel = 'phiếu nhập';

    protected static ?string $pluralModelLabel = 'Phiếu nhập hàng';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('supplier_id')
                    ->label('Nhà cung cấp')
                    ->relationship('supplier', 'name')
                    ->searchable()
                    ->required(),
                Forms\Components\TextInput::make('invoice_no')
                    ->label('Số hoá đơn')
                    ->maxLength(50),
                Forms\Components\TextInput::make('note')
                    ->label('Ghi chú')
                    ->maxLength(255),

                Forms\Components\Repeater::make('lines')
                    ->label('Dòng nguyên liệu')
                    ->addActionLabel('Thêm nguyên liệu')
                    ->minItems(1)
                    ->required()
                    ->live()
                    ->schema([
                        Forms\Components\Select::make('ingredient_id')
                            ->label('Nguyên liệu')
                            ->options(fn () => Ingredient::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(function (Forms\Set $set, $state): void {
                                $donViMacDinh = IngredientUnit::query()
                                    ->where('ingredient_id', $state)
                                    ->where('is_purchase_default', true)
                                    ->value('unit_name');

                                $set('unit_name', $donViMacDinh);
                            })
                            ->required(),
                        Forms\Components\Select::make('unit_name')
                            ->label('Đơn vị nhập')
                            ->options(fn (Forms\Get $get) => IngredientUnit::query()
                                ->where('ingredient_id', $get('ingredient_id'))
                                ->pluck('unit_name', 'unit_name'))
                            ->live()
                            ->required(),
                        Forms\Components\TextInput::make('qty_input')
                            ->label('Số lượng')
                            ->numeric()
                            ->minValue(1)
                            ->live(onBlur: true)
                            ->required(),
                        Forms\Components\TextInput::make('unit_cost')
                            ->label('Đơn giá (đồng/đơn vị nhập)')
                            ->numeric()
                            ->minValue(0)
                            ->live(onBlur: true)
                            ->required(),
                        Forms\Components\Placeholder::make('quy_doi')
                            ->label('Quy ra đơn vị gốc')
                            ->content(function (Forms\Get $get): string {
                                $factor = self::layFactor($get('ingredient_id'), $get('unit_name'));

                                if ($factor === null) {
                                    return '—';
                                }

                                return number_format(max(0, (int) $get('qty_input')) * $factor);
                            }),
                        Forms\Components\Placeholder::make('thanh_tien')
                            ->label('Thành tiền')
                            ->content(fn (Forms\Get $get): string => Money::fromInt(
                                max(0, (int) $get('qty_input')) * max(0, (int) $get('unit_cost'))
                            )->format()),
                    ])
                    ->columns(3),

                Forms\Components\Placeholder::make('tong_tien')
                    ->label('Tổng tiền phiếu')
                    ->content(function (Forms\Get $get): string {
                        $dongPhieu = $get('lines') ?? [];
                        $tong = 0;

                        foreach ($dongPhieu as $dong) {
                            $tong += max(0, (int) ($dong['qty_input'] ?? 0)) * max(0, (int) ($dong['unit_cost'] ?? 0));
                        }

                        return Money::fromInt($tong)->format();
                    }),
            ])
            ->disabled(fn (?Purchase $record): bool => $record !== null && $record->status !== PurchaseStatus::Draft);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Mã phiếu')
                    ->searchable(),
                Tables\Columns\TextColumn::make('supplier.name')
                    ->label('Nhà cung cấp')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->formatStateUsing(fn (PurchaseStatus $state): string => $state->label())
                    ->color(fn (PurchaseStatus $state): string => match ($state) {
                        PurchaseStatus::Draft => 'gray',
                        PurchaseStatus::Received => 'success',
                        PurchaseStatus::Cancelled => 'danger',
                    }),
                Tables\Columns\TextColumn::make('total_cost')
                    ->label('Tổng tiền')
                    ->formatStateUsing(fn (int $state): string => Money::fromInt($state)->format()),
                Tables\Columns\TextColumn::make('received_at')
                    ->label('Nhận lúc')
                    ->dateTime('H:i d/m/Y')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Tạo lúc')
                    ->dateTime('H:i d/m/Y'),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Trạng thái')
                    ->options(array_combine(
                        array_map(fn (PurchaseStatus $s) => $s->value, PurchaseStatus::cases()),
                        array_map(fn (PurchaseStatus $s) => $s->label(), PurchaseStatus::cases()),
                    )),
                Tables\Filters\SelectFilter::make('supplier_id')
                    ->label('Nhà cung cấp')
                    ->options(fn () => Supplier::query()->orderBy('name')->pluck('name', 'id')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('Xem'),
                Tables\Actions\EditAction::make()
                    ->label('Sửa')
                    ->visible(fn (Purchase $record): bool => $record->status === PurchaseStatus::Draft)
                    ->mutateRecordDataUsing(fn (array $data, Purchase $record): array => [
                        ...$data,
                        'lines' => $record->items->map(fn (PurchaseItem $dong): array => [
                            'ingredient_id' => $dong->ingredient_id,
                            'unit_name' => $dong->unit_name,
                            'qty_input' => $dong->qty_input,
                            'unit_cost' => $dong->unit_cost,
                        ])->all(),
                    ])
                    ->using(function (Purchase $record, array $data): void {
                        app(UpdatePurchase::class)->handle(new UpdatePurchaseData(
                            purchaseId: $record->id,
                            supplierId: (int) $data['supplier_id'],
                            note: $data['note'] ?? null,
                            invoiceNo: $data['invoice_no'] ?? null,
                            lines: self::linesFromFormData($data['lines'] ?? []),
                        ));
                    }),
                Tables\Actions\Action::make('receive')
                    ->label('Nhận hàng vào kho')
                    ->icon('heroicon-o-inbox-arrow-down')
                    ->color('success')
                    ->visible(fn (Purchase $record): bool => $record->status === PurchaseStatus::Draft)
                    ->requiresConfirmation()
                    ->modalDescription('Nhận hàng rồi KHÔNG sửa được và KHÔNG huỷ được phiếu này nữa. Số lượng sẽ cộng thẳng vào tồn kho theo giá vốn bình quân.')
                    ->action(function (Purchase $record): void {
                        try {
                            app(ReceivePurchase::class)->handle(new ReceivePurchaseData(
                                purchaseId: $record->id,
                                receivedByUserId: (int) auth()->id(),
                            ));

                            Notification::make()->success()->title('Đã nhận hàng vào kho.')->send();
                        } catch (DomainException $e) {
                            Notification::make()->danger()->title($e->getMessage())->send();
                        }
                    }),
                Tables\Actions\Action::make('cancel')
                    ->label('Huỷ phiếu')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Purchase $record): bool => $record->status === PurchaseStatus::Draft)
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Lý do huỷ')
                            ->required(),
                    ])
                    ->action(function (Purchase $record, array $data): void {
                        try {
                            app(CancelPurchase::class)->handle(new CancelPurchaseData(
                                purchaseId: $record->id,
                                reason: $data['reason'],
                            ));

                            Notification::make()->success()->title('Đã huỷ phiếu nhập.')->send();
                        } catch (DomainException $e) {
                            Notification::make()->danger()->title($e->getMessage())->send();
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManagePurchases::route('/'),
        ];
    }

    /**
     * @param  array<int, array{ingredient_id: mixed, unit_name: mixed, qty_input: mixed, unit_cost: mixed}>  $lines
     * @return list<PurchaseLineData>
     */
    public static function linesFromFormData(array $lines): array
    {
        return array_values(array_map(
            fn (array $line): PurchaseLineData => new PurchaseLineData(
                ingredientId: (int) $line['ingredient_id'],
                unitName: (string) $line['unit_name'],
                qtyInput: (int) $line['qty_input'],
                unitCost: (int) $line['unit_cost'],
            ),
            $lines,
        ));
    }

    private static function layFactor(mixed $ingredientId, mixed $unitName): ?int
    {
        if ($ingredientId === null || $unitName === null || $unitName === '') {
            return null;
        }

        return IngredientUnit::query()
            ->where('ingredient_id', $ingredientId)
            ->where('unit_name', $unitName)
            ->value('factor');
    }
}
