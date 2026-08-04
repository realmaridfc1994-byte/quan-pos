<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/**
 * Mọi endpoint POST/PATCH dưới /api/v1 ghi dữ liệu đều phải chống bấm trùng
 * (idempotent), trừ danh sách miễn trừ dưới đây. Quét toàn bộ route đã đăng
 * ký, không liệt kê tay từng route mới — thêm route ghi dữ liệu mà quên gắn
 * middleware là test này phải đỏ ngay.
 */
it('mọi route POST/PATCH dưới /api/v1 (trừ danh sách miễn trừ) đều có middleware idempotent', function () {
    $duocMienTru = [
        // Không tạo bản ghi giao dịch nên không cần chống trùng.
        'api/v1/auth/login',
        'api/v1/auth/logout',
        'api/v1/auth/pin-verify',
        // Tự chống trùng theo op_uuid của TỪNG thao tác bên trong gói, không
        // dùng header Idempotency-Key — xem routes/api.php và SyncBatch.
        'api/v1/sync/batch',
    ];

    $thieuMiddleware = [];

    foreach (Route::getRoutes() as $route) {
        $uri = $route->uri();

        if (! str_starts_with($uri, 'api/v1')) {
            continue;
        }

        $coPhuongThucGhi = array_intersect(['POST', 'PATCH'], $route->methods());

        if ($coPhuongThucGhi === []) {
            continue;
        }

        if (in_array($uri, $duocMienTru, true)) {
            continue;
        }

        if (! in_array('idempotent', $route->gatherMiddleware(), true)) {
            $thieuMiddleware[] = implode('|', $coPhuongThucGhi).' /'.$uri;
        }
    }

    expect($thieuMiddleware)->toBe([]);
});
