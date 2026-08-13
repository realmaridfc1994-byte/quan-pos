<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Khách quét mã QR nhưng bàn đó chưa có ai mở lượt khách — Phase 4.
 *
 * Client bắt mã lỗi `TABLE_NOT_OPEN` để hiện màn hình "Bấm gọi phục vụ" thay
 * vì màn hình gọi món.
 *
 * CỐ Ý dùng CHUNG một câu trả lời cho ba trường hợp: bàn không tồn tại, bàn
 * đã dẹp (is_active = false), và bàn chưa có khách. Phân biệt ra thì kẻ ngồi
 * nhà thử mã sẽ dò được mã nào là bàn có thật và bàn nào đang có khách —
 * thông tin không việc gì phải cho không.
 */
final class GuestTableNotOpenException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Bàn này chưa có khách. Vui lòng bấm gọi phục vụ để mở bàn.');
    }
}
