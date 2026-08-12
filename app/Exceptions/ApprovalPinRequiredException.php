<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Ném TRƯỚC KHI mở bất kỳ DB::transaction/lockForUpdate nào, khi một việc cần
 * người duyệt bằng mã PIN mà request chưa gửi kèm, hoặc PIN gửi lên sai.
 *
 * Hai chỗ đang dùng:
 *  - ResolveSyncConflict — xử lý xung đột đồng bộ (Phase 2).
 *  - WriteOffStock — hao hụt vượt ngưỡng giá trị (Phase 3 Bước 6).
 *
 * Mã lỗi riêng (`APPROVAL_PIN_REQUIRED`, xem bootstrap/app.php) để màn hình
 * POS phân biệt được với lỗi nghiệp vụ thường (DomainException) và tự hiện ô
 * nhập PIN cho nhân viên, thay vì chỉ hiện một thông báo đỏ chung chung.
 * Không bao giờ ném lỗi này TỪ BÊN TRONG một giao dịch đang mở — ném xong là
 * chưa có khoá nào bị giữ, chưa có gì cần rollback.
 */
final class ApprovalPinRequiredException extends RuntimeException {}
