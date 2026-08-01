<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Catalog\Enums\Station;
use App\Domain\Ordering\Actions\UpdateOrderItemStatus;
use App\Domain\Ordering\DTO\UpdateOrderItemStatusData;
use App\Domain\Ordering\Models\OrderItem;
use App\Domain\Ordering\Queries\GetKdsTickets;
use App\Http\Controllers\Controller;
use App\Http\Requests\KdsTicketsRequest;
use App\Http\Requests\UpdateOrderItemStatusRequest;
use App\Http\Resources\KdsTicketItemResource;
use App\Http\Resources\KdsTicketResource;
use Illuminate\Http\JsonResponse;

final class KdsController extends Controller
{
    /** GET /api/v1/kds/tickets?station=kitchen|bar */
    public function tickets(KdsTicketsRequest $request, GetKdsTickets $query): JsonResponse
    {
        $station = Station::from($request->string('station')->toString());

        return response()->json([
            'data' => KdsTicketResource::collection($query->handle($station)),
        ]);
    }

    /** POST /api/v1/kds/items/{orderItem}/status */
    public function updateItemStatus(UpdateOrderItemStatusRequest $request, OrderItem $orderItem, UpdateOrderItemStatus $action): JsonResponse
    {
        $action->handle(UpdateOrderItemStatusData::fromRequest($request));

        return response()->json([
            'data' => KdsTicketItemResource::make($orderItem->refresh()),
        ]);
    }
}
