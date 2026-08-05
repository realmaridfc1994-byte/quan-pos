<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domain\Ordering\Models\DiningTable;
use App\Domain\Staffing\Models\User;
use App\Domain\Sync\Actions\ResolveSyncConflict;
use App\Domain\Sync\DTO\ResolveSyncConflictData;
use App\Domain\Sync\Enums\ConflictStatus;
use App\Domain\Sync\Models\SyncConflict;
use App\Exceptions\DomainException;
use App\Filament\Resources\SyncConflictResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Phase 2 Bước 5 (sửa 04/08) — đường PHỤ để chủ quán xem lại lịch sử xung đột
 * đồng bộ đã xử lý, và xử lý những việc KHÔNG GẤP lúc rảnh. Đường CHÍNH là
 * lớp phủ ngay trên máy POS (resources/js/pos.js) — thu ngân tối đông khách
 * xử lý ngay tại chỗ, không rời màn hình bán hàng, không đăng nhập lại sang
 * đây (xem docs/viec-ton.md).
 *
 * Không có form tạo/sửa — bảng `sync_conflicts` chỉ do SyncBatch (Bước 4)
 * ghi, người chỉ được QUYẾT, không được tự tạo hay tự sửa nội dung xung đột.
 */
final class SyncConflictResource extends Resource
{
    protected static ?string $model = SyncConflict::class;

    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static ?string $navigationLabel = 'Xung đột đồng bộ';

    protected static ?string $modelLabel = 'xung đột';

    protected static ?string $pluralModelLabel = 'Xung đột đồng bộ';

    /** @var array<string, string> */
    private const NHAN_LOAI = [
        'mon_da_het' => 'Bếp báo hết món',
        'hai_may_mo_ban' => 'Hai máy cùng mở bàn',
        'thu_tien_trung' => 'Hai máy cùng thu tiền, đúng khoản đã thu',
        'thu_mot_phan_vuot' => 'Thu một phần ở hai nơi, cộng lại vượt',
        'thu_vuot_giam_gia' => 'Thu offline, tổng đổi vì giảm giá',
        'thu_vuot_tong_doi_khac' => 'Thu offline, tổng đổi vì lý do khác',
        'luot_da_dong' => 'Gọi món vào lượt đã đóng',
        'ca_da_dong' => 'Phiếu thu thuộc ca đã đóng',
        'gia_lech' => 'Giá món đổi',
        'luot_da_huy' => 'Gọi món vào lượt đã huỷ',
        'thieu_thao_tac_goc' => 'Thiếu thao tác gốc',
    ];

    /** @var list<string> */
    private const CAN_PIN_DUYET = ['thu_tien_trung', 'thu_mot_phan_vuot', 'thu_vuot_giam_gia', 'thu_vuot_tong_doi_khac'];

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\IconColumn::make('is_urgent')
                    ->label('Gấp')
                    ->boolean()
                    ->trueIcon('heroicon-o-fire')
                    ->trueColor('danger'),
                Tables\Columns\TextColumn::make('conflict_kind')
                    ->label('Loại')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::NHAN_LOAI[$state] ?? $state),
                Tables\Columns\TextColumn::make('message_vi')
                    ->label('Chuyện gì đã xảy ra')
                    ->wrap()
                    ->limit(300),
                Tables\Columns\TextColumn::make('device_id')
                    ->label('Máy POS'),
                Tables\Columns\TextColumn::make('occurred_at')
                    ->label('Lúc xảy ra')
                    ->dateTime('H:i d/m/Y'),
                Tables\Columns\TextColumn::make('received_at')
                    ->label('Server nhận')
                    ->dateTime('H:i d/m/Y'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->formatStateUsing(fn (ConflictStatus $state): string => match ($state) {
                        ConflictStatus::Pending => 'Đang chờ',
                        ConflictStatus::Resolved => 'Đã xử lý',
                        ConflictStatus::Dismissed => 'Đã bỏ qua',
                    })
                    ->color(fn (ConflictStatus $state): string => match ($state) {
                        ConflictStatus::Pending => 'warning',
                        ConflictStatus::Resolved => 'success',
                        ConflictStatus::Dismissed => 'gray',
                    }),
            ])
            ->defaultSort('is_urgent', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Trạng thái')
                    ->options([
                        'pending' => 'Đang chờ',
                        'resolved' => 'Đã xử lý',
                        'dismissed' => 'Đã bỏ qua',
                    ])
                    ->default('pending'),
            ])
            ->actions([
                Tables\Actions\Action::make('resolve')
                    ->label('Xử lý')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (SyncConflict $record): bool => $record->status === ConflictStatus::Pending)
                    ->form(fn (SyncConflict $record): array => [
                        Forms\Components\Placeholder::make('message')
                            ->label('Chuyện gì đã xảy ra')
                            ->content($record->message_vi),
                        Forms\Components\Radio::make('resolution')
                            ->label('Chọn một phương án')
                            ->options(collect($record->options)->mapWithKeys(fn (array $o) => [$o['key'] => $o['label']])->all())
                            ->required(),
                        Forms\Components\Select::make('dining_table_ids')
                            ->label('Chọn bàn khác')
                            ->helperText('Chỉ cần chọn khi phương án ở trên yêu cầu dựng lại bàn/lượt khách.')
                            ->multiple()
                            ->options(fn () => DiningTable::query()->where('is_active', true)->orderBy('sort_order')->pluck('code', 'id'))
                            ->visible(fn (Get $get): bool => in_array($get('resolution'), ['tach', 'mo_luot_moi', 'tao_luot_moi'], true)),
                        Forms\Components\Select::make('approver_user_id')
                            ->label('Người duyệt')
                            ->helperText('Xung đột dính tiền bắt buộc có người duyệt bằng mã PIN.')
                            ->options(fn () => User::query()->whereIn('role', ['owner', 'cashier'])->where('is_active', true)->pluck('name', 'id'))
                            ->visible(fn (SyncConflict $record): bool => in_array($record->conflict_kind, self::CAN_PIN_DUYET, true))
                            ->required(fn (SyncConflict $record): bool => in_array($record->conflict_kind, self::CAN_PIN_DUYET, true)),
                        Forms\Components\TextInput::make('approver_pin')
                            ->label('Mã PIN')
                            ->password()
                            ->visible(fn (SyncConflict $record): bool => in_array($record->conflict_kind, self::CAN_PIN_DUYET, true))
                            ->required(fn (SyncConflict $record): bool => in_array($record->conflict_kind, self::CAN_PIN_DUYET, true)),
                        Forms\Components\Textarea::make('note')
                            ->label('Lý do quyết định')
                            ->required(),
                    ])
                    ->action(function (SyncConflict $record, array $data): void {
                        try {
                            app(ResolveSyncConflict::class)->handle(new ResolveSyncConflictData(
                                conflictId: $record->id,
                                dismiss: false,
                                resolution: $data['resolution'],
                                note: $data['note'],
                                resolvedByUserId: (int) auth()->id(),
                                diningTableIds: array_map('intval', $data['dining_table_ids'] ?? []),
                                approverUserId: isset($data['approver_user_id']) ? (int) $data['approver_user_id'] : null,
                                approverPin: $data['approver_pin'] ?? null,
                            ));

                            Notification::make()->success()->title('Đã xử lý xung đột.')->send();
                        } catch (DomainException $e) {
                            Notification::make()->danger()->title($e->getMessage())->send();
                        }
                    }),
                Tables\Actions\Action::make('dismiss')
                    ->label('Bỏ qua')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->visible(fn (SyncConflict $record): bool => $record->status === ConflictStatus::Pending)
                    ->requiresConfirmation()
                    ->form(fn (SyncConflict $record): array => [
                        Forms\Components\Select::make('approver_user_id')
                            ->label('Người duyệt')
                            ->helperText('Xung đột dính tiền bắt buộc có người duyệt bằng mã PIN, kể cả khi bỏ qua.')
                            ->options(fn () => User::query()->whereIn('role', ['owner', 'cashier'])->where('is_active', true)->pluck('name', 'id'))
                            ->visible(in_array($record->conflict_kind, self::CAN_PIN_DUYET, true))
                            ->required(in_array($record->conflict_kind, self::CAN_PIN_DUYET, true)),
                        Forms\Components\TextInput::make('approver_pin')
                            ->label('Mã PIN')
                            ->password()
                            ->visible(in_array($record->conflict_kind, self::CAN_PIN_DUYET, true))
                            ->required(in_array($record->conflict_kind, self::CAN_PIN_DUYET, true)),
                        Forms\Components\Textarea::make('note')
                            ->label('Lý do xem rồi quyết định chưa xử lý')
                            ->required(),
                    ])
                    ->action(function (SyncConflict $record, array $data): void {
                        try {
                            app(ResolveSyncConflict::class)->handle(new ResolveSyncConflictData(
                                conflictId: $record->id,
                                dismiss: true,
                                resolution: null,
                                note: $data['note'],
                                resolvedByUserId: (int) auth()->id(),
                                approverUserId: isset($data['approver_user_id']) ? (int) $data['approver_user_id'] : null,
                                approverPin: $data['approver_pin'] ?? null,
                            ));

                            Notification::make()->success()->title('Đã ghi nhận — chưa xử lý.')->send();
                        } catch (DomainException $e) {
                            Notification::make()->danger()->title($e->getMessage())->send();
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageSyncConflicts::route('/'),
        ];
    }

    public static function getNavigationBadge(): string
    {
        return (string) SyncConflict::query()->where('status', ConflictStatus::Pending)->count();
    }

    public static function getNavigationBadgeColor(): string
    {
        return SyncConflict::query()->where('status', ConflictStatus::Pending)->where('is_urgent', true)->exists()
            ? 'danger'
            : 'warning';
    }
}
