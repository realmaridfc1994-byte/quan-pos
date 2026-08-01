<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Staffing\Actions\RecordCashMovement;
use App\Domain\Staffing\DTO\RecordCashMovementData;
use App\Domain\Staffing\Models\Shift;
use App\Http\Controllers\Controller;
use App\Http\Requests\RecordCashMovementRequest;
use App\Http\Resources\CashMovementResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class CashMovementController extends Controller
{
    /** POST /api/v1/shifts/{shift}/cash-movements */
    public function store(RecordCashMovementRequest $request, Shift $shift, RecordCashMovement $action): JsonResponse
    {
        $movement = $action->handle(RecordCashMovementData::fromRequest($request));

        return response()->json([
            'data' => CashMovementResource::make($movement->load('createdBy')),
        ], Response::HTTP_CREATED);
    }
}
