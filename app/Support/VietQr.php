<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Dựng chuỗi mã QR chuyển khoản theo chuẩn VietQR (NAPAS 247), dựa trên khung
 * EMVCo QR Code Specification for Payment Systems — Merchant Presented Mode.
 *
 * CHỈ dựng CHUỖI VĂN BẢN. Việc vẽ ra hình ảnh mã QR nằm ở trình duyệt (thư
 * viện `qrcode` phía client, xem resources/js/pos.js) — file này không vẽ
 * ảnh, không gọi mạng, không phụ thuộc gì ngoài PHP thuần.
 *
 * Cấu trúc TLV (Tag-Length-Value), mỗi trường: 2 ký tự mã số + 2 ký tự độ dài
 * (số byte của phần giá trị) + giá trị. Trường 63 (CRC) luôn là 4 ký tự cuối,
 * tính trên toàn bộ chuỗi TRƯỚC NÓ (kể cả tiêu đề "6304" của chính nó).
 */
final readonly class VietQr
{
    private const GUID_NAPAS = 'A000000727';

    private const MA_TIEN_TE_VND = '704';

    private const MA_QUOC_GIA = 'VN';

    /** Chuyển khoản tới SỐ TÀI KHOẢN (khác QRIBFTTC — chuyển tới số thẻ). */
    private const MA_DICH_VU_CHUYEN_KHOAN = 'QRIBFTTA';

    private function __construct() {}

    /**
     * @param  string  $bankBin  Mã ngân hàng (BIN) theo danh sách NAPAS, ví dụ Vietcombank = 970436
     * @param  string  $accountNumber  Số tài khoản nhận tiền
     * @param  string  $accountName  Tên chủ tài khoản — tự chuyển về chữ hoa không dấu cho máy quét dễ đọc
     * @param  int  $amountVnd  Số tiền cần chuyển, đơn vị đồng, phải lớn hơn 0
     * @param  string  $purpose  Nội dung chuyển khoản — dùng mã lượt khách để dò được trên sao kê
     */
    public static function build(
        string $bankBin,
        string $accountNumber,
        string $accountName,
        int $amountVnd,
        string $purpose,
    ): string {
        if ($amountVnd <= 0) {
            throw new InvalidArgumentException('Số tiền phải lớn hơn 0.');
        }

        $thongTinThuHuong = self::truong('00', self::GUID_NAPAS)
            .self::truong('01', self::truong('00', $bankBin).self::truong('01', $accountNumber))
            .self::truong('02', self::MA_DICH_VU_CHUYEN_KHOAN);

        $duLieuThem = self::truong('08', $purpose);

        $tenKhongDau = strtoupper(Str::ascii($accountName));

        $noiDungChuaCrc =
            self::truong('00', '01')
            .self::truong('01', '12') // 12 = dynamic — số tiền thay đổi theo từng lượt khách
            .self::truong('38', $thongTinThuHuong)
            .self::truong('53', self::MA_TIEN_TE_VND)
            .self::truong('54', (string) $amountVnd)
            .self::truong('58', self::MA_QUOC_GIA)
            .self::truong('59', $tenKhongDau)
            .self::truong('62', $duLieuThem);

        // Tiêu đề "6304" của chính trường CRC PHẢI nằm trong dữ liệu tính CRC,
        // giá trị CRC thì không — đây là quy định của chuẩn EMVCo, không phải
        // tự chọn.
        $chuaCoCrc = $noiDungChuaCrc.'6304';

        return $chuaCoCrc.self::crc16($chuaCoCrc);
    }

    private static function truong(string $id, string $value): string
    {
        return $id.str_pad((string) strlen($value), 2, '0', STR_PAD_LEFT).$value;
    }

    /** CRC-16/CCITT-FALSE — đa thức 0x1021, khởi tạo 0xFFFF, không đảo bit. Trả về 4 ký tự hex viết hoa. */
    private static function crc16(string $data): string
    {
        $crc = 0xFFFF;

        foreach (str_split($data) as $ky_tu) {
            $crc ^= (ord($ky_tu) << 8);

            for ($i = 0; $i < 8; $i++) {
                $crc = ($crc & 0x8000) ? (($crc << 1) ^ 0x1021) : ($crc << 1);
                $crc &= 0xFFFF;
            }
        }

        return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
    }
}
