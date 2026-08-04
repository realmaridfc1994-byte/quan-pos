<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Billing\Queries\GetVietQrForTableSession;
use App\Domain\Ordering\Models\TableSession;
use App\Http\Controllers\Controller;
use App\Http\Resources\VietQrResource;
use Illuminate\Http\JsonResponse;

final class VietQrController extends Controller
{
    /** GET /api/v1/table-sessions/{tableSession}/vietqr */
    public function show(TableSession $tableSession, GetVietQrForTableSession $query): JsonResponse
    {
        return response()->json([
            'data' => VietQrResource::make($query->handle($tableSession)),
        ]);
    }
}
