<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Billing\Queries\GetSessionBill;
use App\Domain\Ordering\Models\TableSession;
use App\Http\Controllers\Controller;
use App\Http\Resources\BillResource;
use Illuminate\Http\JsonResponse;

final class BillController extends Controller
{
    /** GET /api/v1/table-sessions/{tableSession}/bill */
    public function show(TableSession $tableSession, GetSessionBill $query): JsonResponse
    {
        return response()->json([
            'data' => BillResource::make($query->handle($tableSession->id)),
        ]);
    }
}
