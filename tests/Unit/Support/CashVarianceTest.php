<?php

declare(strict_types=1);

use App\Support\CashVariance;
use App\Support\Money;

it('đếm thiếu tiền thì isShortage true, absolute và format đúng', function () {
    $chenh_lech = CashVariance::between(
        counted: Money::fromInt(4_800_000),
        expected: Money::fromInt(5_000_000),
    );

    expect($chenh_lech->isShortage())->toBeTrue()
        ->and($chenh_lech->isBalanced())->toBeFalse()
        ->and($chenh_lech->isSurplus())->toBeFalse()
        ->and($chenh_lech->absolute()->amount)->toBe(200_000)
        ->and($chenh_lech->format())->toBe('Thiếu 200.000 đ');
});

it('đếm khớp với sổ sách thì isBalanced true', function () {
    $chenh_lech = CashVariance::between(
        counted: Money::fromInt(5_000_000),
        expected: Money::fromInt(5_000_000),
    );

    expect($chenh_lech->isBalanced())->toBeTrue()
        ->and($chenh_lech->isShortage())->toBeFalse()
        ->and($chenh_lech->isSurplus())->toBeFalse()
        ->and($chenh_lech->absolute()->amount)->toBe(0)
        ->and($chenh_lech->format())->toBe('Khớp');
});

it('đếm thừa tiền thì isSurplus true, absolute và format đúng', function () {
    $chenh_lech = CashVariance::between(
        counted: Money::fromInt(5_050_000),
        expected: Money::fromInt(5_000_000),
    );

    expect($chenh_lech->isSurplus())->toBeTrue()
        ->and($chenh_lech->isBalanced())->toBeFalse()
        ->and($chenh_lech->isShortage())->toBeFalse()
        ->and($chenh_lech->absolute()->amount)->toBe(50_000)
        ->and($chenh_lech->format())->toBe('Thừa 50.000 đ');
});

it('không có trường hợp nào — kể cả thiếu tiền — ném exception', function () {
    // Đây chính là lý do CashVariance tồn tại: Money::minus() sẽ ném lỗi khi
    // két thiếu tiền, nhưng đối soát ca PHẢI ghi lại được trường hợp này.
    expect(fn () => CashVariance::between(Money::fromInt(0), Money::fromInt(10_000_000)))
        ->not->toThrow(Throwable::class);

    expect(fn () => CashVariance::between(Money::fromInt(10_000_000), Money::fromInt(0)))
        ->not->toThrow(Throwable::class);

    expect(fn () => CashVariance::between(Money::zero(), Money::zero()))
        ->not->toThrow(Throwable::class);
});
