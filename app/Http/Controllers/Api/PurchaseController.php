<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Inventory\Actions\CancelPurchase;
use App\Domain\Inventory\Actions\CreatePurchase;
use App\Domain\Inventory\Actions\ReceivePurchase;
use App\Domain\Inventory\Actions\UpdatePurchase;
use App\Domain\Inventory\DTO\CancelPurchaseData;
use App\Domain\Inventory\DTO\CreatePurchaseData;
use App\Domain\Inventory\DTO\ReceivePurchaseData;
use App\Domain\Inventory\DTO\UpdatePurchaseData;
use App\Domain\Inventory\Enums\PurchaseStatus;
use App\Domain\Inventory\Models\Purchase;
use App\Http\Controllers\Controller;
use App\Http\Requests\CancelPurchaseRequest;
use App\Http\Requests\ReceivePurchaseRequest;
use App\Http\Requests\StorePurchaseRequest;
use App\Http\Requests\UpdatePurchaseRequest;
use App\Http\Resources\PurchaseResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

final class PurchaseController extends Controller
{
    /** GET /api/v1/purchases?status=draft&supplier_id=1 */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Purchase::class);

        $query = Purchase::query()->with('supplier')->latest('id');

        if ($request->filled('status')) {
            $query->where('status', PurchaseStatus::from($request->string('status')->toString()));
        }

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->integer('supplier_id'));
        }

        return response()->json([
            'data' => PurchaseResource::collection($query->limit(100)->get()),
        ]);
    }

    /** GET /api/v1/purchases/{purchase} */
    public function show(Purchase $purchase): JsonResponse
    {
        Gate::authorize('view', $purchase);

        return response()->json([
            'data' => PurchaseResource::make($purchase->load(['supplier', 'items.ingredient', 'createdBy', 'receivedBy'])),
        ]);
    }

    /** POST /api/v1/purchases */
    public function store(StorePurchaseRequest $request, CreatePurchase $action): JsonResponse
    {
        $purchase = $action->handle(CreatePurchaseData::fromRequest($request));

        return PurchaseResource::make($purchase->load(['supplier', 'items.ingredient']))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /** PATCH /api/v1/purchases/{purchase} */
    public function update(UpdatePurchaseRequest $request, Purchase $purchase, UpdatePurchase $action): JsonResponse
    {
        $updated = $action->handle(UpdatePurchaseData::fromRequest($request));

        return PurchaseResource::make($updated->load(['supplier', 'items.ingredient']))->response();
    }

    /** POST /api/v1/purchases/{purchase}/receive */
    public function receive(ReceivePurchaseRequest $request, Purchase $purchase, ReceivePurchase $action): JsonResponse
    {
        $received = $action->handle(ReceivePurchaseData::fromRequest($request));

        return PurchaseResource::make($received->load(['supplier', 'items.ingredient', 'receivedBy']))->response();
    }

    /** POST /api/v1/purchases/{purchase}/cancel */
    public function cancel(CancelPurchaseRequest $request, Purchase $purchase, CancelPurchase $action): JsonResponse
    {
        $cancelled = $action->handle(CancelPurchaseData::fromRequest($request));

        return PurchaseResource::make($cancelled->load('supplier'))->response();
    }
}
