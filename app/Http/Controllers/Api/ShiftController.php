<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Staffing\Actions\CloseShift;
use App\Domain\Staffing\Actions\OpenShift;
use App\Domain\Staffing\DTO\CloseShiftData;
use App\Domain\Staffing\DTO\OpenShiftData;
use App\Domain\Staffing\Models\Shift;
use App\Domain\Staffing\Queries\GetCurrentShift;
use App\Domain\Staffing\Queries\GetShiftReport;
use App\Http\Controllers\Controller;
use App\Http\Requests\CloseShiftRequest;
use App\Http\Requests\OpenShiftRequest;
use App\Http\Requests\ViewShiftReportRequest;
use App\Http\Resources\ShiftResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class ShiftController extends Controller
{
    /** POST /api/v1/shifts/open */
    public function open(OpenShiftRequest $request, OpenShift $action): JsonResponse
    {
        $shift = $action->handle(OpenShiftData::fromRequest($request));

        return response()->json([
            'data' => ShiftResource::make($shift->load('openedBy')),
        ], Response::HTTP_CREATED);
    }

    /** POST /api/v1/shifts/{shift}/close */
    public function close(CloseShiftRequest $request, Shift $shift, CloseShift $action): JsonResponse
    {
        $data = CloseShiftData::fromRequest($request);
        $shift = $action->handle($data);

        return response()->json([
            'data' => ShiftResource::make($shift->load(['openedBy', 'closedBy'])),
        ]);
    }

    /** GET /api/v1/shifts/current */
    public function current(GetCurrentShift $query): JsonResponse
    {
        $shift = $query->handle();

        return response()->json([
            'data' => $shift !== null ? ShiftResource::make($shift->load('openedBy')) : null,
        ]);
    }

    /** GET /api/v1/shifts/{shift}/report */
    public function report(ViewShiftReportRequest $request, Shift $shift, GetShiftReport $query): JsonResponse
    {
        return response()->json([
            'data' => $query->handle($shift->id),
        ]);
    }
}
