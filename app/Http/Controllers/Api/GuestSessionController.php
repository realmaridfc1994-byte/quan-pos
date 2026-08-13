<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Ordering\Models\DiningTable;
use App\Domain\Ordering\Queries\IssueGuestSessionToken;
use App\Domain\Ordering\Support\GuestSessionToken;
use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureGuestSessionToken;
use App\Http\Requests\StoreGuestSessionRequest;
use App\Http\Resources\GuestSessionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Kênh CÔNG KHAI của khách tự gọi món — Phase 4, lượt 1: chỉ cơ chế token.
 *
 * Chưa có đường gọi món nào ở đây (lượt 2), và SẼ KHÔNG BAO GIỜ có đường
 * thanh toán — quyết định kiến trúc đã chốt: bề mặt tấn công quá lớn, tiền
 * chỉ đi qua tay thu ngân.
 */
final class GuestSessionController extends Controller
{
    /**
     * POST /api/v1/guest/sessions — khách quét mã QR trên bàn.
     *
     * Bàn chưa có khách → GuestTableNotOpenException → HTTP 409 kèm mã
     * `TABLE_NOT_OPEN` để client hiện màn hình "Bấm gọi phục vụ".
     */
    public function store(StoreGuestSessionRequest $request, IssueGuestSessionToken $query): JsonResponse
    {
        $duLieu = $query->handle($request->string('ma_ban')->toString());

        return GuestSessionResource::make($duLieu)->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * GET /api/v1/guest/session — token còn sống không, bàn nào.
     *
     * Lượt khách đã do middleware tìm và kiểm; Controller KHÔNG tự đọc id từ
     * token.
     */
    public function show(Request $request, IssueGuestSessionToken $query): JsonResponse
    {
        /** @var GuestSessionToken $noiDung */
        $noiDung = $request->attributes->get(EnsureGuestSessionToken::KHOA_TOKEN);

        $ban = DiningTable::query()->findOrFail($noiDung->diningTableId);

        return GuestSessionResource::make(
            $query->xemLai((string) $request->header('X-Guest-Token'), $ban, $noiDung->hetHanLuc)
        )->response();
    }
}
