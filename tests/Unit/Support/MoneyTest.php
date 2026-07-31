<?php

declare(strict_types=1);

use App\Support\Money;

it('cộng hai số tiền ra đúng tổng', function () {
    $tong = Money::fromDong(100_000)->add(Money::fromDong(50_000));

    expect($tong->amount)->toBe(150_000);
});

it('trừ hai số tiền ra đúng hiệu', function () {
    $con_lai = Money::fromDong(100_000)->subtract(Money::fromDong(30_000));

    expect($con_lai->amount)->toBe(70_000);
});

it('trừ ra số âm thì ném exception', function () {
    Money::fromDong(30_000)->subtract(Money::fromDong(100_000));
})->throws(InvalidArgumentException::class);

it('nhân số tiền với số lượng ra đúng thành tiền', function () {
    $thanh_tien = Money::fromDong(25_000)->multiply(3);

    expect($thanh_tien->amount)->toBe(75_000);
});

it('nhân với số lượng âm thì ném exception', function () {
    Money::fromDong(25_000)->multiply(-1);
})->throws(InvalidArgumentException::class);

it('tính phần trăm làm tròn thông thường, 0.5 lên', function () {
    // 10% của 12.345đ = 1.234,5đ → làm tròn lên 1.235đ
    $giam_gia = Money::fromDong(12_345)->percentage(10);

    expect($giam_gia->amount)->toBe(1_235);
});

it('tính phần trăm làm tròn xuống khi phần lẻ dưới 0.5', function () {
    // 10% của 12.340đ = 1.234đ → không có phần lẻ để làm tròn
    $giam_gia = Money::fromDong(12_340)->percentage(10);

    expect($giam_gia->amount)->toBe(1_234);
});

it('phần trăm âm thì ném exception', function () {
    Money::fromDong(100_000)->percentage(-5);
})->throws(InvalidArgumentException::class);

it('khởi tạo bằng float thì ném exception', function () {
    Money::fromDong(1000.5);
})->throws(InvalidArgumentException::class);

it('khởi tạo bằng số âm thì ném exception', function () {
    Money::fromDong(-1);
})->throws(InvalidArgumentException::class);

it('format ra chuỗi tiền Việt Nam đúng định dạng', function () {
    expect(Money::fromDong(1_250_000)->format())->toBe('1.250.000 đ')
        ->and(Money::zero()->format())->toBe('0 đ');
});

it('isZero và isAtLeast hoạt động đúng', function () {
    expect(Money::zero()->isZero())->toBeTrue()
        ->and(Money::fromDong(1)->isZero())->toBeFalse()
        ->and(Money::fromDong(100)->isAtLeast(Money::fromDong(100)))->toBeTrue()
        ->and(Money::fromDong(99)->isAtLeast(Money::fromDong(100)))->toBeFalse();
});

it('các alias plus/minus/times/fromInt tương thích với tên gốc', function () {
    $money = Money::fromInt(100_000)->plus(Money::fromDong(50_000))->minus(Money::fromDong(20_000))->times(2);

    expect($money->amount)->toBe(260_000);
});
