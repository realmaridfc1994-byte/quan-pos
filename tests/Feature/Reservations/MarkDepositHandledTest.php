<?php

declare(strict_types=1);

/**
 * Phase 4 Bước P4-4A.3 — dấu vết tiền cọc (chính sách chốt 12/08).
 *
 * Hệ thống KHÔNG tự quyết giữ hay hoàn cọc. Nhưng phải ghi lại được ai quyết,
 * lúc nào, theo hướng nào, vì sao — nếu không thì ba tháng sau không ai trả
 * lời được "khách này không tới, cọc xử lý chưa?".
 */

use App\Domain\Billing\Enums\PaymentStatus;
use App\Domain\Reservations\Enums\DepositStatus;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Domain\Reservations\Models\Reservation;
use App\Domain\Staffing\Models\User;
use App\Exceptions\DomainException;
use Carbon\CarbonImmutable;
use Spatie\Activitylog\Models\Activity;

require_once __DIR__.'/../../Fixtures/CocDatBan.php';

it('đánh dấu đã hoàn cọc thì ghi đủ ai, lúc nào, vì sao', function () {
    $reservation = Reservation::factory()->noShow()->create();
    thuCocCho($reservation);
    $thuNgan = User::factory()->cashier()->create();

    $ketQua = danhDauCoc($reservation, [
        'handledByUserId' => $thuNgan->id,
        'occurredAt' => CarbonImmutable::parse('2026-08-13 09:30:00'),
    ]);

    expect($ketQua->deposit_status)->toBe(DepositStatus::Refunded)
        ->and($ketQua->deposit_handled_by_user_id)->toBe($thuNgan->id)
        ->and($ketQua->deposit_handled_note)->toBe('Khách gọi điện xin lại, quán đồng ý hoàn')
        // occurred_at lấy từ thời điểm NGHIỆP VỤ người gọi truyền vào, không
        // phải giờ chạy lệnh (M7, bài học bug F Phase 3).
        ->and($ketQua->deposit_handled_at->toDateTimeString())->toBe('2026-08-13 09:30:00');
});

it('đánh dấu quán giữ lại cọc cũng ghi đủ dấu vết', function () {
    $reservation = Reservation::factory()->noShow()->create();
    thuCocCho($reservation);

    $ketQua = danhDauCoc($reservation, [
        'depositStatus' => DepositStatus::Kept,
        'note' => 'Đã báo trước là không hoàn nếu không tới',
    ]);

    expect($ketQua->deposit_status)->toBe(DepositStatus::Kept)
        ->and($ketQua->deposit_handled_note)->toBe('Đã báo trước là không hoàn nếu không tới');
});

it('KHÔNG tự trả tiền cho ai — phiếu cọc giữ nguyên, việc hoàn tiền là VoidPayment riêng', function () {
    $reservation = Reservation::factory()->noShow()->create();
    $phieuCoc = thuCocCho($reservation);

    danhDauCoc($reservation);

    // Đánh dấu chỉ ghi dấu vết. Một cú bấm nhầm không được vừa mất dấu vết
    // vừa mất tiền — nên phiếu cọc phải còn nguyên, chưa bị huỷ.
    $phieuCoc->refresh();
    expect($phieuCoc->status)->toBe(PaymentStatus::Completed)
        ->and($phieuCoc->amount)->toBe(200_000)
        ->and($phieuCoc->voided_at)->toBeNull();
});

it('ghi một dòng nhật ký để ba tháng sau còn tra được', function () {
    $reservation = Reservation::factory()->noShow()->create();
    thuCocCho($reservation);

    danhDauCoc($reservation);

    $nhatKy = Activity::query()->where('log_name', 'coc-dat-ban')->sole();
    expect($nhatKy->getExtraProperty('deposit_status'))->toBe('refunded')
        ->and($nhatKy->getExtraProperty('reservation_id'))->toBe($reservation->id);
});

it('không ghi lý do thì không đánh dấu được', function () {
    $reservation = Reservation::factory()->noShow()->create();
    thuCocCho($reservation);

    expect(fn () => danhDauCoc($reservation, ['note' => '   ']))
        ->toThrow(DomainException::class, 'Phải ghi rõ vì sao xử lý tiền cọc như vậy.');

    expect($reservation->refresh()->deposit_status)->toBe(DepositStatus::Unhandled);
});

it('chọn "chưa xử lý" không phải là một cách xử lý', function () {
    $reservation = Reservation::factory()->noShow()->create();
    thuCocCho($reservation);

    expect(fn () => danhDauCoc($reservation, ['depositStatus' => DepositStatus::Unhandled]))
        ->toThrow(DomainException::class);
});

it('đặt bàn không có phiếu cọc nào thì không có gì để xử lý', function () {
    $reservation = Reservation::factory()->noShow()->create();

    expect(fn () => danhDauCoc($reservation))
        ->toThrow(DomainException::class, 'không có phiếu cọc nào còn hiệu lực');
});

it('phiếu cọc đã bị huỷ thì coi như không còn cọc để xử lý', function () {
    $reservation = Reservation::factory()->noShow()->create();
    thuCocCho($reservation)->update([
        'status' => PaymentStatus::Voided,
        'voided_by_user_id' => User::factory()->owner()->create()->id,
        'voided_at' => now(),
        'void_reason' => 'Ghi nhầm số tiền',
    ]);

    expect(fn () => danhDauCoc($reservation))->toThrow(DomainException::class);
});

it('khách đã ngồi ăn rồi thì cọc là chuyện của cái bill, không xử lý ở đây', function () {
    $reservation = Reservation::factory()->create(['status' => ReservationStatus::Seated]);
    thuCocCho($reservation);

    expect(fn () => danhDauCoc($reservation))
        ->toThrow(DomainException::class, "đang ở trạng thái 'seated'");
});

it('đặt bàn khách tự huỷ mà có cọc cũng xử lý được, không chỉ no_show', function () {
    $reservation = Reservation::factory()->cancelled()->create();
    thuCocCho($reservation);

    expect(danhDauCoc($reservation)->deposit_status)->toBe(DepositStatus::Refunded);
});

it('đã đánh dấu rồi thì không cho đánh dấu đè lên, phải hỏi chủ quán', function () {
    $reservation = Reservation::factory()->noShow()->create();
    thuCocCho($reservation);

    danhDauCoc($reservation, ['depositStatus' => DepositStatus::Refunded]);

    expect(fn () => danhDauCoc($reservation, ['depositStatus' => DepositStatus::Kept]))
        ->toThrow(DomainException::class, 'đã được đánh dấu');

    // Quyết định đầu tiên còn nguyên, không bị ghi đè lặng lẽ.
    expect($reservation->refresh()->deposit_status)->toBe(DepositStatus::Refunded);
});
