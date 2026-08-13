<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Token của khách tự gọi món không dùng được — Phase 4.
 *
 * Ba lý do TÁCH RIÊNG vì màn hình của khách phải nói ba câu khác nhau:
 *  - hỏng   → "Mã không đúng. Vui lòng quét lại mã QR trên bàn."
 *  - hết hạn→ "Phiên đã hết hạn. Vui lòng quét lại mã QR trên bàn."
 *  - đã đóng→ "Bàn đã thanh toán xong. Cần gọi thêm, vui lòng gọi phục vụ."
 *
 * Cố ý KHÔNG nói cụ thể hơn (bàn nào, phiên nào, vì sao giải mã hỏng) — với
 * người dùng thì thừa, với kẻ dò thì là chỉ dẫn.
 */
final class GuestTokenException extends RuntimeException
{
    private function __construct(
        string $message,
        public readonly string $errorCode,
    ) {
        parent::__construct($message);
    }

    public static function hong(): self
    {
        return new self('Mã không đúng. Vui lòng quét lại mã QR trên bàn.', 'GUEST_TOKEN_INVALID');
    }

    public static function hetHan(): self
    {
        return new self('Phiên đã hết hạn. Vui lòng quét lại mã QR trên bàn.', 'GUEST_TOKEN_EXPIRED');
    }

    public static function banDaDong(): self
    {
        return new self('Bàn đã thanh toán xong. Cần gọi thêm, vui lòng gọi phục vụ.', 'GUEST_SESSION_CLOSED');
    }
}
