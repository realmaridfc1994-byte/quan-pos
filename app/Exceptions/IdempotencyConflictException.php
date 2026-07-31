<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Ném khi một request khác với cùng Idempotency-Key đang được xử lý dở.
 * Đổi thành HTTP 409 ở bootstrap/app.php.
 */
final class IdempotencyConflictException extends RuntimeException {}
