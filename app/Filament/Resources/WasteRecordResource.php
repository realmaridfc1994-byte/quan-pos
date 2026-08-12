<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domain\Inventory\Enums\WasteReasonCategory;
use App\Domain\Inventory\Models\Ingredient;
use App\Domain\Inventory\Models\StockBalance;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Staffing\Enums\UserRole;
use App\Domain\Staffing\Models\User;
use App\Filament\Resources\WasteRecordResource\Pages;
use App\Support\CauHinhQuan;
use App\Support\Money;
use App\Support\StockCost;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

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
                // Mã vân tay sinh MỘT LẦN lúc mở form, gửi kèm khi bấm lưu.
                // Bấm lưu hai lần vì mạng lag thì cả hai lần mang cùng mã này,
                // và WriteOffStock chỉ ghi một dòng hao hụt.
                Forms\Components\Hidden::make('uuid')
                    ->default(fn (): string => (string) Str::uuid()),
                Forms\Components\Select::make('ingredient_id')
                    ->label('Nguyên liệu')
                    ->options(fn () => Ingredient::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->live()
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
                    ->live(onBlur: true)
                    ->required(),
                // Tối thiểu 5 ký tự để khớp ràng buộc database
                // ck_stock_movements_waste_reason (K15) — chặn ngay trên form
                // để thu ngân sửa tại chỗ, thay vì bấm Lưu rồi mới ăn lỗi.
                // WriteOffStock vẫn kiểm lại, đó mới là chốt chặn thật.
                Forms\Components\Textarea::make('detail')
                    ->label('Mô tả chi tiết')
                    ->minLength(5)
                    ->required(),

                // Hai ô dưới CHỈ hiện khi lô hao hụt đáng giá từ ngưỡng trở
                // lên (mặc định 200.000đ, chỉnh ở trang "Ngưỡng cảnh báo"), và
                // chỉ hiện với thu ngân — chủ quán tự ghi thì không cần duyệt.
                // WriteOffStock vẫn tự kiểm lại một lần nữa: form chỉ để người
                // dùng biết trước phải nhập gì, không phải chốt chặn.
                Forms\Components\Placeholder::make('gia_tri_uoc_tinh')
                    ->label('Giá trị lô hao hụt (ước tính theo giá vốn hiện tại)')
                    ->content(fn (Forms\Get $get): string => self::uocTinhGiaTri($get)->format())
                    ->visible(fn (Forms\Get $get): bool => self::coDuLieuTinh($get)),
                Forms\Components\Select::make('approver_user_id')
                    ->label('Chủ quán duyệt')
                    ->options(fn () => User::query()
                        ->where('role', UserRole::Owner)
                        ->where('is_active', true)
                        ->orderBy('name')
                        ->pluck('name', 'id'))
                    ->required()
                    ->visible(fn (Forms\Get $get): bool => self::canDuyet($get)),
                Forms\Components\TextInput::make('approver_pin')
                    ->label('Mã PIN chủ quán')
                    ->password()
                    ->required()
                    ->visible(fn (Forms\Get $get): bool => self::canDuyet($get)),
            ]);
    }

    /** Đủ dữ liệu để ước tính giá trị chưa (đã chọn nguyên liệu và nhập số lượng). */
    private static function coDuLieuTinh(Forms\Get $get): bool
    {
        return filled($get('ingredient_id')) && (int) $get('qty') > 0;
    }

    /**
     * Cùng công thức WriteOffStock dùng — giá vốn bình quân gia quyền hiện tại
     * của nguyên liệu nhân số lượng hao hụt.
     */
    private static function uocTinhGiaTri(Forms\Get $get): Money
    {
        if (! self::coDuLieuTinh($get)) {
            return Money::zero();
        }

        $balance = StockBalance::query()->find((int) $get('ingredient_id'));

        if ($balance === null) {
            return Money::zero();
        }

        return Money::fromInt(
            StockCost::giaVonXuat($balance->qty, $balance->total_cost, (int) $get('qty'))['cost']
        );
    }

    private static function canDuyet(Forms\Get $get): bool
    {
        if (auth()->user()?->role === UserRole::Owner) {
            return false;
        }

        if (! self::coDuLieuTinh($get)) {
            return false;
        }

        return self::uocTinhGiaTri($get)->isAtLeast(app(CauHinhQuan::class)->nguongHaoHutCanPin());
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
