<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Vi phạm quy tắc nghiệp vụ (không phải lỗi hình dạng dữ liệu — đó là việc của FormRequest).
 * Được đổi thành HTTP 422 ở một chỗ duy nhất: bootstrap/app.php.
 */
final class DomainException extends RuntimeException {}
