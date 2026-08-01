<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Ordering\Actions\PlaceOrder;
use App\Domain\Ordering\Actions\SendToKitchen;
use App\Domain\Ordering\DTO\PlaceOrderData;
use App\Domain\Ordering\DTO\SendToKitchenData;
use App\Domain\Ordering\Models\Order;
use App\Domain\Ordering\Models\TableSession;
use App\Http\Controllers\Controller;
use App\Http\Requests\PlaceOrderRequest;
use App\Http\Requests\SendToKitchenRequest;
use App\Http\Resources\OrderResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class OrderController extends Controller
{
    /** POST /api/v1/table-sessions/{tableSession}/orders */
    public function store(PlaceOrderRequest $request, TableSession $tableSession, PlaceOrder $action): JsonResponse
    {
        $order = $action->handle(PlaceOrderData::fromRequest($request));

        return response()->json([
            'data' => OrderResource::make($order->load(['createdBy', 'items.options'])),
        ], Response::HTTP_CREATED);
    }

    /** POST /api/v1/orders/{order}/send */
    public function send(SendToKitchenRequest $request, Order $order, SendToKitchen $action): JsonResponse
    {
        $order = $action->handle(SendToKitchenData::fromRequest($request));

        return response()->json([
            'data' => OrderResource::make($order->load(['createdBy', 'items.options'])),
        ]);
    }
}
