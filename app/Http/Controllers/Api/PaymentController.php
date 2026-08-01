<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Billing\Actions\RecordPayment;
use App\Domain\Billing\Actions\VoidPayment;
use App\Domain\Billing\DTO\RecordPaymentData;
use App\Domain\Billing\DTO\VoidPaymentData;
use App\Domain\Billing\Models\Payment;
use App\Domain\Ordering\Models\TableSession;
use App\Http\Controllers\Controller;
use App\Http\Requests\RecordPaymentRequest;
use App\Http\Requests\VoidPaymentRequest;
use App\Http\Resources\PaymentResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class PaymentController extends Controller
{
    /** POST /api/v1/table-sessions/{tableSession}/payments */
    public function store(RecordPaymentRequest $request, TableSession $tableSession, RecordPayment $action): JsonResponse
    {
        $payment = $action->handle(RecordPaymentData::fromRequest($request));

        return response()->json([
            'data' => PaymentResource::make($payment->load('receivedBy')),
        ], Response::HTTP_CREATED);
    }

    /** POST /api/v1/payments/{payment}/void */
    public function void(VoidPaymentRequest $request, Payment $payment, VoidPayment $action): JsonResponse
    {
        $action->handle(VoidPaymentData::fromRequest($request));

        return response()->json([
            'data' => PaymentResource::make($payment->refresh()->load(['receivedBy', 'voidedBy'])),
        ]);
    }
}
