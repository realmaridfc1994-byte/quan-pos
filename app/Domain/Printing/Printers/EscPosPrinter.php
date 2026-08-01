<?php

declare(strict_types=1);

namespace App\Domain\Printing\Printers;

use App\Support\Money;

/**
 * Hỗ trợ sinh các lệnh ESC/POS cho máy in nhiệt 80mm.
 *
 * Máy in chấp nhận chuỗi byte ESC/POS — lệnh in được ghép bằng chuỗi điều khiển
 * bắt đầu bằng ESC (byte 0x1B) hoặc GS (byte 0x1D).
 */
final class EscPosPrinter
{
    private string $buffer = '';

    /**
     * Khởi tạo máy in (reset về trạng thái gốc).
     */
    public function initialize(): self
    {
        $this->buffer .= "\x1B\x40";

        return $this;
    }

    /**
     * Xuống dòng.
     */
    public function lineFeed(int $lines = 1): self
    {
        $this->buffer .= str_repeat("\x0A", $lines);

        return $this;
    }

    /**
     * In đôi chiều rộng (width × 2) và cao (height × 2).
     */
    public function setDoubleSize(): self
    {
        $this->buffer .= "\x1B\x21\x30"; // Set character spacing
        $this->buffer .= "\x1D\x21\x11"; // Double width & height

        return $this;
    }

    /**
     * Dừng in đôi, về kích thước bình thường.
     */
    public function setNormalSize(): self
    {
        $this->buffer .= "\x1B\x21\x00"; // Normal character spacing
        $this->buffer .= "\x1D\x21\x00"; // Normal size

        return $this;
    }

    /**
     * Căn lề: 0 = trái, 1 = giữa, 2 = phải.
     */
    public function setAlignment(int $alignment): self
    {
        $this->buffer .= "\x1B\x61".chr($alignment);

        return $this;
    }

    /**
     * In văn bản (tự động wrap cho 80mm).
     * 80mm ≈ 32 ký tự (tùy font).
     */
    public function text(string $text): self
    {
        $this->buffer .= $text;

        return $this;
    }

    /**
     * In một dòng có độ dài tối đa (wrap + line feed).
     */
    public function line(string $text = ''): self
    {
        $this->text($text);
        $this->lineFeed();

        return $this;
    }

    /**
     * In dòng kẻ ngang (dùng ký tự - để tạo đường kẻ).
     */
    public function separator(int $width = 32, string $char = '-'): self
    {
        $this->line(str_repeat($char, $width));

        return $this;
    }

    /**
     * Cắt giấy (cut paper).
     */
    public function cut(): self
    {
        $this->buffer .= "\x1D\x56\x41"; // Partial cut

        return $this;
    }

    /**
     * Lấy chuỗi byte ESC/POS đã tạo.
     */
    public function getOutput(): string
    {
        return $this->buffer;
    }

    /**
     * In hai cột: bên trái và bên phải (dùng để in giá).
     * Khổ 80mm ≈ 32 ký tự.
     */
    public static function formatTwoColumns(string $left, string $right, int $width = 32): string
    {
        $leftLen = mb_strlen($left);
        $rightLen = mb_strlen($right);
        $gap = $width - $leftLen - $rightLen;
        $gap = max($gap, 1);

        return $left.str_repeat(' ', $gap).$right;
    }

    /**
     * Định dạng tiền cho in ấn.
     */
    public static function formatMoney(Money $money): string
    {
        return $money->format();
    }
}
