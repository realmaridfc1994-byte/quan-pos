<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Ném khi route bắt buộc Idempotency-Key nhưng request không gửi header này.
 * Đổi thành HTTP 400 ở bootstrap/app.php.
 */
final class IdempotencyKeyRequiredException extends RuntimeException {}
