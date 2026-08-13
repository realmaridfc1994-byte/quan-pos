<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * Hình dạng JSON của báo cáo tổng hợp — Phase 4 Bước 4B.1.
 *
 * ── GIẤU LÃI GỘP VÀ GIÁ VỐN KHI KHÔNG CÓ QUYỀN ────────────────────────────
 * Không có quyền `view-cost-profit` (chỉ chủ quán) thì hai khối `lai_gop` và
 * `chat_luong_du_lieu` BIẾN MẤT KHỎI JSON — không phải trả `null`, không phải
 * trả `0`. Trả null/0 là nói dối: người đọc sẽ tưởng quán hoà vốn hoặc lỗ,
 * trong khi sự thật là "anh không được xem".
 *
 * Giá vốn là bí mật kinh doanh: thu ngân cần biết doanh thu để đối soát két,
 * không cần biết quán lãi bao nhiêu một lon bia (CLAUDE.md mục 1).
 *
 * @mixin Collection
 */
final class SalesSummaryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $duLieu */
        $duLieu = $this->resource;

        $xemDuocGiaVon = $request->user() !== null
            && Gate::forUser($request->user())->allows('view-cost-profit');

        return [
            'ky' => $duLieu['ky'],
            'doanh_thu' => $duLieu['doanh_thu'],
            'theo_ngay' => $duLieu['theo_ngay'],
            'top_mon_ban_chay' => $duLieu['top_mon_ban_chay'],

            // when() BỎ HẲN khoá khỏi mảng khi điều kiện sai — đúng thứ cần ở
            // đây, khác hẳn việc gán null.
            'chat_luong_du_lieu' => $this->when($xemDuocGiaVon, fn () => $duLieu['chat_luong_du_lieu']),
            'lai_gop' => $this->when($xemDuocGiaVon, fn () => $duLieu['lai_gop']),
        ];
    }
}
