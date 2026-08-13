<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Queries;

use App\Domain\Ordering\Enums\TableSessionStatus;
use App\Domain\Ordering\Models\DiningTable;
use App\Domain\Ordering\Support\GuestSessionToken;
use App\Exceptions\GuestTableNotOpenException;
use Illuminate\Support\Carbon;

/**
 * Đổi mã bàn công khai lấy token phiên ngắn hạn — Phase 4.
 *
 * Đặt trong `Queries/` chứ không phải `Actions/` vì nó KHÔNG THAY ĐỔI MỘT
 * DÒNG DỮ LIỆU NÀO: chỉ đọc bàn, đọc lượt khách đang mở, rồi dựng một chuỗi
 * token trong bộ nhớ. Không transaction, không khoá dòng nào (CLAUDE.md luật
 * 11 không đụng tới đường này).
 *
 * ── CHỐT CHẶN THẬT NẰM Ở ĐÂY ──────────────────────────────────────────────
 * Mã QR dán trên bàn là công khai, ai chụp cũng có. Thứ chặn kẻ ngồi nhà gọi
 * món là điều kiện dưới đây: bàn phải ĐANG CÓ MỘT LƯỢT KHÁCH MỞ, tức là có
 * người thật đã ngồi xuống và nhân viên đã mở bàn. Quán đóng cửa thì không
 * bàn nào mở, không mã QR nào đổi được token.
 *
 * Chỉ nhận `open`. Bàn đã bấm in tạm tính (`billing`) là khách đòi tính tiền
 * — xem ghi chú ở EnsureGuestSessionToken.
 */
final class IssueGuestSessionToken
{
    /**
     * @return array{token: string, ma_doi_chieu: string, ten_ban: string, khu_vuc: ?string, het_han_luc: string, con_lai_giay: int}
     *
     * @throws GuestTableNotOpenException
     */
    public function handle(string $maBanCongKhai): array
    {
        $ban = DiningTable::query()
            ->where('public_code', $maBanCongKhai)
            ->where('is_active', true)
            ->first();

        if ($ban === null) {
            throw new GuestTableNotOpenException;
        }

        $session = $ban->tableSessionTables()
            ->whereNull('detached_at')
            ->whereHas('tableSession', fn ($q) => $q->where('status', TableSessionStatus::Open))
            ->with('tableSession:id')
            ->first()?->tableSession;

        if ($session === null) {
            throw new GuestTableNotOpenException;
        }

        $token = GuestSessionToken::phatHanh($session->id, $ban->id);
        $noiDung = GuestSessionToken::doc($token);

        return [
            'token' => $token,
            'ma_doi_chieu' => GuestSessionToken::maDoiChieu($token),
            'ten_ban' => $ban->name,
            'khu_vuc' => $ban->area,
            'het_han_luc' => $noiDung->hetHanLuc->toIso8601String(),
            'con_lai_giay' => $noiDung->conLaiGiay(),
        ];
    }

    /**
     * Thông tin phiên hiện tại cho client kiểm token còn sống không. KHÔNG
     * phát lại chuỗi token.
     *
     * @return array{token: null, ma_doi_chieu: string, ten_ban: string, khu_vuc: ?string, het_han_luc: string, con_lai_giay: int}
     */
    public function xemLai(string $token, DiningTable $ban, Carbon $hetHanLuc): array
    {
        return [
            'token' => null,
            'ma_doi_chieu' => GuestSessionToken::maDoiChieu($token),
            'ten_ban' => $ban->name,
            'khu_vuc' => $ban->area,
            'het_han_luc' => $hetHanLuc->toIso8601String(),
            'con_lai_giay' => max(0, $hetHanLuc->getTimestamp() - Carbon::now()->getTimestamp()),
        ];
    }
}
