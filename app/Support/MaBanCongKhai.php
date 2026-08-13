<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Mã định danh bàn in trong mã QR dán trên bàn — Phase 4.
 *
 * ĐÂY LÀ MÃ CÔNG KHAI, KHÔNG PHẢI BÍ MẬT. Nó chỉ trả lời câu "đây là bàn
 * nào", không cấp quyền gì cả. Ai chụp được nó cũng không gọi món được: muốn
 * gọi món phải đổi nó lấy token phiên, và server chỉ cấp token khi bàn đang
 * có khách ngồi thật.
 *
 * Vì sao KHÔNG dùng `dining_tables.code` (B01, VIP1) làm mã QR: đoán được
 * ngay từ bàn thứ nhất. Kẻ rảnh rỗi ngồi nhà thử B01..B20 là dò ra hết bàn
 * của quán và biết bàn nào đang có khách — không gọi món được, nhưng đó là
 * thông tin không việc gì phải cho không.
 *
 * Vì sao 22 ký tự chứ không phải UUID 36 ký tự: mã QR càng ngắn thì ô vuông
 * càng thưa, điện thoại cũ quét trong quán tối vẫn bắt được. 22 ký tự chữ-số
 * là khoảng 131 bit — dò trúng còn khó hơn dò trúng UUID.
 */
final class MaBanCongKhai
{
    public const DO_DAI = 22;

    /** CHỖ DUY NHẤT sinh mã bàn — Factory và Seeder đều gọi vào đây. */
    public static function sinh(): string
    {
        return Str::random(self::DO_DAI);
    }

    /**
     * Đường link in vào mã QR: khách quét bằng camera mặc định là mở thẳng
     * trình duyệt, không phải cài app nào.
     *
     * Đổi `pos.khach_tu_goi.duong_dan_goc` là PHẢI IN LẠI TEM CỦA MỌI BÀN.
     */
    public static function duongDan(string $maCongKhai): string
    {
        $goc = rtrim((string) config('pos.khach_tu_goi.duong_dan_goc'), '/');

        return "{$goc}/g/{$maCongKhai}";
    }
}
