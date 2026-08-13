<?php

declare(strict_types=1);

/**
 * Phase 4 Bước P4-4A.3 — báo cáo "cọc còn treo, chưa ai xử lý".
 *
 * Đây là mục thứ ba trong ba dấu vết cọc chốt 12/08. Không có nó thì hai mục
 * kia vô dụng: có ghi lại nhưng không ai tra ra được khoản nào còn treo.
 */

use App\Domain\Loyalty\Models\Customer;
use App\Domain\Reservations\Enums\DepositStatus;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Domain\Reservations\Models\Reservation;
use App\Domain\Reservations\Queries\GetUnhandledDeposits;
use App\Domain\Staffing\Models\User;
use Carbon\CarbonImmutable;

require_once __DIR__.'/../../Fixtures/CocDatBan.php';

it('liệt kê đúng đặt bàn no_show còn cọc chưa xử lý, kèm số tiền và tên khách', function () {
    $khach = Customer::factory()->create(['name' => 'Anh Tuấn', 'phone' => '0909111222']);
    $reservation = Reservation::factory()->noShow()->create([
        'customer_id' => $khach->id,
        'reserved_at' => CarbonImmutable::parse('2026-08-10 19:00:00'),
        'guest_count' => 6,
    ]);
    thuCocCho($reservation, 300_000);

    $danhSach = app(GetUnhandledDeposits::class)->handle();

    expect($danhSach)->toHaveCount(1);
    expect($danhSach->first())
        ->toMatchArray([
            'reservation_id' => $reservation->id,
            'status' => 'no_show',
            'khach' => 'Anh Tuấn',
            'dien_thoai' => '0909111222',
            'so_khach' => 6,
            'tien_coc' => 300_000,
            'tien_coc_text' => '300.000 đ',
        ]);
});

it('khách vãng lai không có hồ sơ vẫn hiện trong danh sách', function () {
    $reservation = Reservation::factory()->noShow()->create(['customer_id' => null]);
    thuCocCho($reservation);

    $dong = app(GetUnhandledDeposits::class)->handle()->sole();

    expect($dong['khach'])->toBe('Khách vãng lai')
        ->and($dong['dien_thoai'])->toBeNull()
        ->and($dong['tien_coc'])->toBe(200_000);
});

it('đặt bàn khách tự huỷ mà còn cọc cũng nằm trong danh sách, không chỉ no_show', function () {
    $huy = Reservation::factory()->cancelled()->create();
    thuCocCho($huy, 150_000);

    $khongToi = Reservation::factory()->noShow()->create();
    thuCocCho($khongToi, 250_000);

    $danhSach = app(GetUnhandledDeposits::class)->handle();

    expect($danhSach)->toHaveCount(2)
        ->and($danhSach->pluck('status')->sort()->values()->all())->toBe(['cancelled', 'no_show']);
});

it('đánh dấu xử lý xong thì rơi khỏi danh sách ngay', function () {
    $reservation = Reservation::factory()->noShow()->create();
    thuCocCho($reservation);

    expect(app(GetUnhandledDeposits::class)->handle())->toHaveCount(1);

    danhDauCoc($reservation, ['depositStatus' => DepositStatus::Kept, 'note' => 'Quán giữ theo thoả thuận']);

    expect(app(GetUnhandledDeposits::class)->handle())->toHaveCount(0);
});

it('đặt bàn không đặt cọc thì không có gì để hỏi, không nằm trong danh sách', function () {
    Reservation::factory()->noShow()->create();

    expect(app(GetUnhandledDeposits::class)->handle())->toHaveCount(0);
});

it('đặt bàn còn chờ hoặc khách đã ngồi ăn thì không nằm trong danh sách', function () {
    $choToi = Reservation::factory()->create();
    thuCocCho($choToi);

    $daNgoi = Reservation::factory()->create(['status' => ReservationStatus::Seated]);
    thuCocCho($daNgoi);

    expect(app(GetUnhandledDeposits::class)->handle())->toHaveCount(0);
});

it('một đặt bàn thu cọc làm hai lần thì cộng lại thành một dòng', function () {
    $reservation = Reservation::factory()->noShow()->create();
    thuCocCho($reservation, 100_000);
    thuCocCho($reservation, 150_000);

    $dong = app(GetUnhandledDeposits::class)->handle()->sole();

    expect($dong['tien_coc'])->toBe(250_000)
        ->and($dong['tien_coc_text'])->toBe('250.000 đ');
});

it('đếm đúng số ngày khoản cọc đã treo', function () {
    $this->travelTo(CarbonImmutable::parse('2026-08-20 08:00:00'));

    $reservation = Reservation::factory()->noShow()->create([
        'reserved_at' => CarbonImmutable::parse('2026-08-14 19:00:00'),
    ]);
    thuCocCho($reservation);

    expect(app(GetUnhandledDeposits::class)->handle()->sole()['so_ngay_treo'])->toBe(6);
});

it('khoản treo lâu nhất đứng đầu danh sách', function () {
    $moi = Reservation::factory()->noShow()->create(['reserved_at' => CarbonImmutable::parse('2026-08-18 19:00:00')]);
    thuCocCho($moi);

    $cu = Reservation::factory()->noShow()->create(['reserved_at' => CarbonImmutable::parse('2026-08-01 19:00:00')]);
    thuCocCho($cu);

    $danhSach = app(GetUnhandledDeposits::class)->handle();

    expect($danhSach->first()['reservation_id'])->toBe($cu->id);
});

it('người đánh dấu được ghi lại là ai', function () {
    $thuNgan = User::factory()->cashier()->create();
    $reservation = Reservation::factory()->noShow()->create();
    thuCocCho($reservation);

    danhDauCoc($reservation, ['handledByUserId' => $thuNgan->id]);

    expect($reservation->refresh()->depositHandledBy->id)->toBe($thuNgan->id);
});
