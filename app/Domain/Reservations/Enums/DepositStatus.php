<?php

declare(strict_types=1);

namespace App\Domain\Reservations\Enums;

/**
 * Tiền cọc của một đặt bàn đã được xử lý chưa, và theo hướng nào.
 *
 * Hệ thống KHÔNG BAO GIỜ tự đặt giá trị này thành refunded/kept — người thao
 * tác đánh dấu, vì chỉ người mới biết đã nói gì với khách. `unhandled` nghĩa
 * là "chưa ai quyết", không phải "không có cọc".
 */
enum DepositStatus: string
{
    case Unhandled = 'unhandled';
    case Refunded = 'refunded';
    case Kept = 'kept';

    public function label(): string
    {
        return match ($this) {
            self::Unhandled => 'Chưa xử lý',
            self::Refunded => 'Đã hoàn khách',
            self::Kept => 'Quán giữ lại',
        };
    }
}
