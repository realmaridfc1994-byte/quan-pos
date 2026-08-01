<?php

declare(strict_types=1);

namespace App\Domain\Printing\Templates;

use App\Domain\Printing\DTO\BillData;
use App\Domain\Printing\Printers\EscPosPrinter;
use App\Support\Money;

/**
 * Tạm tính — in trước khi khách thanh toán.
 *
 * Ghi rõ "PHIẾU TẠM TÍNH - CHƯA THANH TOÁN" để khách biết chưa phải thu tiền.
 */
final class ProvisionalBillTemplate
{
    public static function render(BillData $data): string
    {
        $printer = new EscPosPrinter;

        $printer
            ->initialize()
            ->setAlignment(1) // Căn giữa
            ->setDoubleSize()
            ->line('PHIẾU TẠM TÍNH')
            ->setNormalSize()
            ->line('CHƯA THANH TOÁN')
            ->line()
            ->separator()
            ->setAlignment(0); // Căn trái

        $printer->line("Bàn: {$data->tableName}");
        $printer->line("Mã: {$data->sessionCode}");
        $printer->line();

        $printer->separator();
        $printer->line('SỐ LƯỢNG | MÓN | ĐƠN GIÁ | THÀNH TIỀN');
        $printer->separator();

        foreach ($data->items as $item) {
            $qty = (string) $item['quantity'];
            $name = substr($item['product_name'], 0, 20);
            $unitPrice = Money::fromInt($item['unit_price']);
            $lineAmount = Money::fromInt($item['line_amount']);

            $line = EscPosPrinter::formatTwoColumns(
                "{$qty}x {$name}",
                $lineAmount->format(),
                32
            );
            $printer->line($line);

            if ($item['variant_name']) {
                $printer->line("   ({$item['variant_name']})");
            }
        }

        $printer->separator();

        $subtotalLine = EscPosPrinter::formatTwoColumns(
            'Cộng tiền',
            $data->subtotal->format(),
            32
        );
        $printer->line($subtotalLine);

        if (! $data->discountAmount->isZero()) {
            $discountLine = EscPosPrinter::formatTwoColumns(
                "Giảm giá ({$data->discountReason})",
                "-{$data->discountAmount->format()}",
                32
            );
            $printer->line($discountLine);
        }

        $printer->setDoubleSize();
        $totalLine = EscPosPrinter::formatTwoColumns(
            'TỔNG',
            $data->total->format(),
            32
        );
        $printer->line($totalLine);
        $printer->setNormalSize();

        $printer
            ->line()
            ->separator()
            ->setAlignment(1)
            ->line('Cảm ơn!')
            ->line()
            ->cut();

        return $printer->getOutput();
    }
}
