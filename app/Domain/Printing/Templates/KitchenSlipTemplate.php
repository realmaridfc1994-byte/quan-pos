<?php

declare(strict_types=1);

namespace App\Domain\Printing\Templates;

use App\Domain\Printing\DTO\KitchenSlipData;
use App\Domain\Printing\Printers\EscPosPrinter;

/**
 * Tem bếp — in cho một nơi làm (bếp / quầy pha chế).
 *
 * Chứa danh sách những món của một bàn mà nơi này cần làm.
 * Chữ to, dễ đọc, không ghi giá (bếp không cần biết giá).
 */
final class KitchenSlipTemplate
{
    public static function render(KitchenSlipData $data): string
    {
        $printer = new EscPosPrinter;

        $printer
            ->initialize()
            ->setAlignment(1) // Căn giữa
            ->setDoubleSize()
            ->line("BÀN {$data->tableName}")
            ->setNormalSize()
            ->line("{$data->station}")
            ->line()
            ->separator()
            ->line("Giờ: {$data->orderTime}")
            ->line("Người gọi: {$data->staffName}")
            ->line()
            ->separator();

        foreach ($data->items as $item) {
            $printer
                ->setDoubleSize()
                ->line("{$item['quantity']}x {$item['product_name']}")
                ->setNormalSize();

            if ($item['variant_name']) {
                $printer->line("   {$item['variant_name']}");
            }

            if ($item['note']) {
                $printer->line("   Ghi chú: {$item['note']}");
            }

            $printer->line();
        }

        $printer
            ->separator()
            ->line()
            ->cut();

        return $printer->getOutput();
    }
}
