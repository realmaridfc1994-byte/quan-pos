<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Nhân viên nghỉ việc thì tài khoản bị tắt cờ is_active, nhưng token cũ trên máy vẫn còn.
 * Middleware này chặn ngay, không cho dùng token cũ nữa.
 */
final class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->is_active) {
            return response()->json([
                'message' => 'Tài khoản đã bị vô hiệu hoá.',
            ], 403);
        }

        return $next($request);
    }
}
