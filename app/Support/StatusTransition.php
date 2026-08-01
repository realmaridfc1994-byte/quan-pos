<?php

declare(strict_types=1);

namespace App\Support;

use App\Exceptions\DomainException;

/**
 * Kiểm tra một bước chuyển trạng thái có hợp lệ không: chỉ được đi ĐÚNG MỘT
 * BƯỚC về phía trước trong một chuỗi trạng thái đã định, không được lùi,
 * không được nhảy cóc.
 */
final class StatusTransition
{
    /** @param list<string> $thuTu Chuỗi trạng thái hợp lệ, từ đầu đến cuối. */
    public static function kiemTra(array $thuTu, string $hienTai, string $ke): void
    {
        $viTriHienTai = array_search($hienTai, $thuTu, true);
        $viTriKe = array_search($ke, $thuTu, true);

        if ($viTriHienTai === false || $viTriKe === false || $viTriKe !== $viTriHienTai + 1) {
            throw new DomainException("Không thể chuyển trạng thái từ \"{$hienTai}\" sang \"{$ke}\".");
        }
    }
}
