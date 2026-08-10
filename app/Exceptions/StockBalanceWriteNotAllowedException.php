<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Có nơi khác ngoài RecordStockMovement cố ghi vào stock_balances. Đây LUÔN
 * LÀ lỗi lập trình (thêm một đường ghi thứ hai), không phải lỗi dữ liệu.
 */
final class StockBalanceWriteNotAllowedException extends RuntimeException {}
