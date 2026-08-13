<?php

declare(strict_types=1);

namespace App\Domain\Printing\Templates;

use App\Domain\Ordering\Models\DiningTable;
use App\Support\MaBanCongKhai;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * Tem QR dán lên mặt bàn — Phase 4.
 *
 * Xuất SVG chứ không phải PNG: SVG là hình vẽ theo công thức, in ra khổ nào
 * cũng nét. PNG phóng to là vỡ, mà tem dán bàn thì hay phải in to cho người
 * lớn tuổi quét được.
 *
 * SVG cũng KHÔNG cần extension `gd` hay `imagick` của PHP — máy quán chạy
 * XAMPP, càng ít thứ phải bật trong php.ini càng ít thứ hỏng lúc cài.
 *
 * ── TEM CHỈ VẼ LẠI, KHÔNG BAO GIỜ SINH MÃ MỚI ─────────────────────────────
 * Lớp này chỉ ĐỌC `public_code` có sẵn của bàn. Nếu nó tự sinh mã thì mỗi lần
 * in lại là mọi tem đang dán trên bàn thành vô dụng — và không ai biết cho
 * tới khi khách quét không được giữa giờ cao điểm.
 */
final class TemBanQrTemplate
{
    /** Cạnh của riêng ô vuông QR, tính bằng điểm ảnh SVG. */
    private const CANH_QR = 480;

    private const LE = 40;

    private const CAO_CHU = 120;

    public function render(DiningTable $ban): string
    {
        $duongDan = MaBanCongKhai::duongDan($ban->public_code);

        $rong = self::CANH_QR + self::LE * 2;
        $cao = $rong + self::CAO_CHU;

        $qr = $this->veQr($duongDan);
        $tenBan = $this->thoat($ban->name);
        $maBan = $this->thoat($ban->code);
        $link = $this->thoat($duongDan);
        $yChu = self::CANH_QR + self::LE + 60;
        $yPhu = $yChu + 42;

        // Mã QR là HÌNH VẼ, không chứa chữ nào đọc được. Ghi kèm đường link
        // dưới dạng chú thích để mở file bằng trình soạn thảo là đối chiếu
        // được ngay tem này thuộc bàn nào — không phải cầm điện thoại quét
        // từng tờ để kiểm. Hai thẻ này KHÔNG hiện ra khi in.
        return <<<SVG
            <?xml version="1.0" encoding="UTF-8"?>
            <svg xmlns="http://www.w3.org/2000/svg" width="{$rong}" height="{$cao}" viewBox="0 0 {$rong} {$cao}">
                <title>{$tenBan} ({$maBan})</title>
                <desc>{$link}</desc>
                <rect width="{$rong}" height="{$cao}" fill="#ffffff"/>
                <svg x="{$this->le()}" y="{$this->le()}" width="{$this->canhQr()}" height="{$this->canhQr()}">{$qr}</svg>
                <text x="{$this->giua($rong)}" y="{$yChu}" text-anchor="middle"
                      font-family="DejaVu Sans, Arial, sans-serif" font-size="56" font-weight="bold" fill="#111111">{$tenBan}</text>
                <text x="{$this->giua($rong)}" y="{$yPhu}" text-anchor="middle"
                      font-family="DejaVu Sans, Arial, sans-serif" font-size="26" fill="#666666">{$maBan} — quét để xem thực đơn</text>
            </svg>
            SVG;
    }

    /**
     * Bacon trả về một tài liệu SVG hoàn chỉnh (có cả dòng khai báo XML). Bỏ
     * dòng khai báo và lớp `<svg>` ngoài cùng đi, chỉ giữ phần hình bên trong,
     * để lồng được vào tem mà không đẻ ra hai tài liệu XML trong một file.
     */
    private function veQr(string $noiDung): string
    {
        $writer = new Writer(new ImageRenderer(
            new RendererStyle(self::CANH_QR, 1),
            new SvgImageBackEnd,
        ));

        $svg = $writer->writeString($noiDung);

        $svg = (string) preg_replace('/^<\?xml.*?\?>\s*/s', '', $svg);
        $svg = (string) preg_replace('/^<svg[^>]*>/s', '', $svg);

        return (string) preg_replace('#</svg>\s*$#s', '', $svg);
    }

    private function le(): int
    {
        return self::LE;
    }

    private function canhQr(): int
    {
        return self::CANH_QR;
    }

    private function giua(int $rong): int
    {
        return intdiv($rong, 2);
    }

    private function thoat(string $chu): string
    {
        return htmlspecialchars($chu, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
