<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

/**
 * Số tiền VND, luôn là số nguyên đơn vị đồng và không bao giờ âm.
 *
 * Vì sao cần class này thay vì dùng int trần:
 *  - Chặn ngay tại chỗ việc trừ ra số âm (két âm tiền là chuyện vô nghĩa),
 *    thay vì để MySQL báo lỗi kiểu dữ liệu khó hiểu ở cuối giao dịch.
 *  - Chặn việc lỡ tay đưa float vào, làm sai một đồng trên hoá đơn.
 */
final readonly class Money
{
    private function __construct(public int $amount) {}

    public static function fromInt(int|float $amount): self
    {
        if (is_float($amount)) {
            throw new InvalidArgumentException('Số tiền phải là số nguyên đồng, không được là số thực.');
        }

        if ($amount < 0) {
            throw new InvalidArgumentException("Số tiền không được âm: {$amount}");
        }

        return new self($amount);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    public function plus(self $other): self
    {
        return new self($this->amount + $other->amount);
    }

    public function minus(self $other): self
    {
        if ($other->amount > $this->amount) {
            throw new InvalidArgumentException(
                "Phép trừ ra số âm: {$this->amount} - {$other->amount}"
            );
        }

        return new self($this->amount - $other->amount);
    }

    public function times(int $quantity): self
    {
        if ($quantity < 0) {
            throw new InvalidArgumentException("Số lượng không được âm: {$quantity}");
        }

        return new self($this->amount * $quantity);
    }

    /**
     * Tính phần trăm của số tiền hiện tại, làm tròn thông thường (0.5 lên).
     * Ví dụ: 12.345đ percentage(10) = 1.235đ (vì 1.234,5 làm tròn lên).
     */
    public function percentage(int $percent): self
    {
        if ($percent < 0) {
            throw new InvalidArgumentException("Phần trăm không được âm: {$percent}");
        }

        return new self((int) round($this->amount * $percent / 100));
    }

    public function isZero(): bool
    {
        return $this->amount === 0;
    }

    public function isAtLeast(self $other): bool
    {
        return $this->amount >= $other->amount;
    }

    public function isLessThan(self $other): bool
    {
        return $this->amount < $other->amount;
    }

    public function equals(self $other): bool
    {
        return $this->amount === $other->amount;
    }

    /** Định dạng cho người đọc: 1250000 → "1.250.000 đ" */
    public function format(): string
    {
        return number_format($this->amount, 0, ',', '.').' đ';
    }
}
