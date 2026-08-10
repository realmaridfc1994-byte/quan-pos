<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Van an toàn của RecordStockMovement bị vi phạm (K9: tồn về 0 thì trị giá
 * phải về 0; tồn dương thì trị giá không âm). Đây LUÔN LÀ lỗi lập trình
 * trong cách tính giá vốn, không phải lỗi dữ liệu người dùng nhập vào —
 * không được đổi thành thông báo nghiệp vụ 422 cho người dùng.
 */
final class StockLedgerInvariantViolatedException extends RuntimeException {}
