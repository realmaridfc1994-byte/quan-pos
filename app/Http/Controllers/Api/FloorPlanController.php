<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Ordering\Queries\GetFloorPlan;
use App\Http\Controllers\Controller;
use App\Http\Resources\FloorPlanTableResource;
use Illuminate\Http\JsonResponse;

final class FloorPlanController extends Controller
{
    /** GET /api/v1/floor-plan */
    public function index(GetFloorPlan $query): JsonResponse
    {
        return response()->json([
            'data' => FloorPlanTableResource::collection($query->handle()),
        ]);
    }
}
