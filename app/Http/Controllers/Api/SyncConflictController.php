<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Sync\Actions\ResolveSyncConflict;
use App\Domain\Sync\DTO\ResolveSyncConflictData;
use App\Domain\Sync\Enums\ConflictStatus;
use App\Domain\Sync\Models\SyncConflict;
use App\Http\Controllers\Controller;
use App\Http\Requests\ResolveSyncConflictRequest;
use App\Http\Resources\SyncConflictResource as SyncConflictJsonResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Phase 2 Bước 5 (sửa 04/08): xử lý xung đột đồng bộ đi qua ĐÚNG hệ đăng
 * nhập của máy POS (auth:sanctum) — không bắt thu ngân rời màn hình bán hàng
 * sang panel Filament, vì tối đông khách không ai bấm vào lối đó.
 */
final class SyncConflictController extends Controller
{
    /**
     * GET /api/v1/sync/conflicts?status=pending
     *
     * Mọi vai trò đã đăng nhập đều XEM được (staff/kitchen chỉ xem, không
     * quyết — quyền QUYẾT chặn riêng ở resolve() qua ResolveSyncConflictRequest).
     */
    public function index(Request $request): JsonResponse
    {
        $trangThai = $request->query('status');

        $query = SyncConflict::query()->orderByDesc('is_urgent')->orderBy('created_at');

        if ($trangThai !== null) {
            $query->where('status', ConflictStatus::from($trangThai));
        }

        return response()->json([
            'data' => SyncConflictJsonResource::collection($query->limit(100)->get()),
        ]);
    }

    /**
     * POST /api/v1/sync/conflicts/{conflict}/resolve
     */
    public function resolve(ResolveSyncConflictRequest $request, SyncConflict $conflict, ResolveSyncConflict $action): JsonResponse
    {
        $ketQua = $action->handle(ResolveSyncConflictData::fromRequest($request));

        return SyncConflictJsonResource::make($ketQua)->response();
    }

    /**
     * GET /api/v1/sync/conflicts/pending-count
     *
     * Cho máy POS hỏi định kỳ để hiện chấm đỏ — chỉ đếm, không trả nội dung
     * xung đột (nội dung đầy đủ lấy qua index() khi người bấm vào chấm đỏ).
     */
    public function pendingCount(): JsonResponse
    {
        $dangCho = SyncConflict::query()->where('status', ConflictStatus::Pending);

        return response()->json([
            'data' => [
                'pending' => (clone $dangCho)->count(),
                'urgent' => (clone $dangCho)->where('is_urgent', true)->count(),
            ],
        ]);
    }
}
