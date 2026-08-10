<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Sổ cái kho không bao giờ được xoá — ghi sai thì ghi thêm một dòng bù trừ
 * (adjust), không xoá dòng cũ. Nổ ra là có ai đó cố gọi delete()/forceDelete()
 * trực tiếp trên StockMovement, luôn là lỗi lập trình.
 */
final class StockMovementImmutableException extends RuntimeException {}
