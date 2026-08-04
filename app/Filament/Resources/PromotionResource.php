<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domain\Billing\Actions\TogglePromotionActive;
use App\Domain\Billing\Enums\PromotionAppliesTo;
use App\Domain\Billing\Enums\PromotionType;
use App\Domain\Billing\Models\Promotion;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Filament\Resources\PromotionResource\Pages;
use App\Support\Money;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Phase 2 Bước 6 — quản lý khuyến mãi. KHÔNG có nút Xoá: hoá đơn cũ tham
 * chiếu `table_sessions.promotion_id` tới đúng chương trình đã dùng, xoá đi
 * là phá dữ liệu giao dịch cũ. Ngưng một chương trình thì bấm "Tắt".
 */
final class PromotionResource extends Resource
{
    protected static ?string $model = Promotion::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationLabel = 'Khuyến mãi';

    protected static ?string $modelLabel = 'khuyến mãi';

    protected static ?string $pluralModelLabel = 'Khuyến mãi';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('code')
                ->label('Mã khuyến mãi')
                ->helperText('Thu ngân gõ hoặc chọn mã này lúc áp dụng. Viết hoa, không dấu, không khoảng trắng.')
                ->required()
                ->maxLength(30)
                ->unique(ignoreRecord: true),
            Forms\Components\TextInput::make('name')
                ->label('Tên chương trình')
                ->helperText('Ví dụ: "Giờ vàng bia 17h-19h"')
                ->required()
                ->maxLength(100),
            Forms\Components\Select::make('type')
                ->label('Kiểu giảm')
                ->options([
                    PromotionType::Percent->value => PromotionType::Percent->label(),
                    PromotionType::Amount->value => PromotionType::Amount->label(),
                    PromotionType::HappyHour->value => PromotionType::HappyHour->label(),
                ])
                ->required()
                ->live(),
            Forms\Components\TextInput::make('value')
                ->label('Giá trị giảm')
                ->helperText(fn (Get $get): string => $get('type') === PromotionType::Amount->value
                    ? 'Số tiền cố định, đơn vị đồng.'
                    : 'Phần trăm, từ 1 đến 100.')
                ->numeric()
                ->minValue(1)
                ->maxValue(fn (Get $get): int => $get('type') === PromotionType::Amount->value ? 999_999_999 : 100)
                ->required(),
            Forms\Components\TextInput::make('min_order_amount')
                ->label('Tạm tính tối thiểu (đồng)')
                ->helperText('Bỏ trống = không giới hạn.')
                ->numeric()
                ->minValue(0),
            Forms\Components\TextInput::make('max_discount_amount')
                ->label('Trần số tiền được giảm (đồng)')
                ->helperText('Bỏ trống = không trần. Dùng để chặn % quá lớn trên đơn to.')
                ->numeric()
                ->minValue(0),
            Forms\Components\DateTimePicker::make('starts_at')
                ->label('Bắt đầu')
                ->helperText('Bỏ trống = áp dụng ngay.'),
            Forms\Components\DateTimePicker::make('ends_at')
                ->label('Kết thúc')
                ->helperText('Bỏ trống = không có ngày hết hạn.'),
            Forms\Components\Fieldset::make('Khung giờ áp dụng')
                ->schema([
                    Forms\Components\Select::make('time_rules.days')
                        ->label('Áp dụng vào các ngày')
                        ->helperText('Bỏ trống = mọi ngày trong tuần.')
                        ->multiple()
                        ->options([
                            0 => 'Chủ nhật', 1 => 'Thứ hai', 2 => 'Thứ ba', 3 => 'Thứ tư',
                            4 => 'Thứ năm', 5 => 'Thứ sáu', 6 => 'Thứ bảy',
                        ]),
                    Forms\Components\TimePicker::make('time_rules.from')
                        ->label('Từ giờ')
                        ->seconds(false)
                        ->required(fn (Get $get): bool => $get('type') === PromotionType::HappyHour->value),
                    Forms\Components\TimePicker::make('time_rules.to')
                        ->label('Đến giờ')
                        ->seconds(false)
                        ->required(fn (Get $get): bool => $get('type') === PromotionType::HappyHour->value),
                ])
                ->columns(3),
            Forms\Components\Select::make('applies_to')
                ->label('Áp dụng cho')
                ->options([
                    PromotionAppliesTo::All->value => PromotionAppliesTo::All->label(),
                    PromotionAppliesTo::Category->value => PromotionAppliesTo::Category->label(),
                    PromotionAppliesTo::Product->value => PromotionAppliesTo::Product->label(),
                ])
                ->required()
                ->live()
                ->default(PromotionAppliesTo::All->value),
            Forms\Components\Select::make('target_id')
                ->label(fn (Get $get): string => $get('applies_to') === PromotionAppliesTo::Product->value ? 'Món' : 'Nhóm món')
                ->options(fn (Get $get): array => match ($get('applies_to')) {
                    PromotionAppliesTo::Category->value => Category::query()->orderBy('name')->pluck('name', 'id')->all(),
                    PromotionAppliesTo::Product->value => Product::query()->orderBy('name')->pluck('name', 'id')->all(),
                    default => [],
                })
                ->visible(fn (Get $get): bool => $get('applies_to') !== PromotionAppliesTo::All->value)
                ->required(fn (Get $get): bool => $get('applies_to') !== PromotionAppliesTo::All->value),
            Forms\Components\TextInput::make('max_usage')
                ->label('Tổng số lượt được dùng')
                ->helperText('Bỏ trống = không giới hạn.')
                ->numeric()
                ->minValue(1),
            Forms\Components\Toggle::make('is_active')
                ->label('Đang bật')
                ->default(true),
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
                    ->label('Tên chương trình')
                    ->searchable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('Kiểu')
                    ->badge()
                    ->formatStateUsing(fn (PromotionType $state): string => $state->label()),
                Tables\Columns\TextColumn::make('value')
                    ->label('Giá trị')
                    ->formatStateUsing(fn (Promotion $record): string => $record->type === PromotionType::Amount
                        ? Money::fromInt($record->value)->format()
                        : "{$record->value}%"),
                Tables\Columns\TextColumn::make('applies_to')
                    ->label('Áp dụng cho')
                    ->formatStateUsing(fn (PromotionAppliesTo $state): string => $state->label()),
                Tables\Columns\TextColumn::make('used_count')
                    ->label('Đã dùng')
                    ->formatStateUsing(fn (Promotion $record): string => $record->max_usage !== null
                        ? "{$record->used_count}/{$record->max_usage}"
                        : (string) $record->used_count),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Đang bật')
                    ->boolean(),
            ])
            ->defaultSort('code')
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Sửa')
                    ->mutateFormDataUsing(fn (array $data): array => self::chuanHoaTimeRules($data)),
                Tables\Actions\Action::make('toggleActive')
                    ->label(fn (Promotion $record): string => $record->is_active ? 'Tắt' : 'Bật')
                    ->icon(fn (Promotion $record): string => $record->is_active ? 'heroicon-o-eye-slash' : 'heroicon-o-eye')
                    ->color(fn (Promotion $record): string => $record->is_active ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->action(fn (Promotion $record) => app(TogglePromotionActive::class)->handle($record)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManagePromotions::route('/'),
        ];
    }

    /**
     * Ba ô con của "Khung giờ áp dụng" đều trống thì ghi `time_rules = NULL`
     * (nghĩa là "mọi giờ mọi ngày"), thay vì một mảng {"days":null,...} vô nghĩa.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function chuanHoaTimeRules(array $data): array
    {
        $luat = $data['time_rules'] ?? null;

        if (is_array($luat) && blank($luat['days'] ?? null) && blank($luat['from'] ?? null) && blank($luat['to'] ?? null)) {
            $data['time_rules'] = null;
        }

        return $data;
    }
}
