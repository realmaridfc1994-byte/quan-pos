<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BillController;
use App\Http\Controllers\Api\CashMovementController;
use App\Http\Controllers\Api\FloorPlanController;
use App\Http\Controllers\Api\GuestSessionController;
use App\Http\Controllers\Api\KdsController;
use App\Http\Controllers\Api\MenuController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\OrderItemController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PurchaseController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\ShiftController;
use App\Http\Controllers\Api\SyncBatchController;
use App\Http\Controllers\Api\SyncConflictController;
use App\Http\Controllers\Api\TableSessionController;
use App\Http\Controllers\Api\VietQrController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    /*
     * KÊNH CÔNG KHAI của khách quét mã QR trên bàn — Phase 4.
     *
     * KHÔNG có auth:sanctum: khách bước vào quán không có tài khoản nào. Chốt
     * chặn nằm ở chỗ khác — server chỉ cấp token khi bàn ĐANG CÓ KHÁCH NGỒI
     * THẬT, và token chết ngay khi thu ngân đóng bàn.
     *
     * Hai bộ đếm chặn gọi dồn dập khai ở AppServiceProvider:
     *   guest-doi-ma  — 10 lần/phút theo máy khách, chặn máy dò mã bàn
     *   guest-api     — 60 lần/phút theo TỪNG TOKEN
     */
    Route::prefix('guest')->group(function (): void {
        Route::post('sessions', [GuestSessionController::class, 'store'])
            ->middleware('throttle:guest-doi-ma');

        Route::middleware(['guest-session', 'throttle:guest-api'])->group(function (): void {
            Route::get('session', [GuestSessionController::class, 'show']);
        });
    });

    Route::prefix('auth')->group(function (): void {
        Route::post('login', [AuthController::class, 'login']);

        Route::middleware(['auth:sanctum', 'active'])->group(function (): void {
            Route::post('logout', [AuthController::class, 'logout']);

            // Chống dò PIN bằng cách thử vét cạn: tối đa 5 lần/phút và 20 lần/giờ theo user gọi.
            Route::post('pin-verify', [AuthController::class, 'pinVerify'])
                ->middleware(['throttle:5,1,pin-verify-minute', 'throttle:20,60,pin-verify-hour']);
        });
    });

    Route::prefix('shifts')->middleware(['auth:sanctum', 'active'])->group(function (): void {
        Route::post('open', [ShiftController::class, 'open'])->middleware('idempotent');
        Route::get('current', [ShiftController::class, 'current']);
        Route::post('{shift}/close', [ShiftController::class, 'close'])->middleware('idempotent');
        Route::post('{shift}/cash-movements', [CashMovementController::class, 'store'])->middleware('idempotent');
        Route::get('{shift}/report', [ShiftController::class, 'report']);
    });

    Route::middleware(['auth:sanctum', 'active'])->group(function (): void {
        Route::get('menu', [MenuController::class, 'index']);
        Route::get('floor-plan', [FloorPlanController::class, 'index']);

        // Báo cáo tổng hợp: mọi vai trò xem được doanh thu; lãi gộp và giá vốn
        // bị Resource cắt khỏi JSON nếu không có quyền `view-cost-profit`.
        Route::get('reports/summary', [ReportController::class, 'summary']);

        Route::prefix('table-sessions')->group(function (): void {
            Route::post('/', [TableSessionController::class, 'open'])->middleware('idempotent');
            Route::get('{tableSession}', [TableSessionController::class, 'show']);
            Route::post('{tableSession}/tables', [TableSessionController::class, 'attachTable'])->middleware('idempotent');
            Route::delete('{tableSession}/tables/{diningTable}', [TableSessionController::class, 'detachTable']);
            Route::post('{tableSession}/transfer', [TableSessionController::class, 'transfer'])->middleware('idempotent');
            Route::post('{tableSession}/split', [TableSessionController::class, 'split'])->middleware('idempotent');
            Route::post('{tableSession}/move-items', [TableSessionController::class, 'moveItems'])->middleware('idempotent');
            Route::post('{tableSession}/close', [TableSessionController::class, 'close'])->middleware('idempotent');
            Route::post('{tableSession}/void', [TableSessionController::class, 'void'])->middleware('idempotent');
            Route::post('{tableSession}/orders', [OrderController::class, 'store'])->middleware('idempotent');
            Route::get('{tableSession}/bill', [BillController::class, 'show']);
            Route::get('{tableSession}/vietqr', [VietQrController::class, 'show']);
            Route::post('{tableSession}/discount', [TableSessionController::class, 'discount'])->middleware('idempotent');
            Route::post('{tableSession}/payments', [PaymentController::class, 'store'])->middleware('idempotent');
        });

        Route::prefix('payments')->group(function (): void {
            Route::post('{payment}/void', [PaymentController::class, 'void'])->middleware('idempotent');
        });

        Route::prefix('purchases')->group(function (): void {
            Route::get('/', [PurchaseController::class, 'index']);
            Route::post('/', [PurchaseController::class, 'store'])->middleware('idempotent');
            Route::get('{purchase}', [PurchaseController::class, 'show']);
            Route::patch('{purchase}', [PurchaseController::class, 'update'])->middleware('idempotent');
            Route::post('{purchase}/receive', [PurchaseController::class, 'receive'])->middleware('idempotent');
            Route::post('{purchase}/cancel', [PurchaseController::class, 'cancel'])->middleware('idempotent');
        });

        Route::prefix('orders')->group(function (): void {
            Route::patch('{order}/items/{orderItem}', [OrderItemController::class, 'update'])->middleware('idempotent');
            Route::delete('{order}/items/{orderItem}', [OrderItemController::class, 'destroy']);
            Route::post('{order}/items/{orderItem}/cancel', [OrderItemController::class, 'cancel'])->middleware('idempotent');
            Route::post('{order}/send', [OrderController::class, 'send'])->middleware('idempotent');
        });

        Route::prefix('kds')->group(function (): void {
            Route::get('tickets', [KdsController::class, 'tickets']);
            Route::post('items/{orderItem}/status', [KdsController::class, 'updateItemStatus'])->middleware('idempotent');
        });

        // Không dùng middleware 'idempotent' (header Idempotency-Key) — gói này
        // tự chống trùng theo op_uuid của TỪNG thao tác bên trong, xem SyncBatch.
        Route::prefix('sync')->group(function (): void {
            Route::post('batch', [SyncBatchController::class, 'store']);
            Route::get('conflicts', [SyncConflictController::class, 'index']);
            Route::get('conflicts/pending-count', [SyncConflictController::class, 'pendingCount']);
            Route::post('conflicts/{conflict}/resolve', [SyncConflictController::class, 'resolve'])->middleware('idempotent');
        });
    });
});
