<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Billing\Actions\CalculateBill;
use App\Domain\Billing\DTO\CalculateBillData;
use App\Domain\Ordering\Actions\AttachTable;
use App\Domain\Ordering\Actions\CloseTableSession;
use App\Domain\Ordering\Actions\DetachTable;
use App\Domain\Ordering\Actions\OpenTableSession;
use App\Domain\Ordering\Actions\TransferTable;
use App\Domain\Ordering\Actions\VoidTableSession;
use App\Domain\Ordering\DTO\AttachTableData;
use App\Domain\Ordering\DTO\CloseTableSessionData;
use App\Domain\Ordering\DTO\DetachTableData;
use App\Domain\Ordering\DTO\OpenTableSessionData;
use App\Domain\Ordering\DTO\TransferTableData;
use App\Domain\Ordering\DTO\VoidTableSessionData;
use App\Domain\Ordering\Models\DiningTable;
use App\Domain\Ordering\Models\TableSession;
use App\Domain\Ordering\Queries\GetTableSessionDetail;
use App\Http\Controllers\Controller;
use App\Http\Requests\AttachTableRequest;
use App\Http\Requests\CalculateBillRequest;
use App\Http\Requests\CloseTableSessionRequest;
use App\Http\Requests\DetachTableRequest;
use App\Http\Requests\OpenTableSessionRequest;
use App\Http\Requests\TransferTableRequest;
use App\Http\Requests\VoidTableSessionRequest;
use App\Http\Resources\TableSessionResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class TableSessionController extends Controller
{
    /** GET /api/v1/table-sessions/{tableSession} */
    public function show(TableSession $tableSession, GetTableSessionDetail $query): JsonResponse
    {
        return response()->json([
            'data' => TableSessionResource::make($query->handle($tableSession->id)),
        ]);
    }

    /** POST /api/v1/table-sessions */
    public function open(OpenTableSessionRequest $request, OpenTableSession $action): JsonResponse
    {
        $tableSession = $action->handle(OpenTableSessionData::fromRequest($request));

        return $this->tra($tableSession, Response::HTTP_CREATED);
    }

    /** POST /api/v1/table-sessions/{tableSession}/tables */
    public function attachTable(AttachTableRequest $request, TableSession $tableSession, AttachTable $action): JsonResponse
    {
        $action->handle(AttachTableData::fromRequest($request));

        return $this->tra($tableSession, Response::HTTP_CREATED);
    }

    /** DELETE /api/v1/table-sessions/{tableSession}/tables/{diningTable} */
    public function detachTable(DetachTableRequest $request, TableSession $tableSession, DiningTable $diningTable, DetachTable $action): JsonResponse
    {
        $action->handle(DetachTableData::fromRequest($request));

        return $this->tra($tableSession);
    }

    /** POST /api/v1/table-sessions/{tableSession}/transfer */
    public function transfer(TransferTableRequest $request, TableSession $tableSession, TransferTable $action): JsonResponse
    {
        $action->handle(TransferTableData::fromRequest($request));

        return $this->tra($tableSession);
    }

    /** POST /api/v1/table-sessions/{tableSession}/close */
    public function close(CloseTableSessionRequest $request, TableSession $tableSession, CloseTableSession $action): JsonResponse
    {
        $action->handle(CloseTableSessionData::fromRequest($request));

        return $this->tra($tableSession);
    }

    /** POST /api/v1/table-sessions/{tableSession}/void */
    public function void(VoidTableSessionRequest $request, TableSession $tableSession, VoidTableSession $action): JsonResponse
    {
        $action->handle(VoidTableSessionData::fromRequest($request));

        return $this->tra($tableSession);
    }

    /** POST /api/v1/table-sessions/{tableSession}/discount */
    public function discount(CalculateBillRequest $request, TableSession $tableSession, CalculateBill $action): JsonResponse
    {
        $action->handle(CalculateBillData::fromRequest($request));

        return $this->tra($tableSession);
    }

    private function tra(TableSession $tableSession, int $status = Response::HTTP_OK): JsonResponse
    {
        return response()->json([
            'data' => TableSessionResource::make($tableSession->fresh()->load(['openedBy', 'tables.diningTable'])),
        ], $status);
    }
}
