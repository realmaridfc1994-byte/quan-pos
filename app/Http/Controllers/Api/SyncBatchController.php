<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Sync\Actions\SyncBatch;
use App\Domain\Sync\DTO\SyncBatchData;
use App\Http\Controllers\Controller;
use App\Http\Requests\SyncBatchRequest;
use Illuminate\Http\JsonResponse;

final class SyncBatchController extends Controller
{
    /** POST /api/v1/sync/batch — docs/thiet-ke-dong-bo.md mục 3. */
    public function store(SyncBatchRequest $request, SyncBatch $action): JsonResponse
    {
        $data = SyncBatchData::fromRequest($request);
        $results = $action->handle($data);

        $summary = ['applied' => 0, 'duplicate' => 0, 'conflict' => 0, 'deferred' => 0, 'rejected' => 0];
        foreach ($results as $result) {
            $summary[$result['status']]++;
        }

        return response()->json([
            'batch_uuid' => $data->batchUuid,
            'server_time' => now()->toIso8601String(),
            'summary' => $summary,
            'results' => $results,
        ]);
    }
}
