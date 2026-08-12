<?php

declare(strict_types=1);

use App\Domain\Staffing\Models\User;
use App\Filament\Pages\CauHinhNguong;
use App\Support\CauHinhQuan;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

it('chưa chỉnh lần nào thì đọc ra giá trị khởi đầu trong config: 25% và 200.000đ', function () {
    $cauHinh = new CauHinhQuan;

    expect($cauHinh->nguongLaiThapPhanTram())->toBe(25)
        ->and($cauHinh->nguongLaiThapTiLe())->toBe(0.25)
        ->and($cauHinh->nguongHaoHutCanPin()->amount)->toBe(200_000);
});

it('chủ quán chỉnh xong thì lần đọc sau ra giá trị mới', function () {
    $cauHinh = new CauHinhQuan;

    $cauHinh->datNguongLaiThapPhanTram(40);
    $cauHinh->datNguongHaoHutCanPin(500_000);

    expect((new CauHinhQuan)->nguongLaiThapPhanTram())->toBe(40)
        ->and((new CauHinhQuan)->nguongHaoHutCanPin()->amount)->toBe(500_000);
});

it('đặt lại mặc định thì quay về giá trị khởi đầu', function () {
    $cauHinh = new CauHinhQuan;
    $cauHinh->datNguongLaiThapPhanTram(40);

    $cauHinh->datLaiMacDinh();

    expect((new CauHinhQuan)->nguongLaiThapPhanTram())->toBe(25);
});

it('chặn ngưỡng lãi thấp ngoài khoảng 0-100%', function () {
    expect(fn () => (new CauHinhQuan)->datNguongLaiThapPhanTram(150))
        ->toThrow(InvalidArgumentException::class);
});

it('chặn ngưỡng hao hụt âm', function () {
    expect(fn () => (new CauHinhQuan)->datNguongHaoHutCanPin(-1))
        ->toThrow(InvalidArgumentException::class);
});

it('chủ quán mở được màn hình Ngưỡng cảnh báo và lưu được hai ngưỡng mới', function () {
    $this->actingAs(User::factory()->owner()->create());

    Livewire::test(CauHinhNguong::class)
        ->assertOk()
        ->assertFormSet([
            CauHinhQuan::KHOA_LAI_THAP => 25,
            CauHinhQuan::KHOA_HAO_HUT_PIN => 200_000,
        ])
        ->fillForm([
            CauHinhQuan::KHOA_LAI_THAP => 30,
            CauHinhQuan::KHOA_HAO_HUT_PIN => 300_000,
        ])
        ->call('luu')
        ->assertHasNoFormErrors();

    expect((new CauHinhQuan)->nguongLaiThapPhanTram())->toBe(30)
        ->and((new CauHinhQuan)->nguongHaoHutCanPin()->amount)->toBe(300_000);
});

// ── Dấu vết mỗi lần đổi ngưỡng (quyết định 11/08) ──────────────────────────

it('mỗi lần đổi ngưỡng ghi một dòng nhật ký: ai đổi, từ bao nhiêu sang bao nhiêu', function () {
    $chuQuan = User::factory()->owner()->create();
    $this->actingAs($chuQuan);

    (new CauHinhQuan)->datNguongHaoHutCanPin(50_000);

    $dong = Activity::query()
        ->where('log_name', CauHinhQuan::LOG_NAME)
        ->where('event', CauHinhQuan::KHOA_HAO_HUT_PIN)
        ->sole();

    expect($dong->causer_id)->toBe($chuQuan->id)
        ->and($dong->properties['gia_tri_cu'])->toBe(200_000)
        ->and($dong->properties['gia_tri_moi'])->toBe(50_000)
        ->and($dong->properties['nguon_gia_tri_cu'])->toBe('mac_dinh');
});

it('chưa ai chỉnh thì màn hình báo đang dùng mặc định', function () {
    $nguon = (new CauHinhQuan)->nguonGiaTri(CauHinhQuan::KHOA_HAO_HUT_PIN);

    expect($nguon['da_chinh'])->toBeFalse()
        ->and($nguon['gia_tri'])->toBe(200_000)
        ->and($nguon['nguoi_doi'])->toBeNull();
});

it('chỉnh rồi thì màn hình báo rõ ai đổi thành bao nhiêu, lúc nào', function () {
    $chuQuan = User::factory()->owner()->create(['name' => 'Chủ quán Tư']);
    $this->actingAs($chuQuan);

    (new CauHinhQuan)->datNguongHaoHutCanPin(50_000);

    $nguon = (new CauHinhQuan)->nguonGiaTri(CauHinhQuan::KHOA_HAO_HUT_PIN);

    expect($nguon['da_chinh'])->toBeTrue()
        ->and($nguon['gia_tri'])->toBe(50_000)
        ->and($nguon['nguoi_doi'])->toBe('Chủ quán Tư')
        ->and($nguon['luc'])->not->toBeNull();
});

it('mất giá trị đã chỉnh (cache:clear) thì màn hình quay về báo mặc định, nhật ký vẫn còn', function () {
    $this->actingAs(User::factory()->owner()->create());
    $cauHinh = new CauHinhQuan;

    $cauHinh->datNguongHaoHutCanPin(50_000);
    $cauHinh->datLaiMacDinh();

    $nguon = (new CauHinhQuan)->nguonGiaTri(CauHinhQuan::KHOA_HAO_HUT_PIN);

    expect($nguon['da_chinh'])->toBeFalse()
        ->and($nguon['gia_tri'])->toBe(200_000);

    // Hai dòng: một lần đổi xuống 50.000đ, một lần đặt lại về mặc định.
    expect(Activity::query()->where('log_name', CauHinhQuan::LOG_NAME)->count())->toBe(2);
});

it('thu ngân không vào được màn hình Ngưỡng cảnh báo', function () {
    $this->actingAs(User::factory()->cashier()->create());

    expect(CauHinhNguong::canAccess())->toBeFalse();
});
