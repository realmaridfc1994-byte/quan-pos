<?php

declare(strict_types=1);

namespace App\Domain\Printing\Templates;

use App\Domain\Printing\DTO\BillData;
use App\Domain\Printing\Printers\EscPosPrinter;
use App\Support\Money;

/**
 * Bill cuối cùng — in khi khách đã thanh toán.
 *
 * Bao gồm: danh sách món, giảm giá, tiền khách đưa, tiền thối,
 * hình thức thanh toán, lời cảm ơn.
 */
final class FinalBillTemplate
{
    public static function render(BillData $data): string
    {
        $printer = new EscPosPrinter;

        $printer
            ->initialize()
            ->setAlignment(1) // Căn giữa
            ->setDoubleSize()
            ->line('HÓA ĐƠN')
            ->setNormalSize()
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

        $printer->separator();

        // Thanh toán
        if ($data->paymentMethod === 'cash') {
            $cashLine = EscPosPrinter::formatTwoColumns(
                'Tiền mặt',
                $data->tenderedAmount->format(),
                32
            );
            $printer->line($cashLine);

            if ($data->changeAmount && ! $data->changeAmount->isZero()) {
                $changeLine = EscPosPrinter::formatTwoColumns(
                    'Tiền thối',
                    $data->changeAmount->format(),
                    32
                );
                $printer->setDoubleSize();
                $printer->line($changeLine);
                $printer->setNormalSize();
            }
        } elseif ($data->paymentMethod === 'transfer') {
            $printer->line('Thanh toán: Chuyển khoản');
            if ($data->paymentReference) {
                $printer->line("Mã gd: {$data->paymentReference}");
            }
        }

        $printer
            ->line()
            ->separator()
            ->setAlignment(1)
            ->line('Cảm ơn quý khách!')
            ->line('Hẹn gặp lại!')
            ->line()
            ->cut();

        return $printer->getOutput();
    }
}
