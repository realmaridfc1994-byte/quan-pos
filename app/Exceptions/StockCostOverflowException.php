<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Một phép nhân hoặc cộng trong tính giá vốn vượt giới hạn số nguyên 64 bit.
 * Ở quy mô một quán nhậu điều này gần như không thể xảy ra — nổ ra nghĩa là
 * có dữ liệu bất thường, không phải chuyện lặt vặt để bỏ qua.
 */
final class StockCostOverflowException extends RuntimeException {}
