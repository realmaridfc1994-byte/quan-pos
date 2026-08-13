<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Hình dạng JSON DUY NHẤT trả ra kênh công khai của khách — Phase 4.
 *
 * ── DANH SÁCH TRẮNG, KHÔNG PHẢI DANH SÁCH ĐEN ─────────────────────────────
 * Mảng dưới đây liệt kê ĐÚNG những trường được phép ra ngoài. Không có
 * `toArray()` của Model ở bất kỳ đâu trong file này, không có `...$mang`,
 * không có vòng lặp nào chép trường tự động.
 *
 * Vì sao không dùng danh sách đen ("bỏ giá vốn, bỏ has_cost..."): danh sách
 * đen chỉ chặn được những cái người viết NHỚ RA lúc viết. Thêm một cột mới
 * vào bảng ba tháng sau là nó lặng lẽ ra ngoài. Danh sách trắng thì cột mới
 * mặc định KHÔNG ra, ai muốn cho ra phải sửa file này — một quyết định có ý
 * thức, nhìn thấy được trong lịch sử code.
 *
 * `tests/Feature/Guest/DanhSachTrangTest.php` khẳng định danh sách khoá trả
 * ra BẰNG ĐÚNG danh sách mong đợi, nên thêm trường là test đỏ ngay.
 *
 * TUYỆT ĐỐI KHÔNG có ở đây: id của bất cứ thứ gì, số tiền, giá vốn,
 * has_cost, trạng thái nội bộ, tên nhân viên, mã ca.
 */
final class GuestSessionResource extends JsonResource
{
    /**
     * @param  array{token: ?string, ma_doi_chieu: string, ten_ban: string, khu_vuc: ?string, het_han_luc: string, con_lai_giay: int}  $resource
     */
    public function __construct($resource)
    {
        parent::__construct($resource);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $d */
        $d = $this->resource;

        $ra = [
            'ma_doi_chieu' => $d['ma_doi_chieu'],
            'ten_ban' => $d['ten_ban'],
            'khu_vuc' => $d['khu_vuc'],
            'het_han_luc' => $d['het_han_luc'],
            'con_lai_giay' => $d['con_lai_giay'],
        ];

        // Chuỗi token chỉ trả về ĐÚNG MỘT LẦN, lúc vừa cấp. Các lần gọi sau
        // client tự giữ lấy; server không phát lại, để token không bị đọc ra
        // từ một màn hình mở sẵn trên bàn.
        if ($d['token'] !== null) {
            $ra = ['token' => $d['token']] + $ra;
        }

        return $ra;
    }
}
