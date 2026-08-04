<?php

declare(strict_types=1);

use App\Support\VietQr;

/** Tự tính lại CRC-16/CCITT-FALSE để đối chiếu, KHÔNG gọi lại VietQr::crc16 (nó private). */
function crc16LaiChoTest(string $data): string
{
    $crc = 0xFFFF;

    foreach (str_split($data) as $tuTu) {
        $crc ^= (ord($tuTu) << 8);

        for ($i = 0; $i < 8; $i++) {
            $crc = ($crc & 0x8000) ? (($crc << 1) ^ 0x1021) : ($crc << 1);
            $crc &= 0xFFFF;
        }
    }

    return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
}

it('CRC-16/CCITT-FALSE tự viết khớp vector kiểm chuẩn quốc tế "123456789" → 29B1', function () {
    expect(crc16LaiChoTest('123456789'))->toBe('29B1');
});

it('dựng đúng khung EMVCo: mở đầu bằng payload format indicator, kết thúc bằng CRC 4 ký tự hex', function () {
    $payload = VietQr::build(
        bankBin: '970436',
        accountNumber: '0123456789',
        accountName: 'Nguyễn Văn A',
        amountVnd: 150_000,
        purpose: 'PH-20260804-0148',
    );

    // 00 02 01 — tag 00 (payload format indicator), độ dài 2, giá trị "01"
    expect($payload)->toStartWith('000201');

    // 4 ký tự cuối là CRC; phần còn lại (đã CHỨA sẵn "6304" — tiêu đề của
    // chính trường CRC) là dữ liệu dùng để tính CRC đó, theo đúng chuẩn EMVCo.
    $phanDungDeTinhCrc = substr($payload, 0, -4);
    $crcGhiTrongChuoi = substr($payload, -4);
    expect($crcGhiTrongChuoi)->toBe(crc16LaiChoTest($phanDungDeTinhCrc));
});

it('nội dung chuyển khoản chứa mã lượt khách để dò sao kê', function () {
    $payload = VietQr::build(
        bankBin: '970436',
        accountNumber: '0123456789',
        accountName: 'Quan Nhau ABC',
        amountVnd: 150_000,
        purpose: 'PH-20260804-0148',
    );

    expect($payload)->toContain('PH-20260804-0148');
});

it('số tiền trong mã QR đúng bằng số tiền truyền vào', function () {
    $payload = VietQr::build(
        bankBin: '970436',
        accountNumber: '0123456789',
        accountName: 'Quan Nhau ABC',
        amountVnd: 380_000,
        purpose: 'PH-20260804-0148',
    );

    // Tag 54 (transaction amount), độ dài 6, giá trị "380000"
    expect($payload)->toContain('5406380000');
});

it('mã ngân hàng và số tài khoản nằm đúng trong khối GUID NAPAS', function () {
    $payload = VietQr::build(
        bankBin: '970436',
        accountNumber: '0123456789',
        accountName: 'Quan Nhau ABC',
        amountVnd: 100_000,
        purpose: 'PH-1',
    );

    expect($payload)->toContain('A000000727')
        ->toContain('970436')
        ->toContain('0123456789')
        ->toContain('QRIBFTTA'); // chuyển tới SỐ TÀI KHOẢN, không phải số thẻ
});

it('tên chủ tài khoản có dấu tiếng Việt được chuyển về chữ hoa không dấu', function () {
    $payload = VietQr::build(
        bankBin: '970436',
        accountNumber: '0123456789',
        accountName: 'Nguyễn Văn A',
        amountVnd: 100_000,
        purpose: 'PH-1',
    );

    expect($payload)->toContain('NGUYEN VAN A')
        ->not->toContain('Nguyễn');
});

it('số tiền phải lớn hơn 0', function () {
    expect(fn () => VietQr::build('970436', '0123456789', 'A', 0, 'PH-1'))
        ->toThrow(InvalidArgumentException::class);
});
