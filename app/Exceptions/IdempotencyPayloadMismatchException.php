<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Ném khi cùng một Idempotency-Key được gửi lại nhưng nội dung request khác với
 * lần trước — chặn việc âm thầm bỏ qua request thứ hai chỉ vì trùng mã.
 * Đổi thành HTTP 422 ở bootstrap/app.php.
 */
final class IdempotencyPayloadMismatchException extends RuntimeException {}
