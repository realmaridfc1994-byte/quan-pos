<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Reporting\Queries\GetSalesSummaryReport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ShowSalesSummaryRequest;
use App\Http\Resources\SalesSummaryResource;
use Illuminate\Http\JsonResponse;

final class ReportController extends Controller
{
    /**
     * GET /api/v1/reports/summary?tu=YYYY-MM-DD&den=YYYY-MM-DD
     *
     * Ba dòng, đúng chuẩn: khoảng ngày đã kiểm ở ShowSalesSummaryRequest, số
     * liệu lấy ở GetSalesSummaryReport (chỉ đọc bảng tổng hợp), việc giấu lãi
     * gộp khi không có quyền nằm ở SalesSummaryResource.
     */
    public function summary(ShowSalesSummaryRequest $request, GetSalesSummaryReport $query): JsonResponse
    {
        [$tu, $den] = $request->khoangNgay();

        return SalesSummaryResource::make($query->handle($tu, $den))->response();
    }
}
