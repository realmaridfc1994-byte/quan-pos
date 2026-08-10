<?php

declare(strict_types=1);

namespace App\Support;

use App\Exceptions\StockCostOverflowException;
use InvalidArgumentException;

/**
 * Thuật toán giá vốn bình quân gia quyền — xem docs/thiet-ke-gia-von.md.
 *
 * Lớp thuần, không đụng database. Toàn bộ số học là số nguyên — không float,
 * không decimal, không bcmath, cùng lý do với App\Support\Money.
 */
final class StockCost
{
    /**
     * Làm tròn nửa lên bằng số nguyên: intdiv(2a + b, 2b).
     *
     * @param  int  $a  tử số gốc, a >= 0
     * @param  int  $b  mẫu số gốc, b > 0
     */
    public static function lamTron(int $a, int $b): int
    {
        if ($a < 0) {
            throw new InvalidArgumentException("Tử số không được âm: {$a}");
        }

        if ($b <= 0) {
            throw new InvalidArgumentException("Mẫu số phải lớn hơn 0: {$b}");
        }

        $tuSo = self::congAnToan(self::nhanAnToan(2, $a), $b);
        $mauSo = self::nhanAnToan(2, $b);

        return intdiv($tuSo, $mauSo);
    }

    /**
     * Giá vốn khi xuất kho — bảng mục 4 của tài liệu, đủ bốn nhánh.
     *
     * @return array{cost: int, has_cost: bool}
     */
    public static function giaVonXuat(int $qty, int $totalCost, int $n): array
    {
        if ($qty > $n) {
            return [
                'cost' => self::lamTron(self::nhanAnToan($totalCost, $n), $qty),
                'has_cost' => true,
            ];
        }

        if ($qty === $n) {
            // Lấy hết sạch: dùng thẳng total_cost để về đúng 0, không sót đồng lẻ.
            return ['cost' => $totalCost, 'has_cost' => true];
        }

        if ($qty > 0) {
            // 0 < qty < n: lấy quá tồn, hết vốn ở đây.
            return ['cost' => $totalCost, 'has_cost' => false];
        }

        // qty <= 0: tồn đang âm, không còn gì để tính giá vốn.
        return ['cost' => 0, 'has_cost' => false];
    }

    /**
     * Giá vốn khi nhập vào theo giá trung bình hiện tại — mục 5.1
     * (kiểm kê thừa, điều chỉnh tăng không phải nhập hàng thật).
     *
     * @return array{cost: int, has_cost: bool}
     */
    public static function giaVonNhapTheoTrungBinh(int $qty, int $totalCost, int $n): array
    {
        if ($qty > 0) {
            return [
                'cost' => self::lamTron(self::nhanAnToan($totalCost, $n), $qty),
                'has_cost' => true,
            ];
        }

        // qty <= 0: không có giá trung bình để dùng.
        return ['cost' => 0, 'has_cost' => false];
    }

    /**
     * Nhân có kiểm tràn số 64 bit. PHP tự chuyển kết quả tràn thành float
     * thay vì báo lỗi — is_int() sau phép nhân là cách phát hiện chuẩn.
     */
    private static function nhanAnToan(int $x, int $y): int
    {
        $ketQua = $x * $y;

        if (! is_int($ketQua)) {
            throw new StockCostOverflowException(
                "Phép nhân {$x} × {$y} vượt giới hạn số nguyên 64 bit."
            );
        }

        return $ketQua;
    }

    private static function congAnToan(int $x, int $y): int
    {
        $ketQua = $x + $y;

        if (! is_int($ketQua)) {
            throw new StockCostOverflowException(
                "Phép cộng {$x} + {$y} vượt giới hạn số nguyên 64 bit."
            );
        }

        return $ketQua;
    }
}
