<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::prefix('auth')->group(function (): void {
        Route::post('login', [AuthController::class, 'login']);

        Route::middleware(['auth:sanctum', 'active'])->group(function (): void {
            Route::post('logout', [AuthController::class, 'logout']);

            // Chống dò PIN bằng cách thử vét cạn: tối đa 5 lần/phút và 20 lần/giờ theo user gọi.
            Route::post('pin-verify', [AuthController::class, 'pinVerify'])
                ->middleware(['throttle:5,1,pin-verify-minute', 'throttle:20,60,pin-verify-hour']);
        });
    });
});
