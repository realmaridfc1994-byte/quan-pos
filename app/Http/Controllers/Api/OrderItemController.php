<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Ordering\Actions\CancelOrderItem;
use App\Domain\Ordering\Actions\RemoveOrderItem;
use App\Domain\Ordering\Actions\UpdateOrderItem;
use App\Domain\Ordering\DTO\CancelOrderItemData;
use App\Domain\Ordering\DTO\RemoveOrderItemData;
use App\Domain\Ordering\DTO\UpdateOrderItemData;
use App\Domain\Ordering\Models\Order;
use App\Domain\Ordering\Models\OrderItem;
use App\Http\Controllers\Controller;
use App\Http\Requests\CancelOrderItemRequest;
use App\Http\Requests\RemoveOrderItemRequest;
use App\Http\Requests\UpdateOrderItemRequest;
use App\Http\Resources\OrderItemResource;
use Illuminate\Http\JsonResponse;

final class OrderItemController extends Controller
{
    /** PATCH /api/v1/orders/{order}/items/{orderItem} */
    public function update(UpdateOrderItemRequest $request, Order $order, OrderItem $orderItem, UpdateOrderItem $action): JsonResponse
    {
        $action->handle(UpdateOrderItemData::fromRequest($request));

        return response()->json([
            'data' => OrderItemResource::make($orderItem->refresh()->load('options')),
        ]);
    }

    /** DELETE /api/v1/orders/{order}/items/{orderItem} */
    public function destroy(RemoveOrderItemRequest $request, Order $order, OrderItem $orderItem, RemoveOrderItem $action): JsonResponse
    {
        $action->handle(RemoveOrderItemData::fromRequest($request));

        return response()->json([
            'data' => OrderItemResource::make($orderItem->refresh()->load('options')),
        ]);
    }

    /** POST /api/v1/orders/{order}/items/{orderItem}/cancel */
    public function cancel(CancelOrderItemRequest $request, Order $order, OrderItem $orderItem, CancelOrderItem $action): JsonResponse
    {
        $dongDaHuy = $action->handle(CancelOrderItemData::fromRequest($request));

        return response()->json([
            'data' => OrderItemResource::make($dongDaHuy->load('options')),
        ]);
    }
}
