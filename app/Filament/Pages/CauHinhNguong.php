<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\Staffing\Enums\UserRole;
use App\Support\CauHinhQuan;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Hai ngưỡng chủ quán tự chỉnh — Phase 3 Bước 6/8.
 *
 * CHỈ owner vào được. Giá trị lưu qua App\Support\CauHinhQuan, trong bảng
 * `cau_hinh_quan` (từ Phase 5 Bước 5A.1; trước đó nằm nhờ trong bảng `cache`
 * và bị `php artisan cache:clear` xoá mất).
 *
 * Cả hai con số mặc định là ĐIỂM KHỞI ĐẦU, không phải chân lý — chủ quán nhìn
 * dữ liệu thật vài tuần rồi tự kéo lên hoặc xuống. Lý do chọn 25% và 200.000đ
 * viết trong config/pos.php.
 */
final class CauHinhNguong extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?string $navigationGroup = 'Kho';

    protected static ?string $navigationLabel = 'Ngưỡng cảnh báo';

    protected static ?string $title = 'Ngưỡng cảnh báo';

    protected static string $view = 'filament.pages.cau-hinh-nguong';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->role === UserRole::Owner;
    }

    public function mount(): void
    {
        $cauHinh = app(CauHinhQuan::class);

        $this->form->fill([
            CauHinhQuan::KHOA_LAI_THAP => $cauHinh->nguongLaiThapPhanTram(),
            CauHinhQuan::KHOA_HAO_HUT_PIN => $cauHinh->nguongHaoHutCanPin()->amount,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make(CauHinhQuan::KHOA_LAI_THAP)
                    ->label('Ngưỡng "lãi thấp" (%)')
                    ->helperText('Món bán chạy mà tỉ lệ lãi dưới mức này sẽ hiện trong cảnh báo trên trang Báo cáo. Bia thường lãi 20-30%, món nấu 50-70% — để 25% thì cảnh báo bắt đúng nhóm đồ uống lãi mỏng. Để quá thấp thì cảnh báo không bao giờ kêu.')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->suffix('%')
                    ->required(),
                Forms\Components\Placeholder::make('nguon_lai_thap')
                    ->label('Đang dùng')
                    ->content(fn (): string => self::moTaNguon(CauHinhQuan::KHOA_LAI_THAP, laTien: false)),
                Forms\Components\TextInput::make(CauHinhQuan::KHOA_HAO_HUT_PIN)
                    ->label('Hao hụt từ mức này trở lên phải có chủ quán duyệt bằng PIN (đồng)')
                    ->helperText('Tính theo GIÁ TRỊ TIỀN của lô hao hụt, không theo số lượng. Dưới mức này thu ngân tự ghi, chỉ cần lý do — để nhân viên không ngại ghi, vì hao hụt không ghi còn tệ hơn. Chủ quán tự ghi thì không bao giờ cần PIN.')
                    ->numeric()
                    ->minValue(0)
                    ->suffix('đ')
                    ->required(),
                Forms\Components\Placeholder::make('nguon_hao_hut')
                    ->label('Đang dùng')
                    ->content(fn (): string => self::moTaNguon(CauHinhQuan::KHOA_HAO_HUT_PIN, laTien: true)),
            ])
            ->statePath('data');
    }

    /**
     * Một câu nói thẳng cho chủ quán biết con số đang có hiệu lực là mặc định
     * hay do người nào đó đổi. Từ 5A.1 hai ngưỡng đã nằm trong bảng riêng nên
     * không còn tự bốc hơi theo cache nữa, nhưng câu này vẫn đáng giữ: nó trả
     * lời câu hỏi "số này ở đâu ra" ngay trên màn hình, thay vì bắt chủ quán
     * đi tìm trong nhật ký.
     */
    private static function moTaNguon(string $khoa, bool $laTien): string
    {
        $nguon = app(CauHinhQuan::class)->nguonGiaTri($khoa);

        $giaTri = $laTien
            ? Money::fromInt($nguon['gia_tri'])->format()
            : $nguon['gia_tri'].'%';

        if (! $nguon['da_chinh']) {
            return "Đang dùng mặc định {$giaTri}.";
        }

        $ten = $nguon['nguoi_doi'] ?? 'Không rõ ai';
        $luc = $nguon['luc']?->format('d/m/Y') ?? 'không rõ ngày';

        return "{$ten} đổi thành {$giaTri} ngày {$luc}.";
    }

    public function luu(): void
    {
        $duLieu = $this->form->getState();
        $cauHinh = app(CauHinhQuan::class);

        $cauHinh->datNguongLaiThapPhanTram((int) $duLieu[CauHinhQuan::KHOA_LAI_THAP]);
        $cauHinh->datNguongHaoHutCanPin((int) $duLieu[CauHinhQuan::KHOA_HAO_HUT_PIN]);

        Notification::make()->success()->title('Đã lưu hai ngưỡng.')->send();
    }

    /** @return array<Action> */
    protected function getFormActions(): array
    {
        return [
            Action::make('luu')
                ->label('Lưu')
                ->submit('luu'),
        ];
    }
}
