<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Chênh lệch giữa tiền mặt ĐẾM THỰC TẾ và tiền mặt LẼ RA phải có khi đóng ca.
 *
 * Vì sao không dùng Money cho việc này: Money là bất biến "số tiền thật luôn
 * không âm" — đúng cho tiền trong két, tiền trên hoá đơn. Nhưng chênh lệch đối
 * soát là một khái niệm khác hẳn: một HIỆU SỐ CÓ DẤU, và trường hợp quan trọng
 * nhất cần ghi lại được chính là lúc nó âm (két thiếu tiền). Nhồi khái niệm này
 * vào Money sẽ phải phá vỡ bất biến "không âm" của Money cho MỌI chỗ dùng khác
 * (thu tiền, tính hoá đơn...) — rủi ro hơn nhiều so với tách riêng một class nhỏ.
 */
final readonly class CashVariance
{
    private function __construct(public int $amount) {}

    public static function between(Money $counted, Money $expected): self
    {
        return new self($counted->amount - $expected->amount);
    }

    public function isBalanced(): bool
    {
        return $this->amount === 0;
    }

    public function isShortage(): bool
    {
        return $this->amount < 0;
    }

    public function isSurplus(): bool
    {
        return $this->amount > 0;
    }

    public function absolute(): Money
    {
        return Money::fromInt(abs($this->amount));
    }

    /** "Thiếu 200.000 đ" / "Thừa 50.000 đ" / "Khớp" */
    public function format(): string
    {
        return match (true) {
            $this->isShortage() => 'Thiếu '.$this->absolute()->format(),
            $this->isSurplus() => 'Thừa '.$this->absolute()->format(),
            default => 'Khớp',
        };
    }
}
