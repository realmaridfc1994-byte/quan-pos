<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Tài khoản ngân hàng của quán — Phase 2 Bước 7
    |--------------------------------------------------------------------------
    |
    | Ba giá trị này bắt buộc phải có thật trong .env mới sinh được mã QR:
    |
    |   VIETQR_BANK_BIN=970436          # Mã ngân hàng (BIN) theo chuẩn NAPAS,
    |                                   # ví dụ Vietcombank = 970436
    |   VIETQR_ACCOUNT_NUMBER=0123456789
    |   VIETQR_ACCOUNT_NAME="QUAN NHAU ABC"
    |
    | Không có trong .env thì GetVietQrForTableSession từ chối sinh mã QR,
    | báo rõ cho chủ quán biết cần cấu hình gì — không sinh mã QR sai hoặc
    | trống rồi để thu ngân chuyển tiền vào tài khoản không xác định.
    */

    'bank_bin' => env('VIETQR_BANK_BIN'),
    'account_number' => env('VIETQR_ACCOUNT_NUMBER'),
    'account_name' => env('VIETQR_ACCOUNT_NAME'),
];
