<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Ném khi không giành được khoá toàn cục `sync:batch` trong 5 giây — một gói
 * khác đang đồng bộ. Đổi thành HTTP 429 ở bootstrap/app.php; máy POS thử lại
 * sau 10 giây (docs/thiet-ke-dong-bo.md mục 7.1).
 */
final class SyncBatchLockedException extends RuntimeException {}
