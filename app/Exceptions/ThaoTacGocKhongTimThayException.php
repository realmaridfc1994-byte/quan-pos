<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Ném nội bộ trong SyncBatch khi một thao tác tham chiếu tới một uuid (lượt
 * khách, phiếu...) không tìm thấy trong gói này lẫn trên server — dòng 10 của
 * ma trận (docs/thiet-ke-dong-bo.md mục 5). Không bao giờ lộ ra ngoài Action.
 */
final class ThaoTacGocKhongTimThayException extends RuntimeException {}
