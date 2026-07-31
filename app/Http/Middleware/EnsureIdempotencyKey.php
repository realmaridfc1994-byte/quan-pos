<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\IdempotencyConflictException;
use App\Exceptions\IdempotencyKeyRequiredException;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Chống thu trùng khi máy POS bấm gửi hai lần vì mạng lag (hoặc khách bấm hai lần).
 *
 * Chỉ áp dụng cho POST/PATCH. Client phải tự sinh header Idempotency-Key trước khi
 * gửi; gửi lại đúng key đó trong 24 giờ thì:
 *  - Nếu lần trước đã xử lý xong THÀNH CÔNG (2xx): trả lại nguyên response cũ,
 *    không chạy lại logic — không tạo bản ghi thứ hai.
 *  - Nếu lần trước đang xử lý dở: trả 409, không đợi, không chạy song song.
 *  - Nếu lần trước LỖI (4xx/5xx): coi như "chưa hoàn tất", nhả khoá ngay để
 *    khách sửa dữ liệu rồi gửi lại được với cùng key.
 */
final class EnsureIdempotencyKey
{
    private const TTL_HOURS = 24;

    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->method(), ['POST', 'PATCH'], true)) {
            return $next($request);
        }

        $idempotencyKey = $request->header('Idempotency-Key');

        if (! is_string($idempotencyKey) || $idempotencyKey === '') {
            throw new IdempotencyKeyRequiredException('Thiếu header Idempotency-Key.');
        }

        $cacheKey = $this->cacheKeyFor($idempotencyKey, $request);
        $store = Cache::store('database');

        $claimed = $store->add($cacheKey, ['status' => 'processing'], now()->addHours(self::TTL_HOURS));

        if (! $claimed) {
            /** @var array{status: string, http_status?: int, headers?: array<string, string>, body?: string}|null $existing */
            $existing = $store->get($cacheKey);

            if ($existing === null || $existing['status'] === 'processing') {
                throw new IdempotencyConflictException('Yêu cầu trước đó với cùng mã đang được xử lý.');
            }

            $replay = response($existing['body'] ?? '', $existing['http_status'] ?? 200);
            foreach ($existing['headers'] ?? [] as $name => $value) {
                $replay->headers->set($name, $value);
            }

            return $replay;
        }

        $response = $next($request);

        if ($response->isSuccessful()) {
            $store->put($cacheKey, [
                'status' => 'completed',
                'http_status' => $response->getStatusCode(),
                'headers' => [
                    'Content-Type' => $response->headers->get('Content-Type', 'application/json'),
                ],
                'body' => $response->getContent(),
            ], now()->addHours(self::TTL_HOURS));
        } else {
            $store->forget($cacheKey);
        }

        return $response;
    }

    private function cacheKeyFor(string $idempotencyKey, Request $request): string
    {
        $user = $request->user();
        $userId = $user !== null ? $user->id : 'guest';

        return 'idem:'.hash('sha256', $idempotencyKey.'|'.$userId.'|'.$request->method().'|'.$request->path());
    }
}
