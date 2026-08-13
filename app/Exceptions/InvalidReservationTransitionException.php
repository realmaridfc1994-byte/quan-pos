<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Chuyển trạng thái đặt bàn sai luồng (M1, docs/schema.md PHẦN M.4) — VD
 * xác nhận một đặt bàn đã seated, hoặc xếp bàn cho một đặt bàn đã cancelled.
 * Đổi thành HTTP 422 ở bootstrap/app.php, giống các exception nghiệp vụ khác.
 */
final class InvalidReservationTransitionException extends RuntimeException {}
