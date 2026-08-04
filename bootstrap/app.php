<?php

use App\Exceptions\DomainException;
use App\Exceptions\IdempotencyConflictException;
use App\Exceptions\IdempotencyKeyRequiredException;
use App\Exceptions\IdempotencyPayloadMismatchException;
use App\Exceptions\SyncBatchLockedException;
use App\Http\Middleware\EnsureIdempotencyKey;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'active' => EnsureUserIsActive::class,
            'idempotent' => EnsureIdempotencyKey::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Mọi lỗi trả về API đều có cùng hình dạng { message, errors, code }, viết bằng tiếng Việt.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $mapped = match (true) {
                $e instanceof ValidationException => [422, 'VALIDATION_ERROR', 'Dữ liệu gửi lên không hợp lệ.', $e->errors()],
                $e instanceof DomainException => [422, 'DOMAIN_ERROR', $e->getMessage(), null],
                $e instanceof IdempotencyKeyRequiredException => [400, 'IDEMPOTENCY_KEY_REQUIRED', $e->getMessage(), null],
                $e instanceof IdempotencyConflictException => [409, 'IDEMPOTENCY_CONFLICT', $e->getMessage(), null],
                $e instanceof IdempotencyPayloadMismatchException => [422, 'IDEMPOTENCY_PAYLOAD_MISMATCH', $e->getMessage(), null],
                $e instanceof AuthenticationException => [401, 'UNAUTHENTICATED', 'Chưa đăng nhập hoặc phiên đã hết hạn.', null],
                $e instanceof AuthorizationException => [403, 'FORBIDDEN', 'Bạn không có quyền thực hiện thao tác này.', null],
                $e instanceof ThrottleRequestsException => [429, 'TOO_MANY_ATTEMPTS', 'Thử sai quá nhiều lần. Vui lòng đợi ít phút rồi thử lại.', null],
                $e instanceof SyncBatchLockedException => [429, 'SYNC_BATCH_LOCKED', $e->getMessage(), null],
                $e instanceof ModelNotFoundException, $e instanceof NotFoundHttpException => [404, 'NOT_FOUND', 'Không tìm thấy dữ liệu.', null],
                app()->environment('production') => [500, 'SERVER_ERROR', 'Có lỗi xảy ra ở máy chủ. Vui lòng thử lại.', null],
                default => null,
            };

            if ($mapped === null) {
                return null;
            }

            [$status, $code, $message, $errors] = $mapped;

            return response()->json([
                'message' => $message,
                'errors' => $errors,
                'code' => $code,
            ], $status);
        });
    })->create();
