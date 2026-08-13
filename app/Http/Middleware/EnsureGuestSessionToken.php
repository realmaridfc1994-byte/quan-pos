<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Ordering\Enums\TableSessionStatus;
use App\Domain\Ordering\Models\TableSession;
use App\Domain\Ordering\Support\GuestSessionToken;
use App\Exceptions\GuestTokenException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cửa gác cho nhóm đường đi công khai của khách tự gọi món — Phase 4.
 *
 * Token đi trong header `X-Guest-Token`, CỐ Ý không dùng `Authorization:
 * Bearer` — chỗ đó đang là của token nhân viên (Sanctum). Hai loại quyền
 * khác hẳn nhau thì để hai cửa khác nhau, để không bao giờ có chuyện một
 * token khách lọt vào đường của nhân viên vì ai đó viết nhầm middleware.
 *
 * Kiểm ba việc, theo đúng thứ tự này:
 *   1. Token có đọc được và còn hạn giờ không (GuestSessionToken::doc)
 *   2. Lượt khách đó CÒN ĐANG MỞ không — đây là chỗ "bàn đóng thì token chết
 *      ngay". Token không tự biết chuyện xảy ra sau khi nó được cấp, nên
 *      trạng thái phải đọc lại từ database MỖI LẦN gọi, không cache.
 *   3. Bàn ghi trong token còn thuộc lượt khách đó không — chống việc cầm
 *      token của bàn A đi thao tác lên bàn B sau khi thu ngân dời bàn.
 *
 * Chỉ nhận trạng thái `open`. Bàn đã bấm in tạm tính (`billing`) nghĩa là
 * khách đòi tính tiền — gọi thêm lúc đó dễ thành cãi nhau về con số trên tờ
 * giấy đã in. Muốn gọi thêm thì kêu phục vụ (chốt 14/08).
 *
 * Lượt khách đã tìm được gắn vào request để Controller dùng, KHÔNG bao giờ
 * để Controller tự đọc id từ token.
 */
final class EnsureGuestSessionToken
{
    public const KHOA_REQUEST = 'guest_table_session';

    public const KHOA_TOKEN = 'guest_token';

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->header('X-Guest-Token');

        if (! is_string($token) || $token === '') {
            throw GuestTokenException::hong();
        }

        $noiDung = GuestSessionToken::doc($token);

        $session = TableSession::query()->find($noiDung->tableSessionId);

        if ($session === null || $session->status !== TableSessionStatus::Open) {
            throw GuestTokenException::banDaDong();
        }

        $banConThuocPhien = $session->tables()
            ->where('dining_table_id', $noiDung->diningTableId)
            ->whereNull('detached_at')
            ->exists();

        if (! $banConThuocPhien) {
            throw GuestTokenException::banDaDong();
        }

        $request->attributes->set(self::KHOA_REQUEST, $session);
        $request->attributes->set(self::KHOA_TOKEN, $noiDung);

        return $next($request);
    }
}
