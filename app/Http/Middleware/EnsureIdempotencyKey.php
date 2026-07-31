<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\IdempotencyConflictException;
use App\Exceptions\IdempotencyKeyRequiredException;
use App\Exceptions\IdempotencyPayloadMismatchException;
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
    /** Khoá "đang xử lý" tự hết hạn nhanh — tiến trình chết đột ngột (timeout, mất kết nối) không kẹt khoá 24 giờ. */
    private const PROCESSING_TTL_SECONDS = 60;

    private const COMPLETED_TTL_HOURS = 24;

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
        $bodyHash = hash('sha256', (string) $request->getContent());

        $claimed = $store->add(
            $cacheKey,
            ['status' => 'processing', 'body_hash' => $bodyHash],
            now()->addSeconds(self::PROCESSING_TTL_SECONDS)
        );

        if (! $claimed) {
            /** @var array{status: string, body_hash?: string, http_status?: int, headers?: array<string, string>, body?: string}|null $existing */
            $existing = $store->get($cacheKey);

            // Cùng mã nhưng nội dung khác lần trước — không được âm thầm thay thế
            // hay bỏ qua request, đặc biệt nguy hiểm với endpoint thu tiền.
            if ($existing !== null && ($existing['body_hash'] ?? null) !== $bodyHash) {
                throw new IdempotencyPayloadMismatchException(
                    'Mã chống trùng này đã dùng cho một yêu cầu khác. Vui lòng thử lại.'
                );
            }

            if ($existing === null || $existing['status'] === 'processing') {
                throw new IdempotencyConflictException('Yêu cầu trước đó với cùng mã đang được xử lý.');
            }

            $replay = response($existing['body'] ?? '', $existing['http_status'] ?? 200);
            foreach ($existing['headers'] ?? [] as $name => $value) {
                $replay->headers->set($name, $value);
            }

            return $replay;
        }

        try {
            $response = $next($request);
        } catch (\Throwable $e) {
            // Bất kỳ lỗi nào bay ra (validation, domain, lỗi hệ thống...) đều coi như
            // "chưa hoàn tất" — nhả khoá ngay để gửi lại cùng key được, không kẹt 24 giờ.
            $store->forget($cacheKey);

            throw $e;
        }

        if ($response->isSuccessful()) {
            $store->put($cacheKey, [
                'status' => 'completed',
                'body_hash' => $bodyHash,
                'http_status' => $response->getStatusCode(),
                'headers' => [
                    'Content-Type' => $response->headers->get('Content-Type', 'application/json'),
                ],
                'body' => $response->getContent(),
            ], now()->addHours(self::COMPLETED_TTL_HOURS));
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
